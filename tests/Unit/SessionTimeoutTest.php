<?php

use App\Http\Middleware\SessionTimeout;
use App\Models\User;
use App\Modules\Authentication\Services\TrustedDeviceService;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('session.timeout', 1);
    config()->set('session.timeout_warning_minutes', 0);
});

function kabataanMakeSessionStore(array $data = []): Store
{
    $session = new Store('testing', new ArraySessionHandler(120));
    $session->start();

    foreach ($data as $key => $value) {
        $session->put($key, $value);
    }

    return $session;
}

function kabataanMakeAuthenticatedRequest(
    string $uri = '/dashboard',
    string $method = 'GET',
    array $sessionData = [],
    array $headers = [],
): Request {
    $request = Request::create($uri, $method, [], [], [], $headers);
    $request->setLaravelSession(kabataanMakeSessionStore($sessionData));

    $user = new User;
    $user->forceFill([
        'id' => 88001,
        'email' => 'kabataan-timeout@example.com',
        'role' => 'kabataan',
        'status' => User::STATUS_ACTIVE,
    ]);
    $user->syncOriginal();
    $request->setUserResolver(fn () => $user);

    return $request;
}

it('passes through guest requests without touching activity', function () {
    Auth::shouldReceive('check')->once()->andReturn(false);

    $request = Request::create('/sign-in', 'GET');
    $request->setLaravelSession(kabataanMakeSessionStore());

    $response = (new SessionTimeout)->handle($request, fn () => response('public-ok'));

    expect($response->getContent())->toBe('public-ok')
        ->and($request->session()->has(SessionTimeout::SESSION_KEY))->toBeFalse();
});

it('seeds and refreshes last activity for active authenticated requests', function () {
    Auth::shouldReceive('check')->twice()->andReturn(true);

    $middleware = new SessionTimeout;

    $first = kabataanMakeAuthenticatedRequest('/dashboard');
    $middleware->handle($first, fn () => response('ok'));
    expect((int) $first->session()->get(SessionTimeout::SESSION_KEY))->toBeGreaterThan(0);

    $initial = now()->subSeconds(20)->getTimestamp();
    $second = kabataanMakeAuthenticatedRequest('/dashboard', 'GET', [
        SessionTimeout::SESSION_KEY => $initial,
    ]);
    $middleware->handle($second, fn () => response('ok'));

    expect((int) $second->session()->get(SessionTimeout::SESSION_KEY))->toBeGreaterThan($initial);
});

it('logs out and redirects to sign-in after inactivity timeout', function () {
    Auth::shouldReceive('check')->once()->andReturn(true);
    Auth::shouldReceive('logout')->once();

    $trusted = Mockery::mock(TrustedDeviceService::class);
    $trusted->shouldReceive('revokeCurrentDevice')->once();
    app()->instance(TrustedDeviceService::class, $trusted);

    $request = kabataanMakeAuthenticatedRequest('/dashboard', 'GET', [
        SessionTimeout::SESSION_KEY => now()->subMinutes(5)->getTimestamp(),
    ]);

    $response = (new SessionTimeout)->handle($request, fn () => response('should-not-run'));

    expect($response->isRedirect())->toBeTrue()
        ->and($response->headers->get('Location'))->toContain('/sign-in');
});

it('returns json session_expired for ajax requests after timeout', function () {
    Auth::shouldReceive('check')->once()->andReturn(true);
    Auth::shouldReceive('logout')->once();

    $trusted = Mockery::mock(TrustedDeviceService::class);
    $trusted->shouldReceive('revokeCurrentDevice')->once();
    app()->instance(TrustedDeviceService::class, $trusted);

    $request = kabataanMakeAuthenticatedRequest(
        '/session/continue',
        'POST',
        [SessionTimeout::SESSION_KEY => now()->subMinutes(5)->getTimestamp()],
        [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ],
    );

    $response = (new SessionTimeout)->handle($request, fn () => response('should-not-run'));

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getData(true))->toMatchArray([
            'authenticated' => false,
            'session_expired' => true,
        ]);
});

it('refreshes activity for continue-session style requests', function () {
    Auth::shouldReceive('check')->once()->andReturn(true);

    Route::post('/session/continue-test', fn () => response()->json(['ok' => true]))
        ->name('kabataan.session.continue');

    $initial = now()->subSeconds(30)->getTimestamp();
    $request = kabataanMakeAuthenticatedRequest('/session/continue-test', 'POST', [
        SessionTimeout::SESSION_KEY => $initial,
    ], [
        'HTTP_ACCEPT' => 'application/json',
    ]);
    $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

    (new SessionTimeout)->handle($request, fn () => response()->json(['ok' => true]));

    expect((int) $request->session()->get(SessionTimeout::SESSION_KEY))->toBeGreaterThan($initial);
});

it('defaults inactivity timeout to 120 minutes', function () {
    config()->set('session.timeout', 120);
    expect((int) config('session.timeout'))->toBe(120);
});
