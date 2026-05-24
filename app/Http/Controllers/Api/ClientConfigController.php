<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterConfig;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/platform/v1/client-config
 *
 * Public, no auth. Returns the runtime config that client apps (ermv3,
 * future products) need to operate the licensing client. None of these
 * are secrets — they're just defaults the server controls so admins can
 * tune behavior without redeploying every client.
 *
 * Client (ermv3) calls this once during install + periodically refreshes
 * and caches in storage/app/licensing/state/.client_config.
 */
class ClientConfigController extends Controller
{
    public function show(): JsonResponse
    {
        // Allow admin to override these globally via master_configs
        $get = fn (string $key, mixed $default) => MasterConfig::get($key, $default);

        return response()->json([
            'success' => true,
            'data' => [
                'issuer'                => $get('licensing.issuer', config('licensing.offline_token.issuer', 'gemilang-inti-teknologi')),

                // Heartbeat
                'heartbeat_enabled'     => (bool) $get('licensing.heartbeat_enabled', true),
                'heartbeat_interval'    => (int)  $get('licensing.heartbeat_interval', 3600),
                'heartbeat_retry_limit' => (int)  $get('licensing.heartbeat_retry_limit', 3),
                'warning_days'          => (int)  $get('licensing.warning_days', 3),
                'grace_period_days'     => (int)  $get('licensing.grace_period_days', 7),

                // Network / token
                'timeout'               => (int)  $get('licensing.timeout', 30),
                'cache_ttl'             => (int)  $get('licensing.cache_ttl', 3600),
                'clock_skew_seconds'    => (int)  $get('licensing.clock_skew_seconds', 60),

                // Misc
                'debug'                 => (bool) $get('licensing.debug', false),

                // Cache hint
                'cache_until'           => now()->addHours(6)->toIso8601String(),
            ],
        ]);
    }
}
