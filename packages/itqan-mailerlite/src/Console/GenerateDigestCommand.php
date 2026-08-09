<?php

namespace Itqan\MailerLite\Console;

use Carbon\Carbon;
use Flarum\Console\AbstractCommand;
use Flarum\Discussion\Discussion;
use Flarum\Settings\SettingsRepositoryInterface;
use Itqan\MailerLite\Api\GroupManager;
use Itqan\MailerLite\Api\MailerLiteClient;
use Symfony\Component\Console\Input\InputOption;

class GenerateDigestCommand extends AbstractCommand
{
    protected MailerLiteClient $client;
    protected GroupManager $groupManager;
    protected SettingsRepositoryInterface $settings;

    public function __construct(
        MailerLiteClient $client,
        GroupManager $groupManager,
        SettingsRepositoryInterface $settings
    ) {
        parent::__construct();
        $this->client = $client;
        $this->groupManager = $groupManager;
        $this->settings = $settings;
    }

    protected function configure(): void
    {
        $this
            ->setName('mailerlite:generate-digest')
            ->setDescription('Generate and send a digest email with top discussions')
            ->addOption('period', 'p', InputOption::VALUE_OPTIONAL, 'Period: weekly or monthly', 'weekly')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Number of top discussions to include', 10)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show digest content without sending');
    }

    protected function fire(): int
    {
        if (!$this->client->isEnabled()) {
            $this->error('MailerLite integration is not enabled or API key is missing.');
            return 1;
        }

        $period = $this->input->getOption('period');
        $limit = (int) $this->input->getOption('limit');
        $dryRun = $this->input->getOption('dry-run');

        // Calculate date range based on period
        $endDate = Carbon::now();
        $startDate = match ($period) {
            'monthly' => Carbon::now()->subMonth(),
            default => Carbon::now()->subWeek(),
        };

        $this->info("Generating {$period} digest for {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");

        // Get top discussions by comment count in the period
        $discussions = Discussion::query()
            ->where('created_at', '>=', $startDate)
            ->where('is_private', false)
            ->whereNull('hidden_at')
            ->orderByDesc('comment_count')
            ->limit($limit)
            ->with(['user', 'firstPost'])
            ->get();

        if ($discussions->isEmpty()) {
            $this->info('No discussions found for the specified period.');
            return 0;
        }

        $this->info("Found {$discussions->count()} top discussions.");

        // Build digest content
        $forumUrl = $this->settings->get('url', 'https://community.itqan.dev');
        $forumTitle = $this->settings->get('forum_title', 'Itqan Community');

        $periodLabel = $period === 'monthly' ? 'Monthly' : 'Weekly';
        $subject = "{$forumTitle} - {$periodLabel} Digest";

        // Idempotency: skip if digest already sent for this period
        $periodKey = $period . '-' . ($period === 'monthly' ? $endDate->format('Y-m') : $endDate->format('Y-W'));
        $lastSentKey = $this->settings->get('itqan-mailerlite.last_digest_period');
        if (!$dryRun && $lastSentKey === $periodKey) {
            $this->info("Digest already sent for period {$periodKey}, skipping.");
            return 0;
        }

        $content = $this->buildDigestHtml($discussions, $forumUrl, $forumTitle, $periodLabel, $startDate, $endDate);

        if ($dryRun) {
            $this->info('Dry run - digest content:');
            $this->newLine();
            $this->line("Subject: {$subject}");
            $this->newLine();
            $this->info('Top Discussions:');
            foreach ($discussions as $discussion) {
                $this->line("  - {$discussion->title} ({$discussion->comment_count} comments)");
            }
            return 0;
        }

        // Send digest to all configured subscriber groups
        $allGroups = $this->groupManager->getAllConfiguredGroups();
        $groupIds = array_column($allGroups, 'id');

        if (empty($groupIds)) {
            $this->error('Could not find or create any recipient groups.');
            return 1;
        }

        $this->info('Sending digest to ' . count($groupIds) . ' group(s): ' . implode(', ', array_column($allGroups, 'name')));

        // Create campaign in MailerLite
        $campaignName = "{$periodLabel} Digest - {$endDate->format('Y-m-d')}";
        $result = $this->client->createCampaign($campaignName, $subject, $content, $groupIds);

        if (!$result['success']) {
            $this->error('Failed to create campaign: ' . ($result['error'] ?? 'Unknown error'));
            return 1;
        }

        $campaignId = $result['data']['id'] ?? null;

        if ($campaignId) {
            $this->info("Created campaign: {$campaignId}");

            // Schedule immediately
            $scheduleResult = $this->client->scheduleCampaign($campaignId);

            if ($scheduleResult['success']) {
                $this->info('Digest campaign scheduled for immediate delivery.');
                $this->settings->set('itqan-mailerlite.last_digest_period', $periodKey);
            } else {
                $this->warn('Campaign created but scheduling failed: ' . ($scheduleResult['error'] ?? 'Unknown error'));
            }
        }

        return 0;
    }

    private function buildDigestHtml(
        $discussions,
        string $forumUrl,
        string $forumTitle,
        string $periodLabel,
        Carbon $startDate,
        Carbon $endDate
    ): string {
        $discussionsList = '';

        foreach ($discussions as $discussion) {
            $discussionUrl = "{$forumUrl}/d/{$discussion->id}-{$discussion->slug}";
            $author = $discussion->user ? $discussion->user->display_name : 'Unknown';
            $commentCount = $discussion->comment_count;
            $excerpt = $discussion->firstPost
                ? strip_tags(substr($discussion->firstPost->content, 0, 200)) . '...'
                : '';

            $discussionsList .= <<<HTML
            <tr>
                <td style="padding: 15px 0; border-bottom: 1px solid #e0e0e0;">
                    <h3 style="margin: 0 0 8px 0;">
                        <a href="{$discussionUrl}" style="color: #09c269; text-decoration: none;">
                            {$discussion->title}
                        </a>
                    </h3>
                    <p style="margin: 0 0 8px 0; color: #666; font-size: 14px;">
                        By {$author} · {$commentCount} comments
                    </p>
                    <p style="margin: 0; color: #444; font-size: 14px;">
                        {$excerpt}
                    </p>
                </td>
            </tr>
            HTML;
        }

        $dateRange = $startDate->format('M j') . ' - ' . $endDate->format('M j, Y');

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 0; background-color: #f5f5f5;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 20px 0;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                            <!-- Header -->
                            <tr>
                                <td style="background-color: #09c269; padding: 30px; text-align: center;">
                                    <h1 style="color: #ffffff; margin: 0; font-size: 24px;">{$forumTitle}</h1>
                                    <p style="color: #ffffff; margin: 10px 0 0 0; opacity: 0.9;">{$periodLabel} Digest</p>
                                </td>
                            </tr>

                            <!-- Date Range -->
                            <tr>
                                <td style="padding: 20px 30px 0 30px; text-align: center;">
                                    <p style="color: #666; margin: 0; font-size: 14px;">{$dateRange}</p>
                                </td>
                            </tr>

                            <!-- Intro -->
                            <tr>
                                <td style="padding: 20px 30px;">
                                    <p style="color: #444; margin: 0; line-height: 1.6;">
                                        Here are the most popular discussions from our community this {$periodLabel}.
                                        Click on any title to join the conversation!
                                    </p>
                                </td>
                            </tr>

                            <!-- Discussions List -->
                            <tr>
                                <td style="padding: 0 30px;">
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        {$discussionsList}
                                    </table>
                                </td>
                            </tr>

                            <!-- CTA -->
                            <tr>
                                <td style="padding: 30px; text-align: center;">
                                    <a href="{$forumUrl}" style="display: inline-block; background-color: #09c269; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-weight: 600;">
                                        Visit the Forum
                                    </a>
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style="background-color: #f9f9f9; padding: 20px 30px; text-align: center; border-top: 1px solid #e0e0e0;">
                                    <p style="color: #999; margin: 0; font-size: 12px;">
                                        You're receiving this because you're a member of {$forumTitle}.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;
    }
}
