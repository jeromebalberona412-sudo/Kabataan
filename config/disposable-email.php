<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JSON Source URLs
    |--------------------------------------------------------------------------
    |
    | Refresh the bundled list with: php artisan disposable:update
    | The fetched list is written to storage/framework/disposable_domains.json
    | and is what App\Rules\ValidEmailAddress checks against.
    |
    */

    'sources' => [
        'https://cdn.jsdelivr.net/gh/disposable/disposable-email-domains@master/domains.json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fetch class
    |--------------------------------------------------------------------------
    */

    'fetcher' => \Propaganistas\LaravelDisposableEmail\Fetcher\DefaultFetcher::class,

    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | Must be writable by the web server so `disposable:update` can refresh it.
    | This file is required at runtime; without it nothing is treated as
    | disposable. Keep it out of the repository and provision it on deploy.
    |
    */

    'storage' => storage_path('framework/disposable_domains.json'),

    /*
    |--------------------------------------------------------------------------
    | Whitelist Configuration
    |--------------------------------------------------------------------------
    |
    | Domains removed from the disposable list, e.g. "mydomain.com".
    |
    */

    'whitelist' => [],

    /*
    |--------------------------------------------------------------------------
    | Include Subdomains
    |--------------------------------------------------------------------------
    |
    | Treat any subdomain of a listed disposable domain as disposable too, so
    | variants such as "temp.mailinator.com" cannot bypass the block.
    |
    */

    'include_subdomains' => true,

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | The list is cached so validation stays a fast local lookup instead of
    | re-reading the JSON on every request. Run `php artisan optimize:clear`
    | (or disposable:update, which flushes it) after refreshing the list.
    |
    */

    'cache' => [
        'enabled' => true,
        'store' => 'default',
        'key' => 'disposable_email:domains',
    ],

];
