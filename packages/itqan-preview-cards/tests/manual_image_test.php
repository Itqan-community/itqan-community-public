<?php

// Run: php packages/itqan-preview-cards/tests/manual_image_test.php
// Exits non-zero if any case fails. No Flarum boot, no database.

error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__.'/../../../vendor/autoload.php';

use Itqan\PreviewCards\Seo\DiscussionCardDriver;

$post = 'http://x/post.png';

$cases = [
    // [image, source, auto update on?, image v17 detects in the first post, expected is manual?]
    [null, null, true, null, false],
    ['', 'custom', true, null, false],
    // Detected by v17 from the post: never manual while auto update is on.
    [$post, null, true, null, false],
    [$post, '', true, null, false],
    [$post, 'auto', true, null, false],
    [$post, 'AUTO', true, null, false],
    // An explicit source always wins.
    [$post, 'custom', true, null, true],
    [$post, 'fof-upload', true, null, true],
    [$post, 'fof-upload', false, $post, true],
    // Frozen row (auto update off) whose image is still the post image: not manual.
    [$post, null, false, $post, false],
    [$post, 'auto', false, $post, false],
    // Frozen row with a URL the moderator typed (v17 stores it with a NULL source).
    ['http://x/typed.png', null, false, $post, true],
    ['http://x/typed.png', 'auto', false, $post, true],
    ['http://x/typed.png', null, false, null, true],
    ['', null, false, $post, false],
];

$failed = 0;

function label(?string $value): string
{
    return $value === null ? 'null' : "'".$value."'";
}

foreach ($cases as [$image, $source, $autoUpdate, $detected, $expected]) {
    $actual = DiscussionCardDriver::isManualImage($image, $source, $autoUpdate, $detected);
    $ok = $actual === $expected;

    echo ($ok ? 'PASS' : 'FAIL').' isManualImage('.label($image).', '.label($source)
        .', '.($autoUpdate ? 'auto-update on' : 'auto-update off').', detected '.label($detected).')'
        .' => '.($actual ? 'true' : 'false')
        .' (expected '.($expected ? 'true' : 'false').")\n";

    if (! $ok) {
        $failed++;
    }
}

if ($failed > 0) {
    echo "\n$failed of ".count($cases)." cases FAILED\n";
    exit(1);
}

echo "\nALL PASS\n";
