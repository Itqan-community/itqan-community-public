<?php

// Run: php packages/flarum-replies-extension/tests/ranking_test.php
// Pure math, no DB, no Flarum boot. Exits non-zero on first failed assertion.

error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__.'/../src/Vote/Ranking.php';

use Mtareq\NestedReplies\Vote\Ranking;

function check(bool $ok, string $name): void
{
    echo ($ok ? 'PASS' : 'FAIL')." $name\n";
    if (! $ok) {
        exit(1);
    }
}

$epoch = 1704067200; // must match Ranking::EPOCH (2024-01-01T00:00:00Z)
$atOne = new DateTimeImmutable('@'.($epoch + 45000));  // age term == +1
$atThree = new DateTimeImmutable('@'.($epoch + 135000)); // age term == +3

check(Ranking::hotness(10, $atOne) === 2.0, 'hotness_score10_age1_is2: sign(+1)*log10(10)=1, +1');
check(Ranking::hotness(-100, $atOne) === -1.0, 'hotness_scoreNeg100_age1_isMinus1: sign(-1)*log10(100)=-2, +1');
check(Ranking::hotness(0, new DateTimeImmutable('@'.$epoch)) === 0.0, 'hotness_zeroScore_atEpoch_is0: sign(0)*log10(1)=0, age 0');
check(abs(Ranking::hotness(5, $atThree) - (round(log10(5), 7) + 3)) < 1e-6, 'hotness_score5_age3_isLogPlus3: sign(1)*log10(5), +3');
// 3e-5 tolerance: time() is sampled twice (here and inside hotness); a clock
// tick between them shifts the age term by 1/45000 ~= 2.2e-5.
check(is_float(Ranking::hotness(5, null)) && abs(Ranking::hotness(5, null) - round(log10(5) + (time() - $epoch) / 45000, 7)) < 3e-5, 'hotness_nullDate_usesNow');

echo "ALL PASS\n";
