<?php

namespace IanM\Translate\Job;

use Flarum\Database\AbstractModel;
use Flarum\Queue\AbstractJob;
use Flarum\Settings\SettingsRepositoryInterface;
use IanM\Translate\TranslationProviders\TranslationProviderInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractTranslationUpdateJob extends AbstractJob
{
    protected SettingsRepositoryInterface $settings;
    protected TranslationProviderInterface $translator;
    protected LoggerInterface $logger;
    
    public function __construct(public AbstractModel $model)
    {
    }

    public function handle(SettingsRepositoryInterface $settings, TranslationProviderInterface $translator, LoggerInterface $logger): void
    {
        if ((bool) !$settings->get('ianm-translate.update-translations-after-save')) {
            return;
        }

        $this->settings = $settings;
        $this->translator = $translator;
        $this->logger = $logger;

        $this->processJob();
    }

    abstract protected function processJob(): void;
}
