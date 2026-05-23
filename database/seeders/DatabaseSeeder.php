<?php

namespace Database\Seeders;

use App\Models\MasterApp;
use App\Models\MasterAppFeature;
use App\Models\MasterConfig;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Super Admin ───────────────────────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'admin@gemilang.local'],
            [
                'name'      => 'Super Admin',
                'password'  => Hash::make('gemilang2025'),
                'role'      => 'super_admin',
                'is_active' => true,
            ]
        );

        // ── Default master_configs ────────────────────────────────────────────
        $configs = [
            // Licensing
            ['config_key' => 'heartbeat_interval',          'config_value' => '3600',                          'config_type' => 'integer',  'category' => 'licensing', 'is_public' => true,  'description' => 'Heartbeat interval in seconds'],
            ['config_key' => 'heartbeat_retry_limit',       'config_value' => '3',                             'config_type' => 'integer',  'category' => 'licensing', 'is_public' => true,  'description' => 'Consecutive failures before warning banner'],
            ['config_key' => 'warning_days_before_lockout', 'config_value' => '3',                             'config_type' => 'integer',  'category' => 'licensing', 'is_public' => true,  'description' => 'Days after first failure before lockout modal'],
            ['config_key' => 'grace_period_days',           'config_value' => '7',                             'config_type' => 'integer',  'category' => 'licensing', 'is_public' => true,  'description' => 'Grace period when server unreachable'],
            ['config_key' => 'warning_banner_message',      'config_value' => 'Verifikasi lisensi gagal. Segera verifikasi atau hubungi administrator.', 'config_type' => 'string', 'category' => 'licensing', 'is_public' => true, 'description' => 'Warning banner message'],
            ['config_key' => 'lock_modal_message',          'config_value' => 'Lisensi perlu diverifikasi. Masukkan kunci lisensi yang valid untuk melanjutkan.', 'config_type' => 'string', 'category' => 'licensing', 'is_public' => true, 'description' => 'Lockout modal message'],
            ['config_key' => 'force_revalidation',          'config_value' => 'false',                         'config_type' => 'boolean',  'category' => 'licensing', 'is_public' => true,  'description' => 'Force all clients to revalidate immediately'],
            // System
            ['config_key' => 'maintenance_mode',            'config_value' => 'false',                         'config_type' => 'boolean',  'category' => 'system',    'is_public' => true,  'description' => 'Put all client apps in maintenance mode'],
            ['config_key' => 'maintenance_message',         'config_value' => 'Sistem sedang dalam pemeliharaan. Silakan coba lagi nanti.', 'config_type' => 'string', 'category' => 'system', 'is_public' => true, 'description' => 'Maintenance mode message'],
            ['config_key' => 'minimum_app_version',         'config_value' => '0.0.0',                         'config_type' => 'string',   'category' => 'system',    'is_public' => true,  'description' => 'Minimum supported app version'],
        ];

        foreach ($configs as $config) {
            MasterConfig::firstOrCreate(
                ['config_key' => $config['config_key']],
                array_merge($config, ['is_encrypted' => false])
            );
        }

        // ── Default app: ermv3 ────────────────────────────────────────────────
        $ermv3 = MasterApp::firstOrCreate(
            ['code' => 'ermv3'],
            [
                'name'        => 'ERM System',
                'description' => 'Enterprise Risk Management System',
                'version'     => '3.0.0',
                'status'      => 'active',
                'icon'        => 'shield',
            ]
        );

        // Default features for ermv3
        $features = [
            ['feature_key' => 'risk_register',      'name' => 'Risk Register',       'category' => 'core'],
            ['feature_key' => 'dashboard',           'name' => 'Dashboard',           'category' => 'core'],
            ['feature_key' => 'mitigation',          'name' => 'Mitigation',          'category' => 'core'],
            ['feature_key' => 'early_warning',       'name' => 'Early Warning',       'category' => 'core'],
            ['feature_key' => 'insurance',           'name' => 'Insurance',           'category' => 'premium'],
            ['feature_key' => 'export_excel',        'name' => 'Export Excel',        'category' => 'premium'],
            ['feature_key' => 'export_pdf',          'name' => 'Export PDF',          'category' => 'premium'],
            ['feature_key' => 'bsc_kpi',             'name' => 'BSC KPI',             'category' => 'addon'],
        ];

        foreach ($features as $feature) {
            MasterAppFeature::firstOrCreate(
                ['app_code' => 'ermv3', 'feature_key' => $feature['feature_key']],
                array_merge($feature, ['app_code' => 'ermv3', 'is_active' => true])
            );
        }
    }
}
