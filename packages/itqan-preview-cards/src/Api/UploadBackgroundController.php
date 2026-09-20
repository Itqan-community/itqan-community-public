<?php

namespace Itqan\PreviewCards\Api;

class UploadBackgroundController extends UploadAssetController
{
    protected string $settingKey = 'itqan-preview-cards.background_path';
    protected string $field = 'background';
    protected string $prefix = 'background';
}
