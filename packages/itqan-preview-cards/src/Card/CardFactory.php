<?php

namespace Itqan\PreviewCards\Card;

use Carbon\CarbonInterface;
use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\Paths;
use Flarum\Http\UrlGenerator;
use Flarum\Post\CommentPost;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Itqan\PreviewCards\Settings;

class CardFactory
{
    private const ARABIC_MONTHS = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'مايو', 6 => 'يونيو',
        7 => 'يوليو', 8 => 'أغسطس', 9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
    ];

    private const RASTER_MIMES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
    private const IMAGE_MIMES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];

    private ?string $logoCache = null;
    private bool $logoLoaded = false;
    private ?string $backgroundCache = null;
    private bool $backgroundLoaded = false;

    public function __construct(
        protected ExtensionManager $extensions,
        protected Settings $settings,
        protected SettingsRepositoryInterface $repository,
        protected UrlGenerator $url,
        protected Paths $paths,
    ) {
    }

    public function localeFor(Discussion $discussion): string
    {
        $policy = $this->settings->languagePolicy();

        if ($policy !== 'auto') {
            return $policy;
        }

        if ($this->extensions->isEnabled('fof-discussion-language') && $discussion->language_id) {
            $discussion->loadMissing('language');
            $code = strtolower((string) ($discussion->language?->code ?? ''));
            $short = substr($code, 0, 2);

            if (in_array($short, ['ar', 'en'], true)) {
                return $short;
            }
        }

        return $this->settings->fallbackLocale();
    }

    public function brandFor(string $locale): string
    {
        $override = trim((string) $this->repository->get('itqan-preview-cards.brand_'.$locale, ''));

        if ($override !== '') {
            return $override;
        }

        return (string) $this->repository->get('forum_title', 'Community');
    }

    public function logoDataUri(): ?string
    {
        if (! $this->logoLoaded) {
            $this->logoLoaded = true;
            $custom = $this->settings->cardLogoPath();
            $logo = $custom !== '' ? $custom : (string) $this->repository->get('logo_path', '');
            $this->logoCache = $logo !== ''
                ? $this->fileDataUri($this->paths->public.'/assets/'.ltrim($logo, '/'), self::IMAGE_MIMES, 2097152)
                : null;
        }

        return $this->logoCache;
    }

    public function colors(): array
    {
        return $this->settings->colors();
    }

    public function backgroundDataUri(): ?string
    {
        if (! $this->backgroundLoaded) {
            $this->backgroundLoaded = true;
            $path = $this->settings->backgroundPath();
            $this->backgroundCache = $path !== ''
                ? $this->fileDataUri($this->paths->public.'/assets/'.ltrim($path, '/'), self::IMAGE_MIMES, 5242880)
                : null;
        }

        return $this->backgroundCache;
    }

    public function forDiscussion(Discussion $discussion, string $locale): CardData
    {
        $discussion->loadMissing(['user', 'firstPost']);

        if ($this->extensions->isEnabled('flarum-tags')) {
            $discussion->loadMissing('tags');
        }

        $firstPost = $discussion->firstPost;
        $content = '';

        if ($firstPost instanceof CommentPost) {
            try {
                $content = $firstPost->formatContent();
            } catch (\Throwable) {
                $content = (string) $firstPost->content;
            }
        }

        $plain = $this->plainText($content);
        $replies = max(0, (int) $discussion->comment_count - 1);
        $user = $discussion->user;

        return new CardData(
            discussionId: (int) $discussion->id,
            title: (string) $discussion->title,
            excerpt: $plain !== '' ? $this->excerpt($plain) : null,
            authorName: $user?->display_name,
            avatarDataUri: $this->avatarDataUri($user),
            tagName: $discussion->relationLoaded('tags') ? $discussion->tags->first()?->name : null,
            createdAt: $discussion->created_at?->format('Y-m-d') ?? '',
            displayDate: $this->formatDate($discussion->created_at, $locale),
            replyCount: $replies,
            lastReplyAt: $replies > 0 ? $discussion->last_posted_at?->format('Y-m-d H:i') : null,
            lastReplyDisplay: $replies > 0 ? $this->formatDate($discussion->last_posted_at, $locale) : null,
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            siteTitle: $this->brandFor($locale),
            tagline: $this->settings->taglineFor($locale) ?: null,
            colors: $this->settings->colors(),
            logoDataUri: $this->logoDataUri(),
            backgroundDataUri: $this->backgroundDataUri(),
            elements: $this->settings->elements(),
        );
    }

    public function altText(CardData $data): string
    {
        return $data->title.' — '.$data->siteTitle;
    }

    private function avatarDataUri(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $raw = (string) ($user->getRawOriginal('avatar_url') ?? '');

        if ($raw === '' || str_contains($raw, '://') || str_contains($raw, '..')) {
            return null;
        }

        return $this->fileDataUri($this->paths->public.'/assets/avatars/'.ltrim($raw, '/'), self::RASTER_MIMES, 1048576);
    }

    private function fileDataUri(string $file, array $mimes, int $maxBytes): ?string
    {
        if (str_contains($file, '..') || ! is_file($file) || filesize($file) > $maxBytes) {
            return null;
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file);

        if (! in_array($mime, $mimes, true)) {
            return null;
        }

        $bytes = file_get_contents($file);

        return $bytes === false ? null : 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function excerpt(string $text): string
    {
        if (mb_strlen($text) <= 180) {
            return $text;
        }

        $cut = mb_substr($text, 0, 180);
        $space = mb_strrpos($cut, ' ');

        if ($space !== false && $space > 120) {
            $cut = mb_substr($cut, 0, $space);
        }

        return $cut.'…';
    }

    private function formatDate(?CarbonInterface $date, string $locale): string
    {
        if ($date === null) {
            return '';
        }

        if ($locale === 'ar') {
            $month = self::ARABIC_MONTHS[(int) $date->format('n')] ?? $date->format('F');

            return $date->format('j').' '.$month.' '.$date->format('Y');
        }

        return $date->format('j F Y');
    }
}
