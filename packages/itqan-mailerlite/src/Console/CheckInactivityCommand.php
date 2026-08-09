<?php

namespace Itqan\MailerLite\Console;

use Carbon\Carbon;
use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Queue\Queue;
use Itqan\MailerLite\Api\MailerLiteClient;
use Itqan\MailerLite\Jobs\AddToGroupJob;
use Itqan\MailerLite\Models\MailerLiteSubscriber;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputOption;

class CheckInactivityCommand extends AbstractCommand
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
            ->setName('mailerlite:check-inactivity')
            ->setDescription('Check for inactive users and add them to the inactive-users group')
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Days of inactivity threshold (overrides setting)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be changed without making changes');
    }

    protected function fire(): int
    {
        if (!$this->client->isEnabled()) {
            $this->error('MailerLite integration is not enabled or API key is missing.');
            return 1;
        }

        $inactivityDays = $this->input->getOption('days')
            ?? $this->settings->get('itqan-mailerlite.inactivity_days', 30);

        $dryRun = $this->input->getOption('dry-run');
        $cutoffDate = Carbon::now()->subDays((int) $inactivityDays);

        $groupName = $this->settings->get('itqan-mailerlite.group_inactive_users', 'inactive-users');

        $this->info("Checking for users inactive for {$inactivityDays}+ days...");
        $this->info("Cutoff date: {$cutoffDate->format('Y-m-d H:i:s')}");

        // Find users who are inactive and not already in the inactive group
        $inactiveUserIds = MailerLiteSubscriber::query()
            ->where('sync_status', MailerLiteSubscriber::STATUS_SYNCED)
            ->whereJsonDoesntContain('groups', $groupName)
            ->pluck('user_id');

        $query = User::query()
            ->whereIn('id', $inactiveUserIds)
            ->where(function ($q) use ($cutoffDate) {
                $q->where('last_seen_at', '<', $cutoffDate)
                    ->orWhereNull('last_seen_at');
            })
            ->where('is_email_confirmed', true);

        $totalUsers = $query->count();

        if ($totalUsers === 0) {
            $this->info('No inactive users found.');
            return 0;
        }

        $this->info("Found {$totalUsers} inactive users.");

        if ($dryRun) {
            $this->info('Dry run - no changes will be made.');
            $users = $query->limit(10)->get();
            $this->info('Sample users that would be marked inactive:');
            foreach ($users as $user) {
                $lastSeen = $user->last_seen_at ? $user->last_seen_at->format('Y-m-d') : 'never';
                $this->line("  - {$user->username} (last seen: {$lastSeen})");
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
                    $this->queue->push(new AddToGroupJob($user->id, 'group_inactive_users'));
                    $processedCount++;
                    $progressBar->advance();
                }
            });

        $progressBar->finish();
        $this->output->writeln('');

        $this->info("Queued {$processedCount} users to be added to inactive-users group.");

        return 0;
    }
}
