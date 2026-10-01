<?php

namespace App\Services\SecurityLog;

use App\Models\SecurityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SecurityLogService
{
    private const LOGIN_FAILURE_THRESHOLD = 5;

    public function recordLoginFailure(
        string $guard,
        string $identity,
        ?Model $actor = null,
        array $metadata = [],
        ?Request $request = null,
    ): void {
        $key = $this->loginFailureKey($guard, $identity);
        $decaySeconds = $guard === 'user' ? (int) config('otp.rate_limits.login.decay_seconds', 60) : 60;
        Cache::add($key, 0, now()->addSeconds(max(1, $decaySeconds)));

        if (Cache::increment($key) !== self::LOGIN_FAILURE_THRESHOLD) {
            return;
        }

        $this->record(
            event: 'login_failed',
            actor: $actor,
            metadata: [...$metadata, 'failed_attempts' => self::LOGIN_FAILURE_THRESHOLD],
            request: $request,
        );
    }

    public function clearLoginFailures(string $guard, string $identity): void
    {
        Cache::forget($this->loginFailureKey($guard, $identity));
    }

    private function loginFailureKey(string $guard, string $identity): string
    {
        return 'security:login-failures:' . $guard . ':' . hash('sha256', Str::lower(trim($identity)));
    }

    /** @param array<string, mixed> $metadata */
    public function record(
        string $event,
        ?Model $actor = null,
        array $metadata = [],
        ?Request $request = null,
    ): SecurityLog {
        $request ??= request();
        $authenticatedActor = $request->user();

        if ($actor === null && $authenticatedActor instanceof Model) {
            $actor = $authenticatedActor;
        }

        $securityLog = new SecurityLog([
            'event' => $event,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => $metadata,
        ]);

        if ($actor !== null) {
            $securityLog->actor()->associate($actor);
        }

        $securityLog->save();

        return $securityLog;
    }
}
