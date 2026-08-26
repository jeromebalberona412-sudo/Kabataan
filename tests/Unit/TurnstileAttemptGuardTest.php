<?php

namespace Tests\Unit;

use App\Services\TurnstileAttemptGuard;
use App\Services\TurnstileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class TurnstileAttemptGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_initial_request_requires_turnstile(): void
    {
        Config::set('services.turnstile.failed_attempts', 3);

        $turnstile = Mockery::mock(TurnstileService::class);
        $turnstile->shouldReceive('isEnabled')->andReturn(true);

        $guard = new TurnstileAttemptGuard($turnstile);
        $request = $this->makeRequest();

        $this->assertTrue($guard->isRequired(TurnstileAttemptGuard::ACTION_SIGNIN, $request));
    }

    public function test_successful_verification_clears_requirement_until_threshold(): void
    {
        Config::set('services.turnstile.failed_attempts', 3);
        Cache::flush();

        $turnstile = Mockery::mock(TurnstileService::class);
        $turnstile->shouldReceive('isEnabled')->andReturn(true);
        $turnstile->shouldReceive('verify')->once()->with('fresh-token', '127.0.0.1')->andReturn(true);

        $guard = new TurnstileAttemptGuard($turnstile);
        $request = $this->makeRequest(['cf-turnstile-response' => 'fresh-token']);

        $this->assertNull($guard->enforce(TurnstileAttemptGuard::ACTION_SIGNIN, $request));
        $this->assertFalse($guard->isRequired(TurnstileAttemptGuard::ACTION_SIGNIN, $request));

        $guard->recordFailure(TurnstileAttemptGuard::ACTION_SIGNIN, $request);
        $guard->recordFailure(TurnstileAttemptGuard::ACTION_SIGNIN, $request);
        $this->assertFalse($guard->isRequired(TurnstileAttemptGuard::ACTION_SIGNIN, $request));

        $third = $guard->recordFailure(TurnstileAttemptGuard::ACTION_SIGNIN, $request);
        $this->assertTrue($third['threshold_reached']);
        $this->assertTrue($guard->isRequired(TurnstileAttemptGuard::ACTION_SIGNIN, $request));
        $this->assertNotNull($third['message']);
    }

    public function test_failed_turnstile_does_not_reset_failure_counter(): void
    {
        Config::set('services.turnstile.failed_attempts', 3);
        Cache::flush();

        $turnstile = Mockery::mock(TurnstileService::class);
        $turnstile->shouldReceive('isEnabled')->andReturn(true);
        $turnstile->shouldReceive('verify')->once()->with('good', '127.0.0.1')->andReturn(true);
        $turnstile->shouldReceive('verify')->once()->with('bad', '127.0.0.1')->andReturn(false);

        $guard = new TurnstileAttemptGuard($turnstile);
        $request = $this->makeRequest(['cf-turnstile-response' => 'good']);
        $this->assertNull($guard->enforce(TurnstileAttemptGuard::ACTION_SIGNIN, $request));

        $guard->recordFailure(TurnstileAttemptGuard::ACTION_SIGNIN, $request);
        $guard->recordFailure(TurnstileAttemptGuard::ACTION_SIGNIN, $request);
        $guard->recordFailure(TurnstileAttemptGuard::ACTION_SIGNIN, $request);
        $this->assertTrue($guard->isRequired(TurnstileAttemptGuard::ACTION_SIGNIN, $request));

        $badRequest = $this->makeRequest(['cf-turnstile-response' => 'bad']);
        $this->assertSame(
            'Security verification failed. Please try again.',
            $guard->enforce(TurnstileAttemptGuard::ACTION_SIGNIN, $badRequest)
        );

        $status = $guard->status(TurnstileAttemptGuard::ACTION_SIGNIN, $badRequest);
        $this->assertSame(3, $status['failed_attempts']);
        $this->assertTrue($status['required']);
    }

    public function test_success_clears_state(): void
    {
        Config::set('services.turnstile.failed_attempts', 3);
        Cache::flush();

        $turnstile = Mockery::mock(TurnstileService::class);
        $turnstile->shouldReceive('isEnabled')->andReturn(true);
        $turnstile->shouldReceive('verify')->once()->andReturn(true);

        $guard = new TurnstileAttemptGuard($turnstile);
        $request = $this->makeRequest(['cf-turnstile-response' => 'token']);
        $guard->enforce(TurnstileAttemptGuard::ACTION_SIGNIN, $request);
        $guard->recordFailure(TurnstileAttemptGuard::ACTION_SIGNIN, $request);
        $guard->clear(TurnstileAttemptGuard::ACTION_SIGNIN, $request);

        // Cleared state looks like a fresh visitor again.
        $this->assertTrue($guard->isRequired(TurnstileAttemptGuard::ACTION_SIGNIN, $request));
        $this->assertSame(0, $guard->status(TurnstileAttemptGuard::ACTION_SIGNIN, $request)['failed_attempts']);
    }

    public function test_disabled_turnstile_never_required(): void
    {
        $turnstile = Mockery::mock(TurnstileService::class);
        $turnstile->shouldReceive('isEnabled')->andReturn(false);

        $guard = new TurnstileAttemptGuard($turnstile);
        $request = $this->makeRequest();

        $this->assertFalse($guard->isRequired(TurnstileAttemptGuard::ACTION_SIGNIN, $request));
        $this->assertNull($guard->enforce(TurnstileAttemptGuard::ACTION_SIGNIN, $request));
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function makeRequest(array $input = []): Request
    {
        $request = Request::create('/sign-in', 'POST', $input);
        $request->setLaravelSession($this->app['session']->driver());
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        return $request;
    }
}
