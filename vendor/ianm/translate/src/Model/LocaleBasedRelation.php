<?php

namespace IanM\Translate\Model;

use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Flarum\Locale\Translator;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LocaleBasedRelation
{
    public function __construct(protected Translator $translator)
    {
    }
    
    public function __invoke(AbstractModel $model): HasOne
    {
        return $model->hasOne($this->getTranslationModel($model), $this->getForeignKey($model), 'id')
            ->where('language', $this->translator->getLocale());
    }

    protected function getTranslationModel(AbstractModel $model): string
    {
        switch($model) {
            case $model instanceof Discussion:
                return DiscussionTranslation::class;
            default:
                return PostTranslation::class;
        }
    }

    protected function getForeignKey(AbstractModel $model): string
    {
        switch($model) {
            case $model instanceof Discussion:
                return 'discussion_id';
            default:
                return 'post_id';
        }
    }
}
