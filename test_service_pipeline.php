<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

@set_time_limit(600);

$front = 'C:\\Users\\Administrator\\Documents\\SK_OnePortal_Santa_Cruz\\Kabataan\\storage\\app\\private\\kk_wizard_pending\\3de1f674-015a-481c-a8d6-1284bd3088c3\\documents\\school_id_front_20260629061335_2ahhy6.jpg';
$back = 'C:\\Users\\Administrator\\Documents\\SK_OnePortal_Santa_Cruz\\Kabataan\\storage\\app\\private\\kk_wizard_pending\\3de1f674-015a-481c-a8d6-1284bd3088c3\\documents\\school_id_back_20260629061335_3tkasf.jpg';

$fields = [
    'first_name' => 'PAULA JUANA',
    'middle_name' => 'AGRAVANTE',
    'last_name' => 'TALABIS',
    'suffix' => 'None',
    'birthday' => '2005-06-29',
    'purok_zone' => 'Sitio 1 Talarey',
    'registration_barangay' => 'Palasan',
];

$sw = microtime(true);
$result = app(\App\Services\SchoolIdPipelineService::class)->validate(12, $fields, $front, $back);
$elapsed = round(microtime(true) - $sw, 1);

echo "Elapsed: {$elapsed}s\n";
if ($result === null) {
    echo "Result: null\n";
    exit(1);
}

echo 'success: '.json_encode($result['success'] ?? null)."\n";
echo 'front_len: '.strlen((string) ($result['ocr']['front']['full_text'] ?? ''))."\n";
echo 'back_len: '.strlen((string) ($result['ocr']['back']['full_text'] ?? ''))."\n";
echo 'decision: '.($result['decision'] ?? 'n/a')."\n";
