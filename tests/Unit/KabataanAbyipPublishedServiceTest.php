<?php

namespace Tests\Unit;

use App\Models\Abyip;
use App\Models\Barangay;
use App\Modules\Baranggay_ABYIP\Services\Baranggay_ABYIPService;
use Tests\TestCase;

class KabataanAbyipPublishedServiceTest extends TestCase
{
    public function test_abyip_model_constants_and_casts(): void
    {
        $abyip = new Abyip([
            'fiscal_year' => 2026,
            'current_version' => 1,
            'status' => Abyip::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $this->assertSame(2026, $abyip->fiscal_year);
        $this->assertSame(1, $abyip->current_version);
        $this->assertSame('published', $abyip->status);
        $this->assertSame('published', Abyip::STATUS_PUBLISHED);
        $this->assertSame('approved', Abyip::STATUS_APPROVED);
    }

    public function test_abyip_relationships_defined(): void
    {
        $abyip = new Abyip();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $abyip->barangay());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $abyip->versions());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasOne::class, $abyip->publishedVersion());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $abyip->publisher());
    }
}
