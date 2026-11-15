<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Tests\Unit;

use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\User\Guest;
use Flarum\User\User;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Collection;
use Itqan\CommunityMcp\Formatter\PostMarkdownFormatter;
use Itqan\CommunityMcp\Mcp\Tools\GetDiscussionTool;
use Itqan\CommunityMcp\Mcp\Tools\GetUserProfileAndPostsTool;
use Itqan\CommunityMcp\Search\ForumSearchService;
use Itqan\CommunityMcp\Support\ArraySettings;
use Itqan\CommunityMcp\Support\Entity;
use Itqan\CommunityMcp\Tests\Support\TestCase;

/**
 * Privacy regressions for the two tools that render user-supplied content:
 *
 * - a soft-deleted placeholder (`hidden_at`) must never reveal its body or its
 *   author, even when the reply tree keeps it on the page;
 * - a member profile must only expose explicitly whitelisted public fields,
 *   never email, password hash, preferences or last-seen activity.
 */
final class ToolPrivacyTest extends TestCase
{
    public function testHiddenPlaceholderPostNeverLeaksContentOrAuthor(): void
    {
        $hiddenPost = $this->post(2, '<t>TOP_SECRET_HIDDEN_BODY</t>', 'hidden_author', '2026-10-26 00:00:00');
        $visiblePost = $this->post(3, '<t>VISIBLE_CHILD_BODY</t>', 'public_author');

        $service = new class($hiddenPost, $visiblePost) extends ForumSearchService {
            private Post $hiddenPost;

            private Post $visiblePost;

            public function __construct(Post $hiddenPost, Post $visiblePost)
            {
                parent::__construct(new CacheRepository(new ArrayStore()), new ArraySettings());

                $this->hiddenPost = $hiddenPost;
                $this->visiblePost = $visiblePost;
            }

            public function getBaseUrl(): string
            {
                return 'https://community.example';
            }

            public function findVisibleDiscussion(int $discussionId, User $actor): ?Discussion
            {
                $discussion = new Discussion();
                $discussion->setRawAttributes([
                    'id' => 700,
                    'title' => 'Fixture discussion',
                    'created_at' => '2026-10-25 00:00:00',
                    'participant_count' => 2,
                    'comment_count' => 2,
                ], true);
                $discussion->setRelation('tags', new Collection());

                return $discussion;
            }

            public function getDiscussionPosts(Discussion $discussion, User $actor, int $offset = 0, int $limit = 25): array
            {
                return [
                    'posts' => [$this->hiddenPost, $this->visiblePost],
                    'total' => 2,
                    'offset' => $offset,
                    'limit' => $limit,
                ];
            }

            public function resolvePostThreadMetadata(iterable $posts, ?User $actor = null): array
            {
                return [];
            }

            public function getDiscussionEngagement(Discussion $discussion, User $actor): array
            {
                return ['votes' => 0, 'likes_count' => 0, 'detected_lang' => null];
            }
        };

        $markdown = (new GetDiscussionTool($service))->renderDiscussionMarkdown(700, new Guest(), 0, 50);

        $this->assertNotNull($markdown);
        $markdown = (string) $markdown;
        $this->assertStringNotContainsString('TOP_SECRET_HIDDEN_BODY', $markdown);
        $this->assertStringNotContainsString('hidden_author', $markdown);
        $this->assertStringContainsString(PostMarkdownFormatter::HIDDEN_MESSAGE, $markdown);
        $this->assertStringContainsString(Entity::DELETED_LABEL, $markdown);
        $this->assertStringContainsString('VISIBLE_CHILD_BODY', $markdown);
        $this->assertStringContainsString('public_author', $markdown);
    }

    public function testUserProfileNeverExposesPrivateAccountFields(): void
    {
        $user = new User();
        $user->setRawAttributes([
            'id' => 42,
            'username' => 'hadi',
            'nickname' => 'هادي الأحمد',
            'email' => 'hadi@example.com',
            'password' => '$2y$10$super-secret-hash-value',
            'preferences' => (string) json_encode(['discloseOnline' => false]),
            'last_seen_at' => '2026-10-26 09:00:00',
            'joined_at' => '2020-01-02 00:00:00',
            'is_email_confirmed' => 1,
            'discussion_count' => 3,
            'comment_count' => 7,
            'bio' => '<t>مطور ويب</t>',
        ], true);
        $user->setRelation('groups', new Collection());

        $service = new class extends ForumSearchService {
            public function __construct()
            {
                parent::__construct(new CacheRepository(new ArrayStore()), new ArraySettings());
            }

            public function getBaseUrl(): string
            {
                return 'https://community.example';
            }

            public function hasColumn(string $table, string $column): bool
            {
                return in_array($column, ['discussion_count', 'comment_count'], true);
            }

            public function getUserBadges(int $userId): array
            {
                return [];
            }

            public function getUserRecentDiscussions(User $user, User $actor, int $limit = 10): array
            {
                return [];
            }

            public function getUserRecentPosts(User $user, User $actor, int $limit = 10): array
            {
                return [];
            }
        };

        $markdown = (new GetUserProfileAndPostsTool($service))->render($user, new Guest(), 5);

        $this->assertStringContainsString('@hadi', $markdown);
        $this->assertStringContainsString('هادي الأحمد', $markdown);
        $this->assertStringNotContainsString('hadi@example.com', $markdown);
        $this->assertStringNotContainsString('super-secret-hash-value', $markdown);
        $this->assertStringNotContainsString('discloseOnline', $markdown);
        $this->assertStringNotContainsString('preferences', $markdown);
        $this->assertStringNotContainsString('2026-10-26 09:00', $markdown);
        $this->assertStringNotContainsString('is_email_confirmed', $markdown);
    }

    private function post(int $number, string $content, string $username, ?string $hiddenAt = null): Post
    {
        $post = new Post();
        $post->setRawAttributes([
            'id' => 900 + $number,
            'discussion_id' => 700,
            'number' => $number,
            'content' => $content,
            'hidden_at' => $hiddenAt,
            'created_at' => '2026-10-25 00:00:00',
        ], true);

        $user = new User();
        $user->setRawAttributes(['id' => 1, 'username' => $username, 'nickname' => $username], true);
        $post->setRelation('user', $user);

        return $post;
    }
}
