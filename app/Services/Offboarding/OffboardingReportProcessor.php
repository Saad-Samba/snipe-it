<?php

namespace App\Services\Offboarding;

use App\Models\Asset;
use App\Models\LicenseSeat;
use App\Models\RegionalAssetCoordinatorAssignment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class OffboardingReportProcessor
{
    private const REQUIRED_HEADERS = [
        'DateDisabled',
        'SamAccountName',
        'DisplayName',
        'Email',
        'EmployeeID',
    ];

    private const NON_PERSON_PREFIXES = ['admin', 'gmbx-', 'svc-', 'svc_'];

    public function process(string $csvPath): array
    {
        [$accounts, $skipped] = $this->readAccounts($csvPath);
        [$usersById, $identityIndexes] = $this->buildUserIndexes();
        [$racsByScope, $racsByCompany] = $this->buildRacIndexes();
        $administratorRecipients = $this->administratorAlertRecipients();

        $reviews = [];
        $notifications = [];
        $routedObligations = 0;

        foreach ($accounts as $account) {
            $match = $this->matchUser($account, $usersById, $identityIndexes);
            $review = [
                'source_row' => $account['source_row'],
                'date_disabled' => $account['date_disabled'],
                'source_username' => $account['username'],
                'source_email' => $account['email'],
                'employee_id' => $account['employee_id'],
                'display_name' => $account['display_name'],
                'match_status' => $match['status'],
                'match_reason' => $match['reason'],
                'leams_user_id' => null,
                'leams_username' => null,
                'obligation_count' => 0,
                'recipients' => [],
                'routing_warnings' => [],
            ];

            if (! isset($match['user'])) {
                $reviews[] = $review;
                continue;
            }

            /** @var User $user */
            $user = $match['user'];
            $review['leams_user_id'] = (int) $user->id;
            $review['leams_username'] = $user->username;
            $obligations = $this->obligationsFor($user);
            $review['obligation_count'] = count($obligations);

            foreach ($obligations as $obligation) {
                [$recipients, $warning] = $this->routeObligation(
                    $obligation,
                    $racsByScope,
                    $racsByCompany
                );

                if ($warning !== '') {
                    $review['routing_warnings'][] = $warning;
                    $recipients = $administratorRecipients;
                }

                if (! empty($recipients)) {
                    $routedObligations++;
                }

                foreach ($recipients as $recipient) {
                    $notifications[$recipient] ??= [];
                    $notifications[$recipient][] = [
                        'user_id' => (int) $user->id,
                        'user_name' => $user->display_name ?: $user->getFullNameAttribute(),
                        'username' => $user->username,
                        'date_disabled' => $account['date_disabled'],
                        'routing_warning' => $warning ?: null,
                        ...$obligation,
                    ];
                }

                $review['recipients'] = array_values(array_unique(array_merge(
                    $review['recipients'],
                    $recipients
                )));
            }

            sort($review['recipients']);
            $reviews[] = $review;
        }

        ksort($notifications);

        return [
            'summary' => [
                'candidate_accounts' => count($accounts),
                'skipped_non_person_accounts' => count($skipped),
                'matched_users' => collect($reviews)->where('match_status', 'matched')->count(),
                'unresolved_users' => collect($reviews)->where('match_status', '!=', 'matched')->count(),
                'users_with_obligations' => collect($reviews)->where('obligation_count', '>', 0)->count(),
                'obligations' => collect($reviews)->sum('obligation_count'),
                'routed_obligations' => $routedObligations,
                'routing_warnings' => collect($reviews)->sum(
                    fn (array $review) => count($review['routing_warnings'])
                ),
                'notification_recipients' => count($notifications),
            ],
            'reviews' => $reviews,
            'skipped' => $skipped,
            'notifications' => $notifications,
        ];
    }

    private function readAccounts(string $csvPath): array
    {
        if (! is_file($csvPath) || ! is_readable($csvPath)) {
            throw new InvalidArgumentException("CSV file is not readable: {$csvPath}");
        }

        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException("Unable to open CSV file: {$csvPath}");
        }

        try {
            $headers = fgetcsv($handle);
            if ($headers === false) {
                throw new InvalidArgumentException('CSV file is empty.');
            }
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
            $missing = array_values(array_diff(self::REQUIRED_HEADERS, $headers));
            if (! empty($missing)) {
                throw new InvalidArgumentException(
                    'CSV is missing required headers: '.implode(', ', $missing)
                );
            }

            $accounts = [];
            $skipped = [];
            $sourceRow = 1;
            while (($values = fgetcsv($handle)) !== false) {
                $sourceRow++;
                if (count($values) === 1 && trim((string) $values[0]) === '') {
                    continue;
                }
                if (count($values) !== count($headers)) {
                    throw new InvalidArgumentException(
                        "CSV row {$sourceRow} has ".count($values).' columns; expected '.count($headers).'.'
                    );
                }

                $row = array_combine($headers, $values);
                $account = [
                    'source_row' => $sourceRow,
                    'date_disabled' => trim((string) ($row['DateDisabled'] ?? '')),
                    'username' => trim((string) ($row['SamAccountName'] ?? '')),
                    'display_name' => trim((string) ($row['DisplayName'] ?? '')),
                    'email' => trim((string) ($row['Email'] ?? '')),
                    'employee_id' => trim((string) ($row['EmployeeID'] ?? '')),
                ];
                $reason = $this->nonPersonReason(
                    $account,
                    trim((string) ($row['Country'] ?? '')),
                    trim((string) ($row['Region'] ?? ''))
                );
                if ($reason !== '') {
                    $skipped[] = [
                        'source_row' => $sourceRow,
                        'username' => $account['username'],
                        'display_name' => $account['display_name'],
                        'reason' => $reason,
                    ];
                    continue;
                }
                $accounts[] = $account;
            }
        } finally {
            fclose($handle);
        }

        return [$accounts, $skipped];
    }

    private function nonPersonReason(array $account, string $country, string $region): string
    {
        $username = $this->normalize($account['username']);
        foreach (self::NON_PERSON_PREFIXES as $prefix) {
            if (str_starts_with($username, $prefix)) {
                return 'known non-person account prefix';
            }
        }
        if ($this->normalize($region) === 'administrator groups'
            || $this->normalize($country) === 'plant site administrators') {
            return 'administrator account classification';
        }
        if ($account['email'] === '' && $account['employee_id'] === '') {
            return 'no person identifier (email or employee ID)';
        }

        return '';
    }

    private function buildUserIndexes(): array
    {
        $users = User::withTrashed()
            ->with('company')
            ->get(['id', 'username', 'email', 'employee_num', 'first_name', 'last_name', 'display_name', 'company_id']);
        $usersById = $users->keyBy(fn (User $user) => (int) $user->id);
        $indexes = [
            'employee ID' => $this->identityIndex($users, 'employee_num'),
            'email' => $this->identityIndex($users, 'email'),
            'username' => $this->identityIndex($users, 'username'),
        ];

        return [$usersById, $indexes];
    }

    private function identityIndex(Collection $users, string $attribute): array
    {
        $index = [];
        foreach ($users as $user) {
            $value = $this->normalize($user->{$attribute});
            if ($value === '') {
                continue;
            }
            $index[$value] ??= [];
            $index[$value][] = (int) $user->id;
        }

        return $index;
    }

    private function matchUser(array $account, Collection $usersById, array $indexes): array
    {
        $values = [
            'employee ID' => $this->normalize($account['employee_id']),
            'email' => $this->normalize($account['email']),
            'username' => $this->normalize($account['username']),
        ];
        $evidence = [];
        foreach ($values as $label => $value) {
            if ($value !== '' && ! empty($indexes[$label][$value])) {
                $evidence[$label] = $indexes[$label][$value];
            }
        }

        if ($evidence === []) {
            return [
                'status' => 'not_found',
                'reason' => 'no exact employee ID, email, or username match',
            ];
        }

        $candidateIds = array_values(array_unique(array_merge(...array_values($evidence))));
        if (count($candidateIds) !== 1) {
            return [
                'status' => 'ambiguous',
                'reason' => 'identifiers resolve to multiple LEAMS users ('.implode(', ', array_keys($evidence)).')',
            ];
        }

        $userId = $candidateIds[0];
        $matchedBy = array_keys(array_filter(
            $evidence,
            fn (array $ids) => in_array($userId, $ids, true)
        ));

        return [
            'status' => 'matched',
            'reason' => 'exact '.implode(', ', $matchedBy).' match',
            'user' => $usersById->get($userId),
        ];
    }

    private function obligationsFor(User $user): array
    {
        $obligations = [];
        $assets = Asset::query()
            ->where('assigned_type', User::class)
            ->where('assigned_to', $user->id)
            ->with(['company', 'discipline'])
            ->get();
        foreach ($assets as $asset) {
            $company = $asset->company ?: $user->company;
            $obligations[] = [
                'kind' => 'asset',
                'item_id' => (int) $asset->id,
                'item_name' => $asset->name ?: $asset->asset_tag,
                'item_reference' => $asset->asset_tag,
                'company_id' => $company?->id,
                'company_name' => $company?->name,
                'discipline_id' => $asset->discipline?->id,
                'discipline_name' => $asset->discipline?->name,
            ];
        }

        $seats = LicenseSeat::query()
            ->where('assigned_to', $user->id)
            ->with(['license.company', 'license.discipline'])
            ->get();
        foreach ($seats as $seat) {
            if (! $seat->license) {
                continue;
            }
            $license = $seat->license;
            $company = $license->company ?: $user->company;
            $obligations[] = [
                'kind' => 'license',
                'item_id' => (int) $license->id,
                'item_name' => $license->name,
                'item_reference' => 'Seat #'.$seat->id,
                'company_id' => $company?->id,
                'company_name' => $company?->name,
                'discipline_id' => $license->discipline?->id,
                'discipline_name' => $license->discipline?->name,
            ];
        }

        return $obligations;
    }

    private function buildRacIndexes(): array
    {
        $byScope = [];
        $byCompany = [];
        $assignments = RegionalAssetCoordinatorAssignment::query()
            ->with(['coordinator', 'company', 'discipline'])
            ->get();

        foreach ($assignments as $assignment) {
            $coordinator = $assignment->coordinator;
            if (! $coordinator || $coordinator->trashed() || ! $coordinator->activated || ! $coordinator->email) {
                continue;
            }
            $email = trim($coordinator->email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            $companyId = (int) $assignment->company_id;
            $disciplineId = (int) $assignment->discipline_id;
            $byCompany[$companyId] ??= [];
            $byCompany[$companyId][$this->normalize($email)] = $email;
            $scopeKey = $companyId.':'.$disciplineId;
            $byScope[$scopeKey] ??= [];
            $byScope[$scopeKey][$this->normalize($email)] = $email;
        }

        return [$byScope, $byCompany];
    }

    private function administratorAlertRecipients(): array
    {
        $settings = Setting::getSettings();
        if (! $settings?->alerts_enabled || empty($settings->alert_email) || config('app.lock_passwords')) {
            return [];
        }

        return collect(explode(',', $settings->alert_email))
            ->map(fn (string $email) => trim($email))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique(fn (string $email) => $this->normalize($email))
            ->values()
            ->all();
    }

    private function routeObligation(array $obligation, array $byScope, array $byCompany): array
    {
        $companyId = (int) ($obligation['company_id'] ?? 0);
        $disciplineId = (int) ($obligation['discipline_id'] ?? 0);
        $reference = $obligation['kind'].' #'.$obligation['item_id'];
        if ($companyId === 0) {
            return [[], "{$reference} has no company"];
        }
        if ($disciplineId !== 0) {
            $recipients = array_values($byScope[$companyId.':'.$disciplineId] ?? []);
            if ($recipients !== []) {
                return [$recipients, ''];
            }

            return [[], 'no active RAC for '.($obligation['company_name'] ?: 'company #'.$companyId)
                .' / '.($obligation['discipline_name'] ?: 'discipline #'.$disciplineId)];
        }

        $companyRecipients = array_values($byCompany[$companyId] ?? []);
        if (count($companyRecipients) === 1) {
            return [$companyRecipients, ''];
        }
        if ($companyRecipients === []) {
            return [[], 'no active RAC for '.($obligation['company_name'] ?: 'company #'.$companyId)];
        }

        return [[], "{$reference} has no discipline and "
            .($obligation['company_name'] ?: 'company #'.$companyId).' has multiple RACs'];
    }

    private function normalize(mixed $value): string
    {
        return mb_strtolower(trim((string) ($value ?? '')));
    }
}
