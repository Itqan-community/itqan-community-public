<?php

namespace Itqan\PreviewCards\Render;

interface CardRenderer
{
    public function render(string $html, int $width, int $height): string;
}
