<?php

namespace App\Console\Commands;

use App\Models\Ldap;
use App\Models\Setting;
use App\Models\User;
use App\Services\LdapUserStatusReconciliation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AuditLdapUserStatus extends Command
{
    protected $signature = 'snipeit:audit-ldap-user-status
                            {--base_dn= : Override the configured LDAP base DN}
                            {--filter= : Override the broad AD user filter}
                            {--all : Display every reconciliation row instead of review rows only}
                            {--json : Output the complete report as JSON}
                            {--csv= : Write the complete detailed report to a new CSV file}';

    protected $description = 'Read-only reconciliation of LEAMS users with live AD enabled/disabled status.';

    public function handle(LdapUserStatusReconciliation $reconciliation): int
    {
        $settings = Setting::getSettings();

        if (! $settings || (string) $settings->ldap_enabled !== '1') {
            $this->error('LDAP is not enabled. Aborting. See Settings > LDAP to enable it.');

            return self::FAILURE;
        }

        if ((string) $settings->is_ad !== '1') {
            $this->error('This command requires Active Directory because it reads userAccountControl.');

            return self::INVALID;
        }

        $baseDn = trim((string) ($this->option('base_dn') ?: $settings->ldap_basedn));
        if ($baseDn === '') {
            $this->error('The LDAP Base Bind DN is empty. Configure it under Settings > LDAP or pass --base_dn.');

            return self::INVALID;
        }

        $filter = trim((string) ($this->option('filter') ?: '(&(objectCategory=person)(objectClass=user))'));
        $attributeMap = [
            'username' => strtolower((string) ($settings->ldap_username_field ?: 'samaccountname')),
            'display_name' => strtolower((string) ($settings->ldap_display_name ?: 'displayname')),
            'employee_id' => strtolower((string) ($settings->ldap_emp_num ?: 'employeeid')),
            'email' => strtolower((string) ($settings->ldap_email ?: 'mail')),
        ];
        $attributes = array_values(array_unique(array_merge(
            array_values($attributeMap),
            ['useraccountcontrol', 'distinguishedname']
        )));

        try {
            $ldapResults = Ldap::findLdapUsers($baseDn, -1, $filter, $attributes);
            if (! is_array($ldapResults)) {
                throw new RuntimeException('The LDAP query did not return a readable result set.');
            }

            $leamsUsers = User::query()
                ->with('company:id,name')
                ->withCount(['assets', 'licenses', 'accessories', 'consumables'])
                ->orderBy('id')
                ->get()
                ->map(fn (User $user) => [
                    'leams_user_id' => $user->id,
                    'username' => (string) $user->username,
                    'employee_id' => (string) $user->employee_num,
                    'email' => (string) $user->email,
                    'display_name' => trim($user->first_name.' '.$user->last_name),
                    'activated' => (bool) $user->activated,
                    'ldap_import' => (bool) $user->ldap_import,
                    'company' => (string) ($user->company?->name ?? ''),
                    'country' => (string) $user->country,
                    'assets_count' => (int) $user->assets_count,
                    'licenses_count' => (int) $user->licenses_count,
                    'accessories_count' => (int) $user->accessories_count,
                    'consumables_count' => (int) $user->consumables_count,
                ]);
        } catch (\Throwable $exception) {
            Log::warning('LDAP user status audit failed.', ['exception' => $exception]);
            $this->error('LDAP user status audit failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $report = $reconciliation->build($ldapResults, $leamsUsers, $attributeMap);

        if ($csvPath = $this->option('csv')) {
            try {
                $this->writeCsv((string) $csvPath, $report['rows']);
                $this->info('CSV report written to '.$csvPath);
            } catch (RuntimeException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(
            ['Metric', 'Count'],
            collect($report['summary'])->map(fn ($value, $metric) => [$metric, $value])->values()->all()
        );

        $rows = $this->option('all')
            ? $report['rows']
            : array_values(array_filter($report['rows'], fn ($row) => $reconciliation->requiresReview($row)));

        $this->newLine();
        $this->table(
            ['LEAMS ID', 'Username', 'Name', 'LEAMS', 'AD', 'Classification', 'Inventory', 'Match'],
            array_map(fn ($row) => [
                $row['leams_user_id'],
                $row['username'],
                $row['display_name'],
                $row['activated'] ? 'active' : 'inactive',
                $row['ad_account_status'] ?: 'not found',
                $row['classification'],
                $row['assigned_inventory'],
                $row['match_basis'],
            ], $rows)
        );
        $this->newLine();
        $this->warn('Read-only audit: no LEAMS or Active Directory accounts were changed.');
        $this->line('The default LDAP filter is intentionally broader than the configured sync filter so disabled AD users are included.');

        return self::SUCCESS;
    }

    private function writeCsv(string $path, array $rows): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new RuntimeException('CSV directory is not writable: '.$directory);
        }
        if (file_exists($path)) {
            throw new RuntimeException('CSV report already exists: '.$path);
        }

        $handle = @fopen($path, 'xb');
        if ($handle === false) {
            throw new RuntimeException('Could not create CSV report (the file may already exist): '.$path);
        }
        chmod($path, 0600);

        $headers = array_keys($rows[0] ?? [
            'classification' => null,
            'recommended_action' => null,
            'review_decision' => null,
        ]);
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(
                fn ($header) => is_bool($row[$header] ?? null)
                    ? ($row[$header] ? '1' : '0')
                    : ($row[$header] ?? ''),
                $headers
            ));
        }
        fclose($handle);
    }
}
