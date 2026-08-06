<?php

namespace App\Console\Commands;

use App\Models\Ldap;
use App\Models\Setting;
use App\Services\LdapCompanyReadinessReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AuditLdapCompanyReadiness extends Command
{
    protected $signature = 'snipeit:audit-ldap-company
                            {--base_dn= : Override the configured LDAP base DN}
                            {--filter= : Override the configured LDAP filter}
                            {--country-attribute=co : LDAP attribute used as the country source}
                            {--only-missing : Show only entries with no country value}
                            {--json : Output the complete report as JSON}
                            {--csv= : Write the detailed report to a CSV file}';

    protected $description = 'Read-only audit of LDAP country coverage and OU, group, company, and office fallback evidence.';

    public function handle(LdapCompanyReadinessReport $reportBuilder): int
    {
        $settings = Setting::getSettings();

        if (! $settings || (string) $settings->ldap_enabled !== '1') {
            $this->error('LDAP is not enabled. Aborting. See Settings > LDAP to enable it.');

            return self::FAILURE;
        }

        $attributeMap = [
            'username' => strtolower((string) ($settings->ldap_username_field ?: 'samaccountname')),
            'display_name' => strtolower((string) ($settings->ldap_display_name ?: 'displayname')),
            'employee_id' => strtolower((string) ($settings->ldap_emp_num ?: 'employeeid')),
            'country' => strtolower(trim((string) $this->option('country-attribute'))),
        ];

        if ($attributeMap['country'] === '') {
            $this->error('The country attribute cannot be empty.');

            return self::INVALID;
        }

        $attributes = array_values(array_unique(array_filter(array_merge(
            array_values($attributeMap),
            ['c', 'company', 'physicaldeliveryofficename', 'distinguishedname', 'memberof', 'useraccountcontrol']
        ))));
        $baseDn = trim((string) ($this->option('base_dn') ?: $settings->ldap_base_dn));
        $filter = $this->option('filter') !== null && $this->option('filter') !== ''
            ? (string) $this->option('filter')
            : null;

        try {
            $ldapResults = Ldap::findLdapUsers($baseDn, -1, $filter, $attributes);
            if (! is_array($ldapResults)) {
                throw new RuntimeException('The LDAP query did not return a readable result set.');
            }
        } catch (\Throwable $exception) {
            Log::warning('LDAP company readiness audit failed.', ['exception' => $exception]);
            $this->error('LDAP company readiness audit failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $report = $reportBuilder->build($ldapResults, $attributeMap);

        if ($this->option('only-missing')) {
            $report['rows'] = array_values(array_filter(
                $report['rows'],
                fn ($row) => $row['country_status'] === 'missing'
            ));
        }

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
        $this->newLine();
        $this->table(
            ['Username', 'Display name', 'Account', 'Country', 'Abbr.', 'Office', 'OUs', 'Groups', 'Fallback evidence'],
            array_map(fn ($row) => [
                $row['username'],
                $row['display_name'],
                $row['account_status'],
                $row['country'],
                $row['country_abbreviation'],
                $row['office_location'],
                implode(' > ', $row['organizational_units']),
                count($row['groups']),
                implode(', ', $row['fallback_evidence']),
            ], $report['rows'])
        );
        $this->newLine();
        $this->warn('OU, group, company, and office values are evidence only. Approve explicit mappings before using them for FMCS access.');

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

        fputcsv($handle, [
            'username', 'display_name', 'employee_id', 'country', 'country_abbreviation',
            'directory_company', 'office_location', 'distinguished_name',
            'organizational_units', 'groups', 'account_status', 'country_status', 'fallback_evidence',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['username'],
                $row['display_name'],
                $row['employee_id'],
                $row['country'],
                $row['country_abbreviation'],
                $row['directory_company'],
                $row['office_location'],
                $row['distinguished_name'],
                implode('; ', $row['organizational_units']),
                implode('; ', $row['groups']),
                $row['account_status'],
                $row['country_status'],
                implode('; ', $row['fallback_evidence']),
            ]);
        }

        fclose($handle);
    }
}
