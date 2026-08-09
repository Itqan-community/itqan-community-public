<?php

namespace Itqan\MailerLite\Console;

use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Queue\Queue;
use Itqan\MailerLite\Api\MailerLiteClient;
use Itqan\MailerLite\Jobs\AddToGroupJob;
use Itqan\MailerLite\Models\MailerLiteSubscriber;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputOption;

class CheckPowerUsersCommand extends AbstractCommand
{
    protected MailerLiteClient $client;
    protected SettingsRepositoryInterface $settings;
    protected Queue $queue;

    public function __construct(
        MailerLiteClient $client,
        SettingsRepositoryInterface $settings,
        Queue $queue
    ) {
        parent::__construct();
        $this->client = $client;
        $this->settings = $settings;
        $this->queue = $queue;
    }

    protected function configure(): void
    {
        $this
            ->setName('mailerlite:check-power-users')
            ->setDescription('Check for power users and add them to the power-users group')
            ->addOption('posts', 'p', InputOption::VALUE_OPTIONAL, 'Minimum posts threshold (overrides setting)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be changed without making changes');
    }

    protected function fire(): int
    {
        if (!$this->client->isEnabled()) {
            $this->error('MailerLite integration is not enabled or API key is missing.');
            return 1;
        }

        $minPosts = $this->input->getOption('posts')
            ?? $this->settings->get('itqan-mailerlite.power_user_posts', 50);

        $dryRun = $this->input->getOption('dry-run');
        $groupName = $this->settings->get('itqan-mailerlite.group_power_users', 'power-users');

        $this->info("Checking for users with {$minPosts}+ posts...");

        // Find users who qualify as power users but aren't in the group yet
        $existingPowerUserIds = MailerLiteSubscriber::query()
            ->where('sync_status', MailerLiteSubscriber::STATUS_SYNCED)
            ->whereJsonContains('groups', $groupName)
            ->pluck('user_id');

        $query = User::query()
            ->where('comment_count', '>=', (int) $minPosts)
            ->where('is_email_confirmed', true)
            ->whereNotIn('id', $existingPowerUserIds);

        $totalUsers = $query->count();

        if ($totalUsers === 0) {
            $this->info('No new power users found.');
            return 0;
        }

        $this->info("Found {$totalUsers} new power users.");

        if ($dryRun) {
            $this->info('Dry run - no changes will be made.');
            $users = $query->limit(10)->get();
            $this->info('Sample users that would be marked as power users:');
            foreach ($users as $user) {
                $this->line("  - {$user->username} ({$user->comment_count} posts)");
            }
            if ($totalUsers > 10) {
                $this->info('  ... and ' . ($totalUsers - 10) . ' more');
            }
            return 0;
        }

        $progressBar = new ProgressBar($this->output, $totalUsers);
        $progressBar->start();

        $processedCount = 0;

        $query->orderBy('id')
            ->chunk(100, function ($users) use ($progressBar, &$processedCount) {
                foreach ($users as $user) {
                    $this->queue->push(new AddToGroupJob($user->id, 'group_power_users'));
                    $processedCount++;
                    $progressBar->advance();
                }
            });

        $progressBar->finish();
        $this->output->writeln('');

        $this->info("Queued {$processedCount} users to be added to power-users group.");

        return 0;
    }
}
