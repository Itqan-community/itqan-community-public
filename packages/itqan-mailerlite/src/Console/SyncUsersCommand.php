<?php

namespace Itqan\MailerLite\Console;

use Flarum\Console\AbstractCommand;
use Flarum\User\User;
use Illuminate\Contracts\Queue\Queue;
use Itqan\MailerLite\Api\MailerLiteClient;
use Itqan\MailerLite\Jobs\SyncSubscriberJob;
use Itqan\MailerLite\Models\MailerLiteSubscriber;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputOption;

class SyncUsersCommand extends AbstractCommand
{
    protected MailerLiteClient $client;
    protected Queue $queue;

    public function __construct(MailerLiteClient $client, Queue $queue)
    {
        parent::__construct();
        $this->client = $client;
        $this->queue = $queue;
    }

    protected function configure(): void
    {
        $this
            ->setName('mailerlite:sync-users')
            ->setDescription('Sync all users to MailerLite')
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Number of users to process per batch', 100)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force re-sync all users, even those already synced')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be synced without making changes');
    }

    protected function fire(): int
    {
        if (!$this->client->isEnabled()) {
            $this->error('MailerLite integration is not enabled or API key is missing.');
            return 1;
        }

        $batchSize = (int) $this->input->getOption('batch-size');
        $force = $this->input->getOption('force');
        $dryRun = $this->input->getOption('dry-run');

        $query = User::query()
            ->where('is_email_confirmed', true)
            ->whereNotNull('email');

        if (!$force) {
            // Only sync users who haven't been synced or have failed
            $syncedUserIds = MailerLiteSubscriber::query()
                ->where('sync_status', MailerLiteSubscriber::STATUS_SYNCED)
                ->pluck('user_id');

            $query->whereNotIn('id', $syncedUserIds);
        }

        $totalUsers = $query->count();

        if ($totalUsers === 0) {
            $this->info('No users to sync.');
            return 0;
        }

        $this->info("Found {$totalUsers} users to sync.");

        if ($dryRun) {
            $this->info('Dry run - no changes will be made.');
            $users = $query->limit(10)->get();
            $this->info('Sample users that would be synced:');
            foreach ($users as $user) {
                $this->line("  - {$user->username} ({$user->email})");
            }
            if ($totalUsers > 10) {
                $this->info('  ... and ' . ($totalUsers - 10) . ' more');
            }
            return 0;
        }

        $progressBar = new ProgressBar($this->output, $totalUsers);
        $progressBar->start();

        $syncedCount = 0;
        $errorCount = 0;

        $query->orderBy('id')
            ->chunk($batchSize, function ($users) use ($progressBar, &$syncedCount, &$errorCount) {
                MailerLiteSubscriber::query()->getConnection()
                    ->table('mailerlite_subscribers')
                    ->insertOrIgnore(
                        $users->map(function ($u) {
                            return [
                                'user_id'     => $u->id,
                                'email'       => $u->email,
                                'sync_status' => MailerLiteSubscriber::STATUS_PENDING,
                            ];
                        })->all()
                    );

                foreach ($users as $user) {
                    try {
                        $this->queue->push(new SyncSubscriberJob($user->id, ['group_new_members']));
                        $syncedCount++;
                    } catch (\Exception $e) {
                        $errorCount++;
                    }

                    $progressBar->advance();
                }
            });

        $progressBar->finish();
        $this->output->writeln('');

        $this->info("Queued {$syncedCount} users for sync.");

        if ($errorCount > 0) {
            $this->warn("Failed to queue {$errorCount} users.");
        }

        return 0;
    }
}
