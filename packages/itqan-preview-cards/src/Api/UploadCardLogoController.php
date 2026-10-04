<?php

namespace Itqan\PreviewCards\Api;

class UploadCardLogoController extends UploadAssetController
{
    protected const MAX_BYTES = 2097152;

    protected string $settingKey = 'itqan-preview-cards.logo_path';
    protected string $field = 'logo';
    protected string $prefix = 'logo';
}
