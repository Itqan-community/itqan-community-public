<?php
// Flags fof_linguist_strings rows whose EN value contains Arabic (UI contamination).
require '/var/www/html/vendor/autoload.php';
$site = require '/var/www/html/site.php';
$app = $site->bootApp();
$c = method_exists($app, 'getContainer') ? $app->getContainer() : $app;
$db = $c->make(Illuminate\Database\ConnectionInterface::class);
$bad = 0;
foreach ($db->table('fof_linguist_strings')->where('locale', 'en')->get(['key', 'value']) as $r) {
    if (preg_match('/[\x{0600}-\x{06FF}]/u', (string) $r->value)) {
        echo " - {$r->key}\n";
        $bad++;
    }
}
echo $bad ? "EN CONTAMINATION: $bad\n" : "clean\n";
exit($bad ? 1 : 0);