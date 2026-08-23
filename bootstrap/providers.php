<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Modules\Authentication\Providers\AuthenticationServiceProvider::class,
    App\Modules\Profile\Providers\ProfileServiceProvider::class,
    App\Modules\Dashboard\Providers\DashboardServiceProvider::class,
    App\Modules\Homepage\Providers\HomepageServiceProvider::class,
    App\Modules\KKProfiling\Providers\KKProfilingServiceProvider::class,
    App\Modules\Programs\Providers\ProgramServiceProvider::class,
    App\Modules\Layout\Providers\LayoutServiceProvider::class,
    App\Modules\Notifications\Providers\NotificationsServiceProvider::class,
    App\Modules\Program_Accomplishments\Providers\ProgramAccomplishmentsServiceProvider::class,
    App\Modules\Baranggay_ABYIP\Providers\Baranggay_ABYIPServiceProvider::class,
];
