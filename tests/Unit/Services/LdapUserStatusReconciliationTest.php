<?php

namespace Tests\Unit\Services;

use App\Services\LdapUserStatusReconciliation;
use PHPUnit\Framework\TestCase;

class LdapUserStatusReconciliationTest extends TestCase
{
    private array $attributes = [
        'username' => 'samaccountname',
        'display_name' => 'displayname',
        'employee_id' => 'employeeid',
        'email' => 'mail',
    ];

    public function test_it_flags_an_active_leams_user_disabled_in_ad(): void
    {
        $report = $this->service()->build($this->ldapResults([
            $this->adUser('514'),
        ]), [$this->leamsUser()], $this->attributes);

        $row = $report['rows'][0];
        $this->assertSame('ad_disabled_leams_active', $row['classification']);
        $this->assertSame('employee_id;username;email', $row['match_basis']);
        $this->assertSame(2, $row['assigned_inventory']);
        $this->assertSame(1, $report['summary']['disabled_in_ad_active_in_leams']);
        $this->assertSame(1, $report['summary']['disabled_candidates_with_inventory']);
        $this->assertTrue($this->service()->requiresReview($row));
    }

    public function test_it_separates_already_inactive_and_local_accounts(): void
    {
        $inactive = $this->leamsUser(['activated' => false]);
        $local = $this->leamsUser([
            'leams_user_id' => 11,
            'username' => 'local-admin',
            'employee_id' => '',
            'email' => '',
            'ldap_import' => false,
        ]);

        $report = $this->service()->build(
            $this->ldapResults([$this->adUser('514')]),
            [$inactive, $local],
            $this->attributes
        );

        $this->assertSame('ad_disabled_leams_inactive', $report['rows'][0]['classification']);
        $this->assertSame('local_or_service_account', $report['rows'][1]['classification']);
        $this->assertSame(1, $report['summary']['local_or_service_accounts']);
    }

    public function test_it_does_not_guess_when_exact_identifiers_conflict(): void
    {
        $second = $this->adUser('512');
        $second['samaccountname'] = ['someone-else', 'count' => 1];
        $second['employeeid'] = ['OTHER', 'count' => 1];
        $second['distinguishedname'] = ['CN=Someone Else,DC=example,DC=com', 'count' => 1];

        $report = $this->service()->build(
            $this->ldapResults([
                array_replace($this->adUser('512'), ['mail' => ['alice.other@example.com', 'count' => 1]]),
                $second,
            ]),
            [$this->leamsUser()],
            $this->attributes
        );

        $this->assertSame('ambiguous_match', $report['rows'][0]['classification']);
        $this->assertSame('', $report['rows'][0]['ad_username']);
        $this->assertSame(1, $report['summary']['ambiguous_matches']);
    }

    public function test_it_marks_an_imported_user_missing_from_ad_for_review(): void
    {
        $report = $this->service()->build(
            $this->ldapResults([]),
            [$this->leamsUser()],
            $this->attributes
        );

        $this->assertSame('not_found_in_ad', $report['rows'][0]['classification']);
        $this->assertSame(1, $report['summary']['not_found_in_ad']);
        $this->assertTrue($this->service()->requiresReview($report['rows'][0]));
    }

    private function service(): LdapUserStatusReconciliation
    {
        return new LdapUserStatusReconciliation();
    }

    private function ldapResults(array $users): array
    {
        return ['count' => count($users), ...$users];
    }

    private function adUser(string $userAccountControl): array
    {
        return [
            'samaccountname' => ['alice', 'count' => 1],
            'employeeid' => ['E123', 'count' => 1],
            'mail' => ['alice@example.com', 'count' => 1],
            'displayname' => ['Alice Example', 'count' => 1],
            'useraccountcontrol' => [$userAccountControl, 'count' => 1],
            'distinguishedname' => ['CN=Alice,OU=Users,DC=example,DC=com', 'count' => 1],
        ];
    }

    private function leamsUser(array $overrides = []): array
    {
        return array_replace([
            'leams_user_id' => 10,
            'username' => 'alice',
            'employee_id' => 'E123',
            'email' => 'alice@example.com',
            'display_name' => 'Alice Example',
            'activated' => true,
            'ldap_import' => true,
            'company' => 'VLS',
            'country' => 'Spain',
            'assets_count' => 1,
            'licenses_count' => 1,
            'accessories_count' => 0,
            'consumables_count' => 0,
        ], $overrides);
    }
}
