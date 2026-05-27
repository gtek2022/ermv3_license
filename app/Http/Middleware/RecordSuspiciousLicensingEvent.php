<?php

namespace App\Http\Middleware;

use App\Models\LicenseCompany;
use App\Models\LicenseInstallation;
use App\Models\LicenseLogsSuspicious;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Terminate middleware: setelah package /api/licensing/v1/* selesai,
 * inspect response. Kalau response = 4xx untuk error security-relevan,
 * tulis row ke `license_logs_suspicious` supaya dashboard admin bisa
 * mereview.
 *
 * Event types yang ditangkap (sesuai migration schema):
 *   - fingerprint_mismatch  → 403 dengan code FINGERPRINT_MISMATCH
 *   - invalid_key           → 404 dengan code INVALID_KEY
 *   - revoked_attempt       → 403 license suspended/cancelled tapi dipakai
 *   - blacklisted           → 403 install ditolak karena blacklist
 *   - replay_attack         → 422 INVALID_NONCE (nonce sudah dipakai)
 *   - seat_limit_exceeded   → 422 USAGE_LIMIT
 */
class RecordSuspiciousLicensingEvent
{
    /**
     * Mapping error code dari package ke event_type + severity.
     *
     * @var array<string, array{event_type: string, severity: string}>
     */
    protected const ERROR_MAP = [
        'FINGERPRINT_MISMATCH' => ['event_type' => 'fingerprint_mismatch', 'severity' => 'warning'],
        'INVALID_KEY'          => ['event_type' => 'invalid_key',          'severity' => 'warning'],
        'INVALID_NONCE'        => ['event_type' => 'replay_attack',        'severity' => 'critical'],
        'USAGE_LIMIT'          => ['event_type' => 'seat_limit_exceeded',  'severity' => 'warning'],
        'LICENSE_NOT_USABLE'   => ['event_type' => 'revoked_attempt',      'severity' => 'critical'],
        'BLACKLISTED'          => ['event_type' => 'blacklisted',          'severity' => 'critical'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Terminate runs AFTER response sent to client — perfect for logging
     * without slowing the response.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Hanya perhatikan licensing API routes
        if (! str_starts_with($request->path(), 'api/licensing/v1/')) {
            return;
        }

        $status = $response->getStatusCode();
        if ($status < 400 || $status >= 500) {
            return; // bukan client error
        }

        try {
            $body = json_decode($response->getContent() ?: '{}', true);
            $code = (string) ($body['error']['code'] ?? $body['code'] ?? '');

            $mapping = self::ERROR_MAP[$code] ?? null;
            if (! $mapping) {
                return; // bukan error yang kita track
            }

            $payload = $request->all();
            $licenseKey  = (string) ($payload['license_key'] ?? '');
            $fingerprint = (string) ($payload['fingerprint'] ?? '');

            // Cari LicenseCompany & Installation kalau ada
            $licenseCompany = $licenseKey
                ? LicenseCompany::findByKey($licenseKey)
                : null;

            $installation = ($licenseCompany && $fingerprint)
                ? LicenseInstallation::where('license_company_id', $licenseCompany->id)
                    ->where('fingerprint', $fingerprint)
                    ->first()
                : null;

            // Registered fingerprint (yang seharusnya) vs received (yang dikirim)
            $registeredFp = $installation?->fingerprint;

            LicenseLogsSuspicious::create([
                'installation_id'        => $installation?->id,
                'license_company_id'     => $licenseCompany?->id,
                'app_code'               => $installation?->app_code,
                'installation_uuid'      => $installation?->installation_uuid,
                'event_type'             => $mapping['event_type'],
                'registered_fingerprint' => $registeredFp,
                'received_fingerprint'   => $fingerprint ?: null,
                'ip_address'             => $request->ip(),
                'domain'                 => $request->getHost(),
                'details'                => json_encode([
                    'endpoint'      => $request->path(),
                    'http_status'   => $status,
                    'error_code'    => $code,
                    'error_message' => $body['error']['message'] ?? $body['message'] ?? null,
                    'user_agent'    => $request->userAgent(),
                ]),
                'severity'    => $mapping['severity'],
                'is_reviewed' => false,
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Logging suspicious event harus best-effort, jangan ganggu response
            Log::warning('[RecordSuspicious] failed to log: ' . $e->getMessage());
        }
    }
}
