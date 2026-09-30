<?php

use Flarum\Extend;
use s9e\TextFormatter\Configurator;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Formatter)
        ->configure(function (Configurator $config) {
            if (isset($config->tags['GOOGLEDRIVE'])) {
                $config->tags['GOOGLEDRIVE']->template =
                    '<div class="itqan-gdrive-card">'
                    . '<div class="itqan-gdrive-icon"><i class="fab fa-google-drive"></i></div>'
                    . '<div class="itqan-gdrive-info">'
                    . '<span class="itqan-gdrive-title">ملف جوجل درايف (Google Drive)</span>'
                    . '<a href="https://drive.google.com/file/d/{@id}/view" target="_blank" rel="noopener noreferrer" class="itqan-gdrive-link"><i class="fas fa-external-link-alt"></i> فتح الملف</a>'
                    . '</div>'
                    . '</div>';
            }
        }),
];
