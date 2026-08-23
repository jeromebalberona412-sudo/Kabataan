<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Process;

$payload = [
    'front_image' => 'C:\\Users\\Administrator\\Documents\\SK_OnePortal_Santa_Cruz\\Kabataan\\storage\\app\\private\\kk_wizard_pending\\3de1f674-015a-481c-a8d6-1284bd3088c3\\documents\\school_id_front_20260629061335_2ahhy6.jpg',
    'back_image' => 'C:\\Users\\Administrator\\Documents\\SK_OnePortal_Santa_Cruz\\Kabataan\\storage\\app\\private\\kk_wizard_pending\\3de1f674-015a-481c-a8d6-1284bd3088c3\\documents\\school_id_back_20260629061335_3tkasf.jpg',
    'form' => [
        'first_name' => 'PAULA JUANA',
        'middle_name' => 'AGRAVANTE',
        'last_name' => 'TALABIS',
        'suffix' => 'None',
        'birthday' => '2005-06-29',
        'purok_zone' => 'Sitio 1 Talarey',
        'barangay' => 'Palasan',
        'municipality' => 'Santa Cruz',
        'province' => 'Laguna',
    ],
];

$payloadPath = sys_get_temp_dir().'/sk_upload_test.json';
file_put_contents($payloadPath, json_encode($payload, JSON_UNESCAPED_UNICODE));

$pythonDir = dirname(str_replace('\\', '/', (string) config('ocr.pipeline_script')));

$sw = microtime(true);
$result = Process::timeout(600)->path($pythonDir)->run([
    config('ocr.python'),
    config('ocr.pipeline_script'),
    '--payload',
    $payloadPath,
]);
$elapsed = round(microtime(true) - $sw, 1);

echo "Elapsed: {$elapsed}s\n";
echo 'Exit: '.$result->exitCode()."\n";
$decoded = json_decode($result->output(), true);
if (is_array($decoded)) {
    echo 'success: '.json_encode($decoded['success'] ?? null)."\n";
    echo 'front_len: '.strlen((string) ($decoded['ocr']['front']['full_text'] ?? ''))."\n";
    echo 'back_len: '.strlen((string) ($decoded['ocr']['back']['full_text'] ?? ''))."\n";
    echo 'decision: '.($decoded['decision'] ?? 'n/a')."\n";
} else {
    echo substr($result->output(), 0, 300)."\n";
}
if ($stderr = trim($result->errorOutput())) {
    echo "STDERR: ".substr($stderr, 0, 500)."\n";
}
