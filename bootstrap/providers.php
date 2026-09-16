<?php

use App\Modules\Authentication\Providers\AuthenticationServiceProvider;
use App\Modules\Baranggay_ABYIP\Providers\Baranggay_ABYIPServiceProvider;
use App\Modules\Communications\Providers\CommunicationsServiceProvider;
use App\Modules\Dashboard\Providers\DashboardServiceProvider;
use App\Modules\Homepage\Providers\HomepageServiceProvider;
use App\Modules\KKProfiling\Providers\KKProfilingServiceProvider;
use App\Modules\Layout\Providers\LayoutServiceProvider;
use App\Modules\Notifications\Providers\NotificationsServiceProvider;
use App\Modules\Profile\Providers\ProfileServiceProvider;
use App\Modules\Program_Accomplishments\Providers\ProgramAccomplishmentsServiceProvider;
use App\Modules\Programs\Providers\ProgramServiceProvider;
use App\Modules\Tutorial_Guide\Providers\TutorialGuideServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    AuthenticationServiceProvider::class,
    ProfileServiceProvider::class,
    DashboardServiceProvider::class,
    HomepageServiceProvider::class,
    KKProfilingServiceProvider::class,
    ProgramServiceProvider::class,
    LayoutServiceProvider::class,
    NotificationsServiceProvider::class,
    CommunicationsServiceProvider::class,
    ProgramAccomplishmentsServiceProvider::class,
    Baranggay_ABYIPServiceProvider::class,
    TutorialGuideServiceProvider::class,
];
