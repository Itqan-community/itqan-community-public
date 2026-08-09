<?php

namespace IanM\Translate\TranslationProviders;

use Exception;
use Flarum\Discussion\Discussion;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\LocaleManager;
use Flarum\Post\CommentPost;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use IanM\Translate\Model\PostTranslation;
use IanM\Translate\Model\DiscussionTranslation;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;
use Throwable;

abstract class AbstractTranslationProvider
{
    protected $translator;

    protected bool $initialized = false;

    public static string $name;
    public static string $link;

    public function __construct(protected SettingsRepositoryInterface $settings, protected LocaleManager $manager, protected LoggerInterface $logger)
    {
    }

    public function name(): string
    {
        return static::$name;
    }

    public function link(): string
    {
        return static::$link;
    }

    public function enabled(): bool
    {
        return (bool) $this->settings->get('ianm-translate.' . $this->name()) ||
            $this->settings->get('ianm-translate.provider') === $this->name();
    }

    protected function getSetting(string $key)
    {
        return $this->settings->get("ianm-translate.{$this->name()}.{$key}");
    }

    protected function putSetting(string $key, $value)
    {
        return $this->settings->set("ianm-translate.{$this->name()}.{$key}", $value);
    }

    protected function ensureInitialized()
    {
        if (!$this->initialized) {
            $this->initialize();
            $this->initialized = true;
        }
    }

    abstract protected function initialize();

    public function isReady(): bool
    {
        return $this->translator !== null;
    }

    abstract function options(): array;

    abstract protected function translate(string $content, string $toLanguage, string $from = null): string;

    /**
     * Uses unparsed content to identify the language.
     *
     * @param string $content
     * @return string
     */
    abstract protected function identify(string $content): string;

    abstract protected function languages(): array;

    public function supportedLanguages(User $actor): array
    {
        try {
            $providerLangs = $this->languages();
            $browserLang = (bool) $this->settings->get('ianm-translate.bind-browser-language');

            $currentLocale = $this->manager->getLocale();
            $locales = $this->adjustLocales($this->manager->getLocales());

            if ($browserLang) {
                $locales = $this->mergeBrowserLanguages($locales);
            }

            if ($actor->can('translateAnyForumLanguage')) {
                return $this->getAllLanguages($locales, $providerLangs, $currentLocale);
            }

            if ($browserLang) {
                return $locales;
            }

            return [$currentLocale];
        } catch (Throwable $e) {
            resolve('log')->error("[ianm-translate] {$this->name()} failed to get supported languages: {$e->getMessage()}");
            return [];
        }
    }

    private function adjustLocales(array $locales): array
    {
        // Adjust locales for special cases (e.g., 'zh-Hans' -> 'zh')
        // Add more cases as needed
        if (isset($locales['zh-Hans'])) {
            $locales['zh'] = $locales['zh-Hans'];
        }

        return $locales;
    }

    private function mergeBrowserLanguages(array $locales): array
    {
        $browserLangs = $this->getBrowserSupportedLanguages();
        return array_keys(array_unique(array_merge($locales, $browserLangs)));
    }

    private function getAllLanguages(array $locales, array $providerLangs, string $currentLocale): array
    {
        // Filter locales based on provider languages and remove duplicates
        $all = array_unique(array_intersect_key($locales, $providerLangs));

        // If the current locale is in provider languages, move it to the front
        if (in_array($currentLocale, $providerLangs)) {
            // Remove existing instance of current locale
            $all = array_diff($all, [$currentLocale]);
            // Add current locale at the beginning
            array_unshift($all, $currentLocale);
        }

        return $all;
    }

    protected function getBrowserSupportedLanguages(): array
    {
        return array_flip(resolve('ianm-translate.request.langs'));
    }

    public function translatePostContent(CommentPost $post, string $toLanguage, User $user, bool $force = false): PostTranslation
    {
        $cached = $this->getCachedTranslation($post, $toLanguage);

        if (!$cached || $force) {
            try {
                return $this->performTranslation($post, $toLanguage, $user);
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
                throw new ValidationException(['translate' => 'Translation failed. Please try again later.']);
            }
        }

        return $cached;
    }

    private function getCachedTranslation(CommentPost $post, string $toLanguage): ?PostTranslation
    {
        return PostTranslation::where('post_id', $post->id)
            ->where('language', $toLanguage)
            ->where('update_needed', false)
            ->first();
    }

    public function translateDiscussionTitle(Discussion $discussion, string $toLanguage, User $user, bool $force = false): DiscussionTranslation
    {
        $cached = $this->getCachedTitleTranslation($discussion, $toLanguage);

        if (!$cached || $force) {
            try {
                $translatedTitle = $this->translate($discussion->title, $toLanguage);
                return DiscussionTranslation::buildOrUpdate($discussion->id, $toLanguage, $translatedTitle, $this->name());
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
                throw new ValidationException([$toLanguage, $e->getMessage()]);
            }
        }

        return $cached;
    }

    private function getCachedTitleTranslation(Discussion $discussion, string $toLanguage): ?DiscussionTranslation
    {
        return DiscussionTranslation::where('discussion_id', $discussion->id)
            ->where('language', $toLanguage)
            ->where('update_needed', false)
            ->first();
    }

    private function performTranslation(CommentPost $post, string $toLanguage, User $user): PostTranslation
    {
        $formatter = $post->getFormatter();
        $translated = $formatter->parse($this->translate($formatter->unparse($post->getParsedContentAttribute(), $post), $toLanguage), $post, $user);
        return PostTranslation::buildOrUpdate($post->id, $toLanguage, $translated, $this->name());
    }


    public function identifyLanguage(CommentPost $post): ?string
    {
        $content = $post->getFormatter()->unparse($post->getParsedContentAttribute(), $post);

        try {
            return $this->identify($content);
        } catch (Exception $e) {
            return null;
        }
    }

    public function identifyTitleLanguage(Discussion $discussion): ?string
    {
        try {
            return $this->identify($discussion->title);
        } catch (Exception $e) {
            return null;
        }
    }
}
