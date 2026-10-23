<?php

namespace Tests\Unit\Services;

use App\Services\LdapCompanyReadinessReport;
use PHPUnit\Framework\TestCase;

class LdapCompanyReadinessReportTest extends TestCase
{
    public function test_it_reports_country_coverage_and_fallback_evidence(): void
    {
        $results = [
            'count' => 3,
            0 => [
                'samaccountname' => ['alice', 'count' => 1],
                'displayname' => ['Alice Example', 'count' => 1],
                'co' => ['Spain', 'count' => 1],
                'c' => ['ES', 'count' => 1],
                'useraccountcontrol' => ['512', 'count' => 1],
            ],
            1 => [
                'samaccountname' => ['bob', 'count' => 1],
                'displayname' => ['Bob Example', 'count' => 1],
                'physicaldeliveryofficename' => ['Barcelona', 'count' => 1],
                'distinguishedname' => ['CN=Bob Example,OU=Barcelona,OU=Users,DC=example,DC=com', 'count' => 1],
                'memberof' => [
                    'CN=Spain Employees,OU=Groups,DC=example,DC=com',
                    'count' => 1,
                ],
                'useraccountcontrol' => ['512', 'count' => 1],
            ],
            2 => [
                'samaccountname' => ['service-account', 'count' => 1],
                'useraccountcontrol' => ['514', 'count' => 1],
            ],
        ];

        $report = (new LdapCompanyReadinessReport())->build($results, [
            'username' => 'samaccountname',
            'display_name' => 'displayname',
            'employee_id' => 'employeeid',
            'country' => 'co',
        ]);

        $this->assertSame([
            'total' => 3,
            'country_present' => 1,
            'country_missing' => 2,
            'active_country_missing' => 1,
            'missing_with_office' => 1,
            'missing_with_ou' => 1,
            'missing_with_groups' => 1,
            'missing_with_company' => 0,
            'unresolved' => 1,
        ], $report['summary']);
        $this->assertSame(['Barcelona', 'Users'], $report['rows'][1]['organizational_units']);
        $this->assertSame(['office', 'ou', 'groups'], $report['rows'][1]['fallback_evidence']);
        $this->assertSame([], $report['rows'][2]['fallback_evidence']);
        $this->assertSame('disabled', $report['rows'][2]['account_status']);
    }

    public function test_it_uses_the_configured_country_attribute_case_insensitively(): void
    {
        $report = (new LdapCompanyReadinessReport())->build([
            'count' => 1,
            0 => [
                'SAMACCOUNTNAME' => ['carol', 'count' => 1],
                'EXTENSIONATTRIBUTE10' => ['Morocco', 'count' => 1],
            ],
        ], [
            'username' => 'samaccountname',
            'display_name' => 'displayname',
            'employee_id' => 'employeeid',
            'country' => 'extensionattribute10',
        ]);

        $this->assertSame('Morocco', $report['rows'][0]['country']);
        $this->assertSame('present', $report['rows'][0]['country_status']);
    }
}
