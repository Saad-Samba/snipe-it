<?php

namespace App\Services;

class LdapCompanyReadinessReport
{
    public function build(array $ldapResults, array $attributes): array
    {
        $rows = [];
        $count = (int) ($ldapResults['count'] ?? 0);

        for ($index = 0; $index < $count; $index++) {
            if (! isset($ldapResults[$index]) || ! is_array($ldapResults[$index])) {
                continue;
            }

            $entry = array_change_key_case($ldapResults[$index], CASE_LOWER);
            $country = $this->firstValue($entry, $attributes['country']);
            $distinguishedName = $this->firstValue($entry, 'distinguishedname');
            $groups = $this->values($entry, 'memberof');
            $office = $this->firstValue($entry, 'physicaldeliveryofficename');
            $directoryCompany = $this->firstValue($entry, 'company');
            $accountStatus = $this->accountStatus($this->firstValue($entry, 'useraccountcontrol'));
            $organizationalUnits = $this->organizationalUnits($distinguishedName);
            $fallbackEvidence = [];

            if ($country === '') {
                if ($office !== '') {
                    $fallbackEvidence[] = 'office';
                }
                if ($organizationalUnits !== []) {
                    $fallbackEvidence[] = 'ou';
                }
                if ($groups !== []) {
                    $fallbackEvidence[] = 'groups';
                }
                if ($directoryCompany !== '') {
                    $fallbackEvidence[] = 'company';
                }
            }

            $rows[] = [
                'username' => $this->firstValue($entry, $attributes['username']),
                'display_name' => $this->firstValue($entry, $attributes['display_name']),
                'employee_id' => $this->firstValue($entry, $attributes['employee_id']),
                'country' => $country,
                'country_abbreviation' => $this->firstValue($entry, 'c'),
                'directory_company' => $directoryCompany,
                'office_location' => $office,
                'distinguished_name' => $distinguishedName,
                'organizational_units' => $organizationalUnits,
                'groups' => $groups,
                'account_status' => $accountStatus,
                'country_status' => $country !== '' ? 'present' : 'missing',
                'fallback_evidence' => $fallbackEvidence,
            ];
        }

        return [
            'summary' => [
                'total' => count($rows),
                'country_present' => count(array_filter($rows, fn ($row) => $row['country_status'] === 'present')),
                'country_missing' => count(array_filter($rows, fn ($row) => $row['country_status'] === 'missing')),
                'active_country_missing' => count(array_filter(
                    $rows,
                    fn ($row) => $row['country_status'] === 'missing' && $row['account_status'] === 'active'
                )),
                'missing_with_office' => $this->missingWithEvidence($rows, 'office'),
                'missing_with_ou' => $this->missingWithEvidence($rows, 'ou'),
                'missing_with_groups' => $this->missingWithEvidence($rows, 'groups'),
                'missing_with_company' => $this->missingWithEvidence($rows, 'company'),
                'unresolved' => count(array_filter($rows, fn ($row) => $row['country_status'] === 'missing' && $row['fallback_evidence'] === [])),
            ],
            'rows' => $rows,
        ];
    }

    private function missingWithEvidence(array $rows, string $evidence): int
    {
        return count(array_filter(
            $rows,
            fn ($row) => $row['country_status'] === 'missing' && in_array($evidence, $row['fallback_evidence'], true)
        ));
    }

    private function firstValue(array $entry, ?string $attribute): string
    {
        return $this->values($entry, $attribute)[0] ?? '';
    }

    private function values(array $entry, ?string $attribute): array
    {
        if (! $attribute) {
            return [];
        }

        $value = $entry[strtolower($attribute)] ?? null;
        if ($value === null) {
            return [];
        }

        $values = is_array($value) ? $value : [$value];
        unset($values['count']);

        return array_values(array_filter(array_map(
            fn ($item) => is_scalar($item) ? trim((string) $item) : '',
            $values
        ), fn ($item) => $item !== ''));
    }

    private function organizationalUnits(string $distinguishedName): array
    {
        if ($distinguishedName === '') {
            return [];
        }

        preg_match_all('/(?:^|,)\s*OU=((?:\\\\.|[^,])*)/i', $distinguishedName, $matches);

        return array_values(array_filter(array_map(
            fn ($ou) => trim(str_replace(['\\,', '\\\\'], [',', '\\'], $ou)),
            $matches[1] ?? []
        )));
    }

    private function accountStatus(string $userAccountControl): string
    {
        if ($userAccountControl === '' || ! is_numeric($userAccountControl)) {
            return 'unknown';
        }

        return (((int) $userAccountControl & 2) === 2) ? 'disabled' : 'active';
    }
}
