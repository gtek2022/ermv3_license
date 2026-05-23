<?php

namespace App\Console\Commands;

use App\Models\MasterConfig;
use Illuminate\Console\Command;
use LucaLongo\Licensing\Models\LicensingKey;

/**
 * Syncs the active signing public key into master_configs.
 *
 * Run after: php artisan licensing:keys:generate
 * Also runs automatically on schedule (daily).
 *
 * The public key in master_configs is:
 *   - is_public = true  → returned in config-sync to client apps
 *   - is_encrypted = false → plain base64url string
 *   - NOT editable via UI (protected by config_key prefix 'system.signing.')
 */
class SyncPublicKeyToConfig extends Command
{
    protected $signature   = 'license:sync-public-key';
    protected $description = 'Sync the active signing public key into master_configs for client distribution.';

    public function handle(): int
    {
        $signingKey = LicensingKey::findActiveSigning();

        if (! $signingKey) {
            $this->error('No active signing key found. Run: php artisan licensing:keys:generate');
            return self::FAILURE;
        }

        $rootKey = LicensingKey::findActiveRoot();

        // Upsert signing public key
        MasterConfig::updateOrCreate(
            ['config_key' => 'system.signing.public_key'],
            [
                'config_value' => $signingKey->getPublicKey(),
                'config_type'  => 'string',
                'category'     => 'system.signing',
                'description'  => 'Active Ed25519 signing public key. Used by client apps to verify license tokens. NOT editable — managed automatically.',
                'is_encrypted' => false,
                'is_public'    => true,
                'updated_by'   => null,
            ]
        );

        // Upsert root public key
        if ($rootKey) {
            MasterConfig::updateOrCreate(
                ['config_key' => 'system.signing.root_public_key'],
                [
                    'config_value' => $rootKey->getPublicKey(),
                    'config_type'  => 'string',
                    'category'     => 'system.signing',
                    'description'  => 'Root CA public key for certificate chain verification. NOT editable.',
                    'is_encrypted' => false,
                    'is_public'    => true,
                    'updated_by'   => null,
                ]
            );
        }

        // Upsert server URL
        MasterConfig::updateOrCreate(
            ['config_key' => 'system.signing.server_url'],
            [
                'config_value' => rtrim(config('app.url'), '/'),
                'config_type'  => 'string',
                'category'     => 'system.signing',
                'description'  => 'Gemilang server URL for client LICENSING_SERVER_URL. NOT editable.',
                'is_encrypted' => false,
                'is_public'    => true,
                'updated_by'   => null,
            ]
        );

        // Upsert issuer
        MasterConfig::updateOrCreate(
            ['config_key' => 'system.signing.issuer'],
            [
                'config_value' => config('licensing.offline_token.issuer', 'laravel-licensing'),
                'config_type'  => 'string',
                'category'     => 'system.signing',
                'description'  => 'PASETO token issuer identifier. NOT editable.',
                'is_encrypted' => false,
                'is_public'    => true,
                'updated_by'   => null,
            ]
        );

        $this->info('Public key synced to master_configs.');
        $this->line('  Key ID:     ' . $signingKey->kid);
        $this->line('  Public Key: ' . substr($signingKey->getPublicKey(), 0, 32) . '…');

        return self::SUCCESS;
    }
}
