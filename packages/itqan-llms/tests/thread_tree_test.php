<?php

// Run: php packages/itqan-llms/tests/thread_tree_test.php
// Exits non-zero on the first failed assertion. No Flarum boot, no database.
//
// The property under test is the one a chronological export gets wrong: a
// reply written after unrelated comments must still be emitted directly beneath
// the comment it answers, so a model reading the thread can tell who was
// replying to whom.

error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__.'/../../../vendor/autoload.php';

use Itqan\Llms\Markdown\ThreadTree;

/**
 * Stands in for a Flarum Post. ThreadTree only reads id, number and
 * parent_id, so a stub keeps this test free of a database.
 */
class FakePost
{
    public $id;
    public $number;
    public $parent_id;

    public function __construct(int $id, int $number, ?int $parentId = null)
    {
        $this->id = $id;
        $this->number = $number;
        $this->parent_id = $parentId;
    }
}

/**
 * Render the tree as "number:depth" pairs, the order and shape a document
 * would be written in.
 */
function shape(array $posts): array
{
    $tree = new ThreadTree;
    $out = [];

    foreach ($tree->flatten($tree->build($posts)) as $node) {
        $out[] = $node->post->number.':'.$node->depth;
    }

    return $out;
}

$failures = 0;

function check(string $name, $expected, $actual): void
{
    global $failures;

    if ($expected === $actual) {
        echo "PASS $name\n";

        return;
    }

    $failures++;
    echo "FAIL $name\n";
    echo '  expected: '.json_encode($expected)."\n";
    echo '  actual:   '.json_encode($actual)."\n";
}

// --- the reason this class exists ---------------------------------------------
//
// Post #3 answers #2, and post #7 answers #3, but both were written after
// unrelated comments #5 and #6. Reading in `number` order gives 1,2,3,4,5,6,7
// with the conversation shredded across it.

check(
    'late_reply_stays_under_its_parent',
    ['1:0', '2:0', '3:1', '7:2', '4:1', '6:2', '5:0'],
    shape([
        new FakePost(1, 1),
        new FakePost(2, 2),
        new FakePost(5, 5),
        new FakePost(3, 3, 2),
        new FakePost(4, 4, 2),
        new FakePost(6, 6, 4),
        new FakePost(7, 7, 3),
    ])
);

// --- ordinary shapes -----------------------------------------------------------

check('opening_post_alone', ['1:0'], shape([new FakePost(1, 1)]));
check('flat_thread', ['1:0', '2:0', '3:0'], shape([new FakePost(1, 1), new FakePost(2, 2), new FakePost(3, 3)]));
check('no_posts', [], shape([]));

// Siblings must stay chronological even when the input is not.
check(
    'siblings_chronological',
    ['1:0', '2:0', '3:0', '4:0'],
    shape([new FakePost(4, 4), new FakePost(1, 1), new FakePost(3, 3), new FakePost(2, 2)])
);

check(
    'deep_chain',
    ['1:0', '2:1', '3:2', '4:3', '5:4'],
    shape([
        new FakePost(1, 1),
        new FakePost(2, 2, 1),
        new FakePost(3, 3, 2),
        new FakePost(4, 4, 3),
        new FakePost(5, 5, 4),
    ])
);

// --- data that would otherwise lose comments ----------------------------------

// The parent is not in the visible set, e.g. it is hidden from this reader.
// Dropping the reply would silently remove a comment from the export.
check(
    'orphan_becomes_root_not_dropped',
    ['1:0', '2:0'],
    shape([new FakePost(1, 1), new FakePost(2, 2, 99)])
);

check(
    'self_parent_becomes_root',
    ['1:0', '2:0'],
    shape([new FakePost(1, 1), new FakePost(2, 2, 2)])
);

// parent_id has no foreign key, so two posts can point at each other. Both
// must still appear: a cycle that swallowed them would lose content, and one
// that nested a post under itself would recurse until the process died.
check(
    'two_post_cycle_keeps_both',
    ['1:0', '2:0', '3:0'],
    shape([new FakePost(1, 1), new FakePost(2, 2, 3), new FakePost(3, 3, 2)])
);

check(
    'three_post_cycle_keeps_all',
    ['1:0', '2:0', '3:0', '4:0'],
    shape([new FakePost(1, 1), new FakePost(2, 2, 3), new FakePost(3, 3, 4), new FakePost(4, 4, 2)])
);

// A long cycle must terminate rather than blow the stack or run forever.
$cycle = [new FakePost(1, 1)];

for ($i = 2; $i <= 200; $i++) {
    $cycle[] = new FakePost($i, $i, $i + 1);
}

$cycle[] = new FakePost(201, 201, 2);

$start = microtime(true);
$shape = shape($cycle);
$elapsed = microtime(true) - $start;

check('long_cycle_keeps_every_post', 201, count($shape));

if ($elapsed > 5.0) {
    $failures++;
    echo "FAIL long_cycle_terminates: took {$elapsed}s\n";
} else {
    echo "PASS long_cycle_terminates: ".round($elapsed, 3)."s\n";
}

// --- a chain deeper than the guard --------------------------------------------

$deep = [new FakePost(1, 1)];
$parent = 1;

for ($i = 2; $i <= 300; $i++) {
    $deep[] = new FakePost($i, $i, $parent);
    $parent = $i;
}

$shape = shape($deep);
check('very_deep_chain_keeps_every_post', 300, count($shape));

echo $failures === 0 ? "\nALL PASS\n" : "\n$failures FAILED\n";

exit($failures === 0 ? 0 : 1);
