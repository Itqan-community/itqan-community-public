<?php

namespace Itqan\Discussions\Vote;

use DateTimeInterface;

/**
 * The "hot" ordering.
 *
 * Reddit's ranking, which answers a question a plain score cannot: how does a
 * post with 40 votes from last year compare with one that has 8 from this
 * morning? The score is put on a log scale, so the difference between 1 and 10
 * votes counts as much as between 10 and 100, and age is added as a straight
 * term — which makes the whole thing drift upward over time and lets new
 * material surface without the old having to lose anything.
 *
 * @see https://github.com/reddit-archive/reddit/blob/master/r2/r2/lib/db/_sorts.pyx
 */
class Ranking
{
    /**
     * Reddit's epoch is arbitrary; any fixed point works, and a recent one
     * keeps the numbers small enough to read.
     */
    private const EPOCH = 1704067200; // 2024-01-01T00:00:00Z

    /**
     * 45000 seconds — 12.5 hours — is the age that counts for as much as one
     * order of magnitude of score.
     */
    private const AGE_DIVISOR = 45000;

    public static function hotness(int $score, ?DateTimeInterface $createdAt): float
    {
        $seconds = ($createdAt ? $createdAt->getTimestamp() : time()) - self::EPOCH;

        $order = log10(max(abs($score), 1));

        $sign = $score <=> 0;

        return round($sign * $order + $seconds / self::AGE_DIVISOR, 7);
    }
}
