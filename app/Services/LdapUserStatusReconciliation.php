<?php

namespace App\Services;

class LdapUserStatusReconciliation
{
    public function build(array $ldapResults, iterable $leamsUsers, array $attributes): array
    {
        $directoryUsers = $this->directoryUsers($ldapResults, $attributes);
        $indexes = $this->indexes($directoryUsers);
        $rows = [];

        foreach ($leamsUsers as $leamsUser) {
            $matches = [];
            $matchBasis = [];

            foreach (['employee_id', 'username', 'email'] as $identity) {
                $value = $this->normalize($leamsUser[$identity] ?? '');
                if ($value === '') {
                    continue;
                }

                foreach ($indexes[$identity][$value] ?? [] as $directoryIndex) {
                    $matches[$directoryIndex] = $directoryUsers[$directoryIndex];
                    $matchBasis[$identity] = true;
                }
            }

            $directoryUser = count($matches) === 1 ? reset($matches) : null;
            [$classification, $recommendedAction] = $this->classify(
                $leamsUser,
                $directoryUser,
                count($matches) > 1
            );

            $rows[] = array_merge([
                'classification' => $classification,
                'recommended_action' => $recommendedAction,
                'review_decision' => '',
                'match_basis' => implode(';', array_keys($matchBasis)),
            ], $leamsUser, [
                'assigned_inventory' => $this->assignedInventory($leamsUser),
                'ad_username' => $directoryUser['username'] ?? '',
                'ad_employee_id' => $directoryUser['employee_id'] ?? '',
                'ad_email' => $directoryUser['email'] ?? '',
                'ad_display_name' => $directoryUser['display_name'] ?? '',
                'ad_account_status' => $directoryUser['account_status'] ?? '',
                'ad_distinguished_name' => $directoryUser['distinguished_name'] ?? '',
            ]);
        }

        $classifications = array_count_values(array_column($rows, 'classification'));
        ksort($classifications);

        return [
            'summary' => [
                'total_leams_users' => count($rows),
                'total_ad_users' => count($directoryUsers),
                'disabled_in_ad_active_in_leams' => $classifications['ad_disabled_leams_active'] ?? 0,
                'disabled_candidates_with_inventory' => count(array_filter(
                    $rows,
                    fn ($row) => $row['classification'] === 'ad_disabled_leams_active'
                        && $row['assigned_inventory'] > 0
                )),
                'not_found_in_ad' => $classifications['not_found_in_ad'] ?? 0,
                'ambiguous_matches' => $classifications['ambiguous_match'] ?? 0,
                'local_or_service_accounts' => $classifications['local_or_service_account'] ?? 0,
            ],
            'classifications' => $classifications,
            'rows' => $rows,
        ];
    }

    public function requiresReview(array $row): bool
    {
        if ($row['classification'] === 'ad_disabled_leams_inactive') {
            return $row['assigned_inventory'] > 0;
        }

        return ! in_array($row['classification'], [
            'ad_enabled_leams_active',
            'ad_disabled_leams_inactive',
            'local_or_service_account',
        ], true);
    }

    private function directoryUsers(array $ldapResults, array $attributes): array
    {
        $users = [];
        $count = (int) ($ldapResults['count'] ?? 0);

        for ($index = 0; $index < $count; $index++) {
            if (! isset($ldapResults[$index]) || ! is_array($ldapResults[$index])) {
                continue;
            }

            $entry = array_change_key_case($ldapResults[$index], CASE_LOWER);
            $users[] = [
                'username' => $this->firstValue($entry, $attributes['username']),
                'employee_id' => $this->firstValue($entry, $attributes['employee_id']),
                'email' => $this->firstValue($entry, $attributes['email']),
                'display_name' => $this->firstValue($entry, $attributes['display_name']),
                'account_status' => $this->accountStatus($this->firstValue($entry, 'useraccountcontrol')),
                'distinguished_name' => $this->firstValue($entry, 'distinguishedname'),
            ];
        }

        return $users;
    }

    private function indexes(array $directoryUsers): array
    {
        $indexes = ['employee_id' => [], 'username' => [], 'email' => []];

        foreach ($directoryUsers as $index => $directoryUser) {
            foreach (array_keys($indexes) as $identity) {
                $value = $this->normalize($directoryUser[$identity]);
                if ($value !== '') {
                    $indexes[$identity][$value][] = $index;
                }
            }
        }

        return $indexes;
    }

    private function classify(array $leamsUser, ?array $directoryUser, bool $ambiguous): array
    {
        if ($ambiguous) {
            return ['ambiguous_match', 'Review conflicting identity evidence manually'];
        }

        if ($directoryUser === null) {
            if (! ($leamsUser['ldap_import'] ?? false)) {
                return ['local_or_service_account', 'Confirm this is an approved local or service account'];
            }

            return ['not_found_in_ad', 'Review identity and LDAP search scope before taking action'];
        }

        if ($directoryUser['account_status'] === 'unknown') {
            return ['unknown_ad_status', 'Review the AD userAccountControl value manually'];
        }

        if ($directoryUser['account_status'] === 'disabled') {
            if ($leamsUser['activated'] ?? false) {
                return ['ad_disabled_leams_active', 'Review for LEAMS deactivation and inventory recovery'];
            }

            return ['ad_disabled_leams_inactive', 'No account action; verify any assigned inventory'];
        }

        if ($leamsUser['activated'] ?? false) {
            return ['ad_enabled_leams_active', 'No account action'];
        }

        return ['ad_enabled_leams_inactive', 'Review whether LEAMS access should be restored'];
    }

    private function assignedInventory(array $user): int
    {
        return array_sum(array_map(
            fn ($field) => (int) ($user[$field] ?? 0),
            ['assets_count', 'licenses_count', 'accessories_count', 'consumables_count']
        ));
    }

    private function firstValue(array $entry, ?string $attribute): string
    {
        if (! $attribute) {
            return '';
        }

        $value = $entry[strtolower($attribute)] ?? null;
        if ($value === null) {
            return '';
        }

        $values = is_array($value) ? $value : [$value];
        unset($values['count']);

        foreach ($values as $item) {
            if (is_scalar($item) && trim((string) $item) !== '') {
                return trim((string) $item);
            }
        }

        return '';
    }

    private function accountStatus(string $userAccountControl): string
    {
        if ($userAccountControl === '' || ! is_numeric($userAccountControl)) {
            return 'unknown';
        }

        return (((int) $userAccountControl & 2) === 2) ? 'disabled' : 'active';
    }

    private function normalize(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }
}
