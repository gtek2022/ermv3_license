<?php

namespace App\Http\Middleware;

use App\Models\LicenseCompany;
use App\Services\Licensing\HeartbeatPolicyResolver;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LucaLongo\Licensing\Models\License;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the heartbeat response carry the interval the client should use next.
 *
 * The client used to decide its own cadence from a separately cached copy of the global
 * config, refreshed by a per-minute version probe. That worked, but it meant the interval
 * and the heartbeat itself arrived by different routes: the server could not express a
 * per-licence cadence at all, and there was no moment at which client and server were
 * known to agree. Answering it here ties the two together - the reply to "I am alive" now
 * also says "ask me again in N seconds" - so the client can re-anchor from the instant it
 * received the answer.
 *
 * Why a middleware rather than a controller.
 *
 * The endpoint belongs to the package: routes/api.php inside masterix21/laravel-licensing
 * binds it to its own UsageController, and nothing in this application overrides it.
 * Claiming the path in our routes/api.php would mean relying on registration order
 * between package and application route files to decide which handler wins - on a licence
 * server that six live installations depend on, where losing that race means every
 * heartbeat 404s. Decorating the response afterwards cannot affect route resolution at
 * all, so the failure mode is bounded: worst case the extra keys are missing and clients
 * fall back to their cached interval, which is exactly what they did before.
 *
 * Compatibility. Everything is added as a new sibling under `data`; `data.usage.*` is left
 * exactly as the package wrote it. absensi, pds and ermv3 read only `success` and
 * `data.usage`, so they are unaffected. Any failure in here is swallowed and the original
 * response is returned untouched - a heartbeat must never fail over a policy lookup.
 */
class AttachHeartbeatPolicy
{
    protected const HEARTBEAT_PATH = 'api/licensing/v1/heartbeat';

    public function __construct(protected HeartbeatPolicyResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->applies($request, $response)) {
            return $response;
        }

        try {
            $this->attach($request, $response);
        } catch (\Throwable $e) {
            // Never let this cost a heartbeat. The client keeps its cached interval.
            Log::warning('[AttachHeartbeatPolicy] could not attach policy: ' . $e->getMessage());
        }

        return $response;
    }

    /**
     * Only a successful heartbeat gets decorated.
     *
     * The status check matters as much as the path check: on 403 FINGERPRINT_MISMATCH or
     * 404 INVALID_KEY there is no licence to resolve a policy for, and attaching a cadence
     * to a rejection would tell an unrecognised caller something about our configuration.
     */
    protected function applies(Request $request, Response $response): bool
    {
        return $request->path() === self::HEARTBEAT_PATH
            && $response instanceof JsonResponse
            && $response->getStatusCode() === 200;
    }

    protected function attach(Request $request, JsonResponse $response): void
    {
        $payload = $response->getData(true);

        if (! is_array($payload) || ! ($payload['success'] ?? false) || ! isset($payload['data']) || ! is_array($payload['data'])) {
            return;
        }

        $licenseKey = (string) $request->input('license_key', '');
        if ($licenseKey === '') {
            return;
        }

        $resolved = $this->resolver->resolve(
            LicenseCompany::findByKey($licenseKey),
            License::findByKey($licenseKey),
        );

        $payload['data']['policy'] = [
            'heartbeat_interval' => $resolved['interval'],

            // Which layer supplied the number - per-licence override, one of the two global
            // config rows, or the built-in default. This is here because the recurring
            // support question is "I changed the interval and nothing happened", and the
            // answer is nearly always that a more specific layer is winning. Now the client
            // logs and the diagnostics screen can just say so.
            'source'             => $resolved['source'],
        ];

        // The client anchors its next due time on the moment it received this response, so
        // it never needs to trust its own clock against ours. server_time is sent anyway so
        // that a skewed client is diagnosable rather than merely wrong.
        $payload['data']['server_time'] = now()->toIso8601String();

        $response->setData($payload);
    }
}
