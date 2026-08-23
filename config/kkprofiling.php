<?php

return [
    /*
    |--------------------------------------------------------------------------
    | KK Profiling finalize time limit (seconds)
    |--------------------------------------------------------------------------
    |
    | Set-password / registration finalize may promote documents and run
    | evaluation. Step 2 OCR results are reused when available; this limit is
    | a safety buffer for legacy drafts that still need one OCR pass.
    |
    */
    'finalize_time_limit' => (int) env('KK_PROFILING_FINALIZE_TIME_LIMIT', 180),
];
