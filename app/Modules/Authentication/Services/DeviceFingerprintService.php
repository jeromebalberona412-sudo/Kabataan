<?php

namespace App\Modules\Authentication\Services;

use Illuminate\Http\Request;

class DeviceFingerprintService
{
    public function fingerprint(Request $request): string
    {
        $components = [
            $request->userAgent() ?? '',
            $request->header('Accept-Language') ?? '',
            $request->header('Accept-Encoding') ?? '',
        ];

        return hash('sha256', implode('|', $components));
    }
}
