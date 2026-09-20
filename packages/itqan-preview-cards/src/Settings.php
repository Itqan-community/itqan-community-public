<?php

namespace Itqan\PreviewCards;

use Flarum\Settings\SettingsRepositoryInterface;

class Settings
{
    public function __construct(protected SettingsRepositoryInterface $settings)
    {
    }

    public function enabled(): bool
    {
        return (bool) $this->settings->get('itqan-preview-cards.enabled', true);
    }

    public function languagePolicy(): string
    {
        $value = (string) $this->settings->get('itqan-preview-cards.language', 'auto');

        return in_array($value, ['auto', 'ar', 'en'], true) ? $value : 'auto';
    }

    public function renderConcurrency(): int
    {
        return max(1, (int) $this->settings->get('itqan-preview-cards.render_concurrency', 2));
    }

    public function provisionalTtl(): int
    {
        return max(0, (int) $this->settings->get('itqan-preview-cards.provisional_ttl', 60));
    }

    public function pruneDays(): int
    {
        return max(1, (int) $this->settings->get('itqan-preview-cards.prune_days', 30));
    }

    public function defaultLocale(): string
    {
        $locale = (string) $this->settings->get('default_locale', 'en');

        return $locale !== '' ? $locale : 'en';
    }

    public function fallbackLocale(): string
    {
        $policy = $this->languagePolicy();

        if ($policy !== 'auto') {
            return $policy;
        }

        $short = strtolower(substr($this->defaultLocale(), 0, 2));

        return $short === 'ar' ? 'ar' : 'en';
    }

    public function showLogo(): bool
    {
        return (bool) $this->settings->get('itqan-preview-cards.show_logo', true);
    }

    public function showBrand(): bool
    {
        return (bool) $this->settings->get('itqan-preview-cards.show_brand', true);
    }

    public function showTagline(): bool
    {
        return (bool) $this->settings->get('itqan-preview-cards.show_tagline', true);
    }

    public function taglineFor(string $locale): string
    {
        $primary = trim((string) $this->settings->get('itqan-preview-cards.tagline_'.$locale, ''));

        if ($primary !== '') {
            return $primary;
        }

        $other = $locale === 'ar' ? 'en' : 'ar';

        return trim((string) $this->settings->get('itqan-preview-cards.tagline_'.$other, ''));
    }

    public function showTag(): bool
    {
        return (bool) $this->settings->get('itqan-preview-cards.show_tag', true);
    }

    public function showExcerpt(): bool
    {
        return (bool) $this->settings->get('itqan-preview-cards.show_excerpt', true);
    }

    public function showAuthor(): bool
    {
        return (bool) $this->settings->get('itqan-preview-cards.show_author', true);
    }

    public function showAvatar(): bool
    {
        return (bool) $this->settings->get('itqan-preview-cards.show_avatar', true);
    }

    public function showDate(): bool
    {
        return (bool) $this->settings->get('itqan-preview-cards.show_date', true);
    }

    public function showReplies(): bool
    {
        return (bool) $this->settings->get('itqan-preview-cards.show_replies', true);
    }

    public function showLastReply(): bool
    {
        return (bool) $this->settings->get('itqan-preview-cards.show_last_reply', true);
    }

    public function backgroundPath(): string
    {
        return (string) $this->settings->get('itqan-preview-cards.background_path', '');
    }

    public function cardLogoPath(): string
    {
        return (string) $this->settings->get('itqan-preview-cards.logo_path', '');
    }

    public function colors(): array
    {
        return [
            'background' => $this->color('background', '#004638'),
            'accent' => $this->accentColor(),
            'title' => $this->color('title', '#ffffff'),
            'text' => $this->color('text', '#cbd7d4'),
            'meta' => $this->color('meta', '#d7e1de'),
            'tag' => $this->color('tag', '#c2d2ce'),
        ];
    }

    public function elements(): array
    {
        return [
            'logo' => $this->showLogo(),
            'brand' => $this->showBrand(),
            'tagline' => $this->showTagline(),
            'tag' => $this->showTag(),
            'excerpt' => $this->showExcerpt(),
            'author' => $this->showAuthor(),
            'avatar' => $this->showAvatar(),
            'date' => $this->showDate(),
            'replies' => $this->showReplies(),
            'last_reply' => $this->showLastReply(),
        ];
    }

    private function color(string $key, string $default): string
    {
        $value = (string) $this->settings->get('itqan-preview-cards.color_'.$key, $default);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $default;
    }

    private function accentColor(): string
    {
        $value = (string) $this->settings->get('itqan-preview-cards.color_accent', '');

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return strtolower($value);
        }

        $theme = (string) $this->settings->get('theme_primary_color', '');

        return preg_match('/^#[0-9a-fA-F]{6}$/', $theme) ? strtolower($theme) : '#00ad83';
    }
}
