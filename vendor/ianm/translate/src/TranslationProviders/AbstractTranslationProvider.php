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
            $currentLocale = $this->manager->getLocale();
            $baseLocale = strtolower(explode('-', $currentLocale)[0]);

            if ($baseLocale === 'ar') {
                return ['ar', 'en'];
            }

            return ['en', 'ar'];
        } catch (Throwable $e) {
            resolve('log')->error("[ianm-translate] {$this->name()} failed to get supported languages: {$e->getMessage()}");
            return ['en', 'ar'];
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
        $all = [];
        foreach ($locales as $code => $name) {
            $langCode = is_string($code) ? $code : $name;
            $baseLang = strtolower(explode('-', $langCode)[0]);
            if (in_array($langCode, $providerLangs) || in_array($baseLang, $providerLangs)) {
                $all[] = $langCode;
            }
        }

        $all = array_values(array_unique($all));

        // If the current locale is in provider languages, move it to the front
        if (in_array($currentLocale, $all)) {
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
        $targetLang = strtolower(explode('-', $toLanguage)[0]);

        if (!$post->detected_lang) {
            $detected = $this->identifyLanguage($post);
            if ($detected && $detected !== 'unknown') {
                $post->detected_lang = $detected;
                $post->save();
            }
        }

        $cached = $this->getCachedTranslation($post, $targetLang);
        if (!$cached && $targetLang !== $toLanguage) {
            $cached = $this->getCachedTranslation($post, $toLanguage);
        }

        // Force re-translation if cached entry is empty, invalid, or empty XML (<r></r>, <t></t>, etc)
        if ($cached) {
            $raw = trim((string) $cached->content);
            $cleanText = trim(strip_tags($raw));
            if ($raw === '' || $raw === '<r></r>' || $raw === '<r/>' || $raw === '<t></t>' || $raw === '<t/>' || $cleanText === '') {
                $cached->delete();
                $cached = null;
                $force = true;
            }
        }

        if (!$cached || $force) {
            try {
                return $this->performTranslation($post, $targetLang, $user);
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
        $targetLang = strtolower(explode('-', $toLanguage)[0]);

        if (!$discussion->detected_lang) {
            $detected = $this->identifyTitleLanguage($discussion);
            if ($detected && $detected !== 'unknown') {
                $discussion->detected_lang = $detected;
                $discussion->save();
            }
        }

        $cached = $this->getCachedTitleTranslation($discussion, $targetLang);
        if (!$cached && $targetLang !== $toLanguage) {
            $cached = $this->getCachedTitleTranslation($discussion, $toLanguage);
        }

        // Force re-translation if cached entry is empty
        if ($cached) {
            $raw = trim((string) $cached->translation);
            $cleanText = trim(strip_tags($raw));
            if ($raw === '' || $cleanText === '') {
                $cached->delete();
                $cached = null;
                $force = true;
            }
        }

        if (!$cached || $force) {
            try {
                $translatedTitle = $this->translate($discussion->title, $targetLang);
                if (empty(trim(strip_tags($translatedTitle)))) {
                    throw new Exception("Translation engine returned empty title for discussion {$discussion->id}");
                }
                return DiscussionTranslation::buildOrUpdate($discussion->id, $targetLang, $translatedTitle, $this->name());
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
                throw new ValidationException([$targetLang, $e->getMessage()]);
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
        $unparsed = $formatter->unparse($post->getParsedContentAttribute(), $post);
        $translatedText = $this->translate($unparsed, $toLanguage);

        if (empty(trim($translatedText))) {
            throw new Exception("Translation engine returned empty result for post {$post->id}");
        }

        $translatedXml = $formatter->parse($translatedText, $post, $user);
        return PostTranslation::buildOrUpdate($post->id, $toLanguage, $translatedXml, $this->name());
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
