<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use App\Models\License;
use App\Models\Manufacturer;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ManualLicenseExpirationInvestigationSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureSettingsRow();

        $company = Company::updateOrCreate(
            ['name' => 'QA License Policy Company'],
            [
                'email' => 'qa-license-policy@example.com',
                'phone' => '+1-555-0100',
                'fax' => '+1-555-0101',
            ]
        );

        $admin = User::withTrashed()->updateOrCreate(
            ['username' => 'qa-license-admin'],
            [
                'first_name' => 'QA',
                'last_name' => 'License Admin',
                'email' => 'qa-license-admin@example.com',
                'company_id' => $company->id,
                'activated' => 1,
                'locale' => 'en-US',
                'permissions' => '{"superuser":"1"}',
                'password' => Hash::make('password'),
                'created_by' => 1,
                'deleted_at' => null,
            ]
        );

        if (! $admin->created_by) {
            $admin->forceFill(['created_by' => $admin->id])->save();
        }

        $category = Category::firstOrCreate(
            [
                'name' => 'QA License Policy Category',
                'category_type' => 'license',
            ]
        );

        $manufacturer = Manufacturer::firstOrCreate(
            ['name' => 'QA License Vendor']
        );

        $supplier = Supplier::firstOrCreate(
            ['name' => 'QA License Supplier']
        );

        $licenses = [
            [
                'serial' => 'QA-LIC-PERP-001',
                'name' => 'QA Perpetual CAD Suite',
                'seats' => 5,
                'purchase_date' => '2024-03-15',
                'expiration_date' => null,
                'perpetual' => 1,
                'termination_date' => null,
                'maintained' => 0,
                'reassignable' => 1,
                'notes' => 'Deliberately has no expiration date to represent a perpetual license.',
            ],
            [
                'serial' => 'QA-LIC-SUB-001',
                'name' => 'QA Annual Design Cloud',
                'seats' => 12,
                'purchase_date' => '2026-01-10',
                'expiration_date' => '2027-01-10',
                'perpetual' => 0,
                'termination_date' => null,
                'maintained' => 1,
                'reassignable' => 1,
                'notes' => 'Represents a normal subscription with a known renewal date.',
            ],
            [
                'serial' => 'QA-LIC-EXP-001',
                'name' => 'QA Expired Security Scanner',
                'seats' => 3,
                'purchase_date' => '2024-05-01',
                'expiration_date' => '2026-05-31',
                'perpetual' => 0,
                'termination_date' => null,
                'maintained' => 1,
                'reassignable' => 0,
                'notes' => 'Already expired so we can verify UI and reporting behavior for hard expirations.',
            ],
            [
                'serial' => 'QA-LIC-TERM-001',
                'name' => 'QA Contractor Tooling Bundle',
                'seats' => 8,
                'purchase_date' => '2026-02-01',
                'expiration_date' => null,
                'perpetual' => 1,
                'termination_date' => '2026-12-31',
                'maintained' => 1,
                'reassignable' => 1,
                'notes' => 'Has a termination date but no expiration date to show a separate end-of-use policy.',
            ],
            [
                'serial' => 'QA-LIC-BOTH-001',
                'name' => 'QA Vendor Managed Analytics',
                'seats' => 20,
                'purchase_date' => '2026-04-01',
                'expiration_date' => '2026-11-15',
                'perpetual' => 0,
                'termination_date' => '2026-12-15',
                'maintained' => 1,
                'reassignable' => 1,
                'notes' => 'Uses both dates so we can compare what each field communicates in the product.',
            ],
        ];

        foreach ($licenses as $licenseData) {
            License::withTrashed()->updateOrCreate(
                ['serial' => $licenseData['serial']],
                array_merge($licenseData, [
                    'company_id' => $company->id,
                    'category_id' => $category->id,
                    'manufacturer_id' => $manufacturer->id,
                    'supplier_id' => $supplier->id,
                    'license_email' => 'procurement@example.com',
                    'license_name' => 'QA Procurement',
                    'order_number' => 'QA-LIC-ORDER',
                    'purchase_order' => 'QA-PO-LICENSE',
                    'purchase_cost' => 999.99,
                    'created_by' => $admin->id,
                    'deleted_at' => null,
                ])
            );
        }
    }

    private function ensureSettingsRow(): void
    {
        if (Setting::query()->exists()) {
            return;
        }

        $timestamp = now();

        DB::table('settings')->insert([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'per_page' => 20,
            'site_name' => 'Snipe-IT Demo',
            'auto_increment_assets' => 1,
            'load_remote' => 1,
            'alerts_enabled' => 1,
            'default_currency' => 'USD',
            'brand' => 2,
            'full_multiple_companies_support' => 0,
            'scope_locations_fmcs' => 0,
            'locale' => 'en-US',
            'labels_per_page' => 30,
            'labels_width' => 2.62500,
            'labels_height' => 1.00000,
            'labels_pmargin_left' => 0.21975,
            'labels_pmargin_right' => 0.21975,
            'labels_pmargin_top' => 0.50000,
            'labels_pmargin_bottom' => 0.50000,
            'labels_display_bgutter' => 0.07000,
            'labels_display_sgutter' => 0.05000,
            'labels_fontsize' => 9,
            'labels_pagewidth' => 8.50000,
            'labels_pageheight' => 11.00000,
            'labels_display_name' => 0,
            'labels_display_serial' => 1,
            'labels_display_tag' => 1,
            'alt_barcode_enabled' => 1,
            'name_display_format' => 'first_last',
            'email_format' => 'filastname',
            'username_format' => 'filastname',
            'is_ad' => 0,
            'ldap_port' => '389',
            'ldap_tls' => 0,
            'zerofill_count' => 5,
            'ldap_pw_sync' => 1,
            'require_accept_signature' => 0,
            'date_display_format' => 'Y-m-d',
            'time_display_format' => 'h:i A',
            'next_auto_tag_base' => 1,
            'thumbnail_max_h' => 30,
            'pwd_secure_uncommon' => 0,
            'pwd_secure_min' => 8,
            'show_url_in_emails' => 0,
            'show_alerts_in_menu' => 1,
            'labels_display_company_name' => 0,
            'show_archived_in_list' => 0,
            'support_footer' => 'on',
            'modellist_displays' => 'image,category,manufacturer,model_number',
            'login_remote_user_enabled' => 0,
            'login_common_disabled' => 0,
            'login_remote_user_custom_logout_url' => '',
            'show_images_in_email' => 1,
            'admin_cc_always' => 1,
            'labels_display_model' => 0,
            'version_footer' => 'on',
            'unique_serial' => 0,
            'logo_print_assets' => 0,
            'default_avatar' => 'default.png',
            'allow_user_skin' => 0,
            'show_assigned_assets' => 0,
            'login_remote_user_header_name' => '',
            'ad_append_domain' => 0,
            'saml_enabled' => 0,
            'digit_separator' => '1,234.56',
            'dash_chart_type' => 'name',
            'label2_enable' => 0,
            'label2_template' => 'DefaultLabel',
            'label2_1d_type' => 'C128',
            'label2_2d_type' => 'QRCODE',
            'label2_2d_target' => 'hardware_id',
            'label2_fields' => 'name=name;serial=serial;model=model.name;',
            'label2_empty_row_count' => 0,
            'google_login' => 0,
            'profile_edit' => 1,
            'require_checkinout_notes' => 0,
            'shortcuts_enabled' => 0,
            'ldap_invert_active_flag' => 0,
            'manager_view_enabled' => 0,
            'finance_report_enabled' => 0,
        ]);
    }
}
