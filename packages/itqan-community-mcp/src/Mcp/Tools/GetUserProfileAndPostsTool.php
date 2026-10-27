<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Mcp\Tools;

use Flarum\User\User;
use InvalidArgumentException;
use Itqan\CommunityMcp\Formatter\PostMarkdownFormatter;
use Itqan\CommunityMcp\Search\ForumSearchService;
use Itqan\CommunityMcp\Support\Entity;

/**
 * `get_user_contributions` — a member profile plus their recent activity.
 *
 * Accepts `@username`, `username`, a numeric user ID, or a full profile URL.
 * The user must be visible to the acting user (`User::whereVisibleTo`).
 */
class GetUserProfileAndPostsTool implements ToolInterface
{
    private ForumSearchService $search;

    public function __construct(ForumSearchService $search)
    {
        $this->search = $search;
    }

    public function getName(): string
    {
        return 'get_user_contributions';
    }

    public function getDescription(): string
    {
        return 'Read an Itqan Community member profile and their recent contributions: '
            .'display name, username, profile URL, join date, discussion/comment counts, '
            .'bio, groups, badges, plus their latest discussions and latest replies with '
            .'permalinks, votes and snippets. Accepts @username, username, numeric user ID '
            .'or a profile URL.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'username' => [
                    'type' => 'string',
                    'description' => 'Member handle: "@hadi", "hadi", a numeric user ID, or https://community.itqan.dev/u/hadi.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 20,
                    'description' => 'How many recent discussions and replies to show for each section (default 10, maximum 20).',
                    'default' => 10,
                ],
            ],
            'required' => ['username'],
            'additionalProperties' => false,
        ];
    }

    public function getAnnotations(): array
    {
        return [
            'readOnlyHint' => true,
            'openWorldHint' => false,
        ];
    }

    public function execute(array $arguments, User $actor): string
    {
        $handle = isset($arguments['username']) && is_scalar($arguments['username'])
            ? trim((string) $arguments['username'])
            : '';

        if ($handle === '') {
            throw new InvalidArgumentException('`username` is required and must be a username, user ID or profile URL.');
        }

        $limit = isset($arguments['limit']) && is_numeric($arguments['limit'])
            ? (int) $arguments['limit']
            : 10;

        $limit = max(1, min(20, $limit));

        $user = $this->search->findUserByHandle($handle, $actor);

        if ($user === null) {
            return $this->renderNotFound($handle);
        }

        return $this->render($user, $actor, $limit);
    }

    public function render(User $user, User $actor, int $limit = 10): string
    {
        $baseUrl = $this->search->getBaseUrl();
        $username = Entity::username($user);
        $displayName = Entity::displayName($user);
        $userId = (int) Entity::attr($user, 'id');

        $lines = [];
        $lines[] = '# '.$displayName.($username !== null ? ' (@'.$username.')' : '');
        $lines[] = '';
        $lines[] = '- **اسم العرض (Display name):** '.$displayName;
        $lines[] = '- **اسم المستخدم (Username):** '.($username !== null ? '@'.$username : '—');
        $lines[] = '- **معرف العضو (User ID):** '.$userId;
        $lines[] = '- **الملف الشخصي (Profile):** '.($username !== null ? $baseUrl.'/u/'.$username : $baseUrl.'/u/'.$userId);

        $joinedAt = Entity::date(Entity::attr($user, 'joined_at'));

        if ($joinedAt !== null) {
            $lines[] = '- **تاريخ الانضمام (Joined):** '.$joinedAt;
        }

        if ($this->search->hasColumn('users', 'discussion_count')) {
            $lines[] = '- **المناقشات (Discussions):** '.Entity::int($user, 'discussion_count');
        }

        if ($this->search->hasColumn('users', 'comment_count')) {
            $lines[] = '- **المشاركات (Comments):** '.Entity::int($user, 'comment_count');
        }

        $groups = $this->groupNames($user);

        if ($groups !== []) {
            $lines[] = '- **المجموعات (Groups):** '.implode(', ', $groups);
        }

        $badges = $this->search->getUserBadges($userId);

        if ($badges !== []) {
            $lines[] = '- **الأوسمة (Badges):** '.implode(', ', array_map(
                static fn (array $badge): string => $badge['name'],
                $badges
            ));
        }

        $bio = $this->bioMarkdown($user);

        if ($bio !== '') {
            $lines[] = '';
            $lines[] = '## نبذة (Bio)';
            $lines[] = '';
            $lines[] = $bio;
        }

        // ------------------------------------------------------------------
        // Discussions started by the member
        // ------------------------------------------------------------------
        $discussions = $this->search->getUserRecentDiscussions($user, $actor, $limit);

        $lines[] = '';
        $lines[] = '## أحدث المناقشات (Recent discussions)';
        $lines[] = '';

        if ($discussions === []) {
            $lines[] = '_لا توجد مناقشات ظاهرة لهذا العضو (No visible discussions)._';
        } else {
            foreach ($discussions as $index => $discussion) {
                $lines[] = sprintf('### %d. %s', $index + 1, $discussion['title']);
                $lines[] = '';
                $lines[] = '- **الرابط (URL):** '.$discussion['url'];

                if (! empty($discussion['created_at'])) {
                    $lines[] = '- **التاريخ (Date):** '.$discussion['created_at'];
                }

                $lines[] = '- **التفاعل (Engagement):** الردود (Replies): '.(int) $discussion['reply_count']
                    .' · المشاركون (Participants): '.(int) $discussion['participant_count']
                    .((int) $discussion['votes'] !== 0 ? ' · 👍 الأصوات (Votes): '.(int) $discussion['votes'] : '');

                if (($discussion['tags'] ?? []) !== []) {
                    $lines[] = '- **الوسوم (Tags):** '.implode(', ', array_map(
                        static fn (array $tag): string => '`'.$tag['slug'].'`'.($tag['name'] !== '' ? ' ('.$tag['name'].')' : ''),
                        $discussion['tags']
                    ));
                }

                $lines[] = '';
            }
        }

        // ------------------------------------------------------------------
        // Replies written by the member
        // ------------------------------------------------------------------
        $posts = $this->search->getUserRecentPosts($user, $actor, $limit);

        $lines[] = '## أحدث المشاركات (Recent replies)';
        $lines[] = '';

        if ($posts === []) {
            $lines[] = '_لا توجد مشاركات ظاهرة لهذا العضو (No visible replies)._';
        } else {
            foreach ($posts as $index => $post) {
                $lines[] = sprintf(
                    '### %d. %s — مشاركة #%d',
                    $index + 1,
                    $post['discussion_title'] !== '' ? $post['discussion_title'] : 'مناقشة #'.$post['discussion_id'],
                    $post['post_number']
                );
                $lines[] = '';
                $lines[] = '- **الرابط المباشر (Permalink):** '.$post['url'];
                $lines[] = '- **المناقشة (Discussion):** '.$post['discussion_url'];

                if (! empty($post['created_at'])) {
                    $lines[] = '- **التاريخ (Date):** '.$post['created_at'];
                }

                if (! empty($post['reply_to_post_id'])) {
                    $parentNumber = $post['parent_post_number'] ?? null;
                    $parentUsername = $post['parent_author_username'] ?? null;
                    $parentLabel = $parentUsername !== null
                        ? '@'.$parentUsername
                        : (string) ($post['parent_author_display_name'] ?? Entity::DELETED_LABEL);

                    $lines[] = sprintf(
                        '- **رداً على (In reply to):** المشاركة #%s بواسطة %s',
                        $parentNumber !== null ? (string) $parentNumber : '?',
                        $parentLabel
                    );
                }

                if ((int) ($post['votes'] ?? 0) !== 0) {
                    $lines[] = '- 👍 الأصوات (Votes): '.(int) $post['votes'];
                }

                if (! empty($post['snippet'])) {
                    $lines[] = '';
                    $lines[] = '> '.$post['snippet'];
                }

                $lines[] = '';
            }
        }

        return trim(implode("\n", $lines));
    }

    private function renderNotFound(string $handle): string
    {
        return trim(implode("\n", [
            '# لم يتم العثور على العضو (Member not found)',
            '',
            'لم يتم العثور على عضو مطابق لـ `'.$handle.'` أو أن الملف غير مرئي.',
            'No visible member matches `'.$handle.'`.',
            '',
            'تحقق من اسم المستخدم أو استخدم معرفًا رقميًا أو رابط الملف الشخصي.',
        ]));
    }

    /**
     * @return array<int, string>
     */
    private function groupNames(User $user): array
    {
        try {
            $groups = $user->groups;
        } catch (\Throwable) {
            return [];
        }

        if (! is_iterable($groups)) {
            return [];
        }

        $names = [];

        foreach ($groups as $group) {
            $name = Entity::attr($group, 'name');

            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function bioMarkdown(User $user): string
    {
        $bio = Entity::attr($user, 'bio');

        if (! is_string($bio) || trim($bio) === '') {
            return '';
        }

        $markdown = str_starts_with(trim($bio), '<')
            ? PostMarkdownFormatter::xmlToMarkdown($bio)
            : PostMarkdownFormatter::normalizeWhitespace($bio);

        if (mb_strlen($markdown, 'UTF-8') > 1000) {
            $markdown = rtrim(mb_substr($markdown, 0, 1000, 'UTF-8')).'…';
        }

        return $markdown;
    }
}
