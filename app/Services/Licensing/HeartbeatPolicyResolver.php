<?php

namespace App\Services\Licensing;

use App\Models\LicenseCompany;
use App\Models\MasterConfig;
use LucaLongo\Licensing\Models\License;

/**
 * The one place that decides how often a client should heartbeat.
 *
 * Before this existed the answer depended on who was asking. Two independent
 * master_configs rows hold the same setting under different names:
 *
 *   heartbeat_interval            seeded by DatabaseSeeder, read by ConfigSyncController
 *   licensing.heartbeat_interval  read by ClientConfigController, DashboardController,
 *                                 HeartbeatMonitorController
 *
 * MasterConfig::get() matches config_key literally and has no namespacing, so those are
 * two separate rows with two separate values. An admin who edited one of them saw half
 * the system change and the other half carry on as before - the dashboard counting down
 * to one interval while the clients obeyed the other. Both rows sat at 3600 only because
 * they were set by hand, twice.
 *
 * Every consumer now goes through here, so whichever row an admin edits, the whole
 * server agrees on the answer. ConfigController keeps the two rows equal on write; this
 * class still resolves in a fixed order in case they ever drift again.
 */
class HeartbeatPolicyResolver
{
    /**
     * Config keys that mean "heartbeat interval", most canonical first.
     *
     * Order matters: `licensing.`-prefixed is canonical because that is what the client
     * config endpoint publishes and what the version hash covers.
     */
    public const INTERVAL_ALIASES = [
        'licensing.heartbeat_interval',
        'heartbeat_interval',
    ];

    /**
     * A heartbeat is the liveness signal, so the bounds are about keeping it meaningful
     * rather than about taste.
     *
     * Below a minute the scheduler cannot honour it anyway - clients run the command
     * every minute and throttle in between - and it would turn every install into a
     * needless load source. Above a day the signal stops being liveness: the offline
     * token lives 7 days (licensing.offline_token.ttl_days) and lockout is measured in
     * days after first failure, so an interval longer than a day means a dead install
     * looks healthy for most of its remaining token life.
     *
     * A value outside the range is clamped rather than rejected, because refusing would
     * mean answering a heartbeat with an error over a config typo.
     */
    public const MIN_INTERVAL = 60;
    public const MAX_INTERVAL = 86400;

    public const DEFAULT_INTERVAL = 3600;

    /**
     * Per-instance memo of the global config lookup.
     *
     * MasterConfig::get() is an uncached SELECT per call, and the heartbeat monitor resolves
     * an interval for every installation in the list. Holding the answer for the lifetime of
     * one resolver keeps that at two queries instead of two per row. Callers that want the
     * fresh value after writing config should resolve a new instance, which is what a new
     * request does anyway.
     *
     * @var array<string, int|null>
     */
    protected array $globalMemo = [];

    /**
     * The interval this particular licence should use, and where the value came from.
     *
     * Precedence mirrors LicensePolicyController and ConfigSyncController, which already
     * resolve heartbeat_tolerance and warning_days this way:
     *
     *   1. license_companies.meta.policy.heartbeat_interval   per-licence, set in the admin form
     *   2. licenses.meta.policy.heartbeat_interval            the mirror the admin form writes
     *   3. master_configs                                     global, either alias
     *   4. DEFAULT_INTERVAL                                   nothing configured at all
     *
     * `source` is returned alongside the value because the commonest support question
     * about this setting is "I changed it and nothing happened", and the answer is almost
     * always that a more specific layer is winning. Handing back the layer name turns
     * that into something an admin can read off the response.
     *
     * @return array{interval:int, source:string, raw:int|null}
     */
    public function resolve(?LicenseCompany $company = null, ?License $license = null): array
    {
        foreach ($this->candidates($company, $license) as $source => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $raw = (int) $value;
            if ($raw <= 0) {
                continue;
            }

            return [
                'interval' => $this->clamp($raw),
                'source'   => $source,
                'raw'      => $raw,
            ];
        }

        return [
            'interval' => self::DEFAULT_INTERVAL,
            'source'   => 'default',
            'raw'      => null,
        ];
    }

    /**
     * Convenience for the many callers that only want the number.
     */
    public function intervalFor(?LicenseCompany $company = null, ?License $license = null): int
    {
        return $this->resolve($company, $license)['interval'];
    }

    /**
     * The global interval, with no licence in play. Used by the admin screens and by the
     * client-config endpoint, which is not licence-scoped.
     */
    public function globalInterval(): int
    {
        return $this->clamp($this->globalRaw() ?? self::DEFAULT_INTERVAL);
    }

    /**
     * Ordered candidate values, keyed by the name of the layer they came from.
     *
     * @return array<string, mixed>
     */
    protected function candidates(?LicenseCompany $company, ?License $license): array
    {
        $candidates = [
            'license_company_policy' => $company?->meta['policy']['heartbeat_interval'] ?? null,
            'license_meta_policy'    => ((array) ($license?->meta ?? []))['policy']['heartbeat_interval'] ?? null,
        ];

        foreach (self::INTERVAL_ALIASES as $key) {
            $candidates['master_config:' . $key] = $this->configValue($key);
        }

        return $candidates;
    }

    protected function globalRaw(): ?int
    {
        foreach (self::INTERVAL_ALIASES as $key) {
            $value = $this->configValue($key);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * A positive integer from master_configs, or null for absent/blank/non-positive.
     *
     * Zero and negatives are treated as "not set" rather than clamped up to the minimum: a
     * 0 in that column is far more likely to be a cleared field than a deliberate request
     * for the fastest possible heartbeat, and falling through to the next layer is the
     * safer reading of it.
     */
    protected function configValue(string $key): ?int
    {
        if (! array_key_exists($key, $this->globalMemo)) {
            $raw = MasterConfig::get($key);

            $this->globalMemo[$key] = ($raw === null || $raw === '' || (int) $raw <= 0)
                ? null
                : (int) $raw;
        }

        return $this->globalMemo[$key];
    }

    protected function clamp(int $seconds): int
    {
        return max(self::MIN_INTERVAL, min(self::MAX_INTERVAL, $seconds));
    }
}
