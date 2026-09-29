<?php
// Helper: print a summary of the harness output for reporting.
$d = json_decode(file_get_contents('/var/www/html/storage/notif-harness.json'), true);

echo "=== groupMentioned|ar|email_subject ===\n";
echo $d["groupMentioned|ar|email_subject"] . "\n";
$hasCorrect = strpos($d["groupMentioned|ar|email_subject"], 'مجموعة') !== false;
$hasTypo = strpos($d["groupMentioned|ar|email_subject"], 'محموعة') !== false;
echo "Has مجموعة? " . ($hasCorrect ? 'yes' : 'NO') . "\n";
echo "Has محموعة typo? " . ($hasTypo ? 'STILL HAS TYPO' : 'no (fixed)') . "\n";

echo "\n=== Type list ===\n";
$types = [];
foreach (array_keys($d) as $k) { $types[explode('|', $k)[0]] = true; }
foreach (array_keys($types) as $t) { echo "  - $t\n"; }
echo "Total types: " . count($types) . "\n";

echo "\n=== discussionReplied AR ===\n";
echo "Subject: " . $d["discussionReplied|ar|email_subject"] . "\n";
echo "Body: " . substr($d["discussionReplied|ar|email_body"], 0, 400) . "\n";

echo "\n=== commentReplied AR ===\n";
echo "Subject: " . $d["commentReplied|ar|email_subject"] . "\n";
echo "Body: " . substr($d["commentReplied|ar|email_body"], 0, 400) . "\n";

echo "\n=== newDiscussionByUser AR body (first 400) ===\n";
echo substr($d["newDiscussionByUser|ar|email_body"], 0, 400) . "\n";

echo "\n=== newPostByUser AR body (first 400) ===\n";
echo substr($d["newPostByUser|ar|email_body"], 0, 400) . "\n";

echo "\n=== newFollower AR body (first 400) ===\n";
echo substr($d["newFollower|ar|email_body"], 0, 400) . "\n";