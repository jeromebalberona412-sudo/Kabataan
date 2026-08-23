<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Process;

$img = 'C:\\Users\\Administrator\\Documents\\SK_OnePortal_Santa_Cruz\\Kabataan\\storage\\app\\private\\kk_wizard_pending\\3de1f674-015a-481c-a8d6-1284bd3088c3\\documents\\school_id_front_20260629061335_2ahhy6.jpg';

$sw = microtime(true);
$result = Process::timeout(600)->run([
    config('ocr.python'),
    config('ocr.script'),
    $img,
]);
$elapsed = round(microtime(true) - $sw, 1);

echo "Elapsed: {$elapsed}s\n";
echo substr($result->output(), 0, 500)."\n";
if ($stderr = trim($result->errorOutput())) {
    echo "STDERR: {$stderr}\n";
}
