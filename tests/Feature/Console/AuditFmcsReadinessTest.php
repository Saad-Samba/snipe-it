<?php

namespace Tests\Feature\Console;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Location;
use App\Models\User;
use App\Services\FmcsReadinessAuditor;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditFmcsReadinessTest extends TestCase
{
    public function testAuditReportsMissingSiteAssignmentsAndIgnoresInactiveUsers(): void
    {
        $activeUser = User::factory()->create(['company_id' => null, 'activated' => 1]);
        $inactiveUser = User::factory()->create(['company_id' => null, 'activated' => 0]);
        $asset = Asset::factory()->create(['company_id' => null]);

        $report = app(FmcsReadinessAuditor::class)->audit();

        $this->assertTrue(collect($report['findings'])->contains(
            fn (array $finding): bool =>
            $finding['code'] === 'missing_site'
            && $finding['resource_type'] === 'User'
            && $finding['resource_id'] === $activeUser->id
            && $finding['severity'] === 'blocking'
        ));
        $this->assertFalse(collect($report['findings'])->contains(
            fn (array $finding): bool =>
            $finding['resource_type'] === 'User' && $finding['resource_id'] === $inactiveUser->id
        ));
        $this->assertTrue(collect($report['findings'])->contains(
            fn (array $finding): bool =>
            $finding['code'] === 'missing_site'
            && $finding['resource_type'] === 'Asset'
            && $finding['resource_id'] === $asset->id
        ));
        $this->assertTrue(collect($report['coverage'])->contains(
            fn (array $row): bool =>
            $row['resource_type'] === 'User'
            && $row['site_id'] === null
            && $row['count'] === 1
        ));
        $this->assertGreaterThanOrEqual(2, $report['totals']['blocking']);
    }

    public function testAuditTreatsGlobalSuperuserAndLocationsAsNonBlocking(): void
    {
        $superuser = User::factory()->superuser()->create(['company_id' => null]);
        $location = Location::factory()->create(['company_id' => null]);

        $report = app(FmcsReadinessAuditor::class)->audit();
        $findings = collect($report['findings']);

        $this->assertTrue($findings->contains(
            fn (array $finding): bool =>
            $finding['resource_type'] === 'User'
            && $finding['resource_id'] === $superuser->id
            && $finding['severity'] === 'info'
            && $finding['context'] === 'superuser'
        ));
        $this->assertTrue($findings->contains(
            fn (array $finding): bool =>
            $finding['resource_type'] === 'Location'
            && $finding['resource_id'] === $location->id
            && $finding['severity'] === 'warning'
        ));
        $this->assertSame(0, $report['totals']['blocking']);
    }

    public function testAuditIdentifiesPrivilegedApiUserWithoutSite(): void
    {
        $user = User::factory()->create([
            'company_id' => null,
            'permissions' => '{"admin":"1"}',
        ]);
        DB::table('oauth_access_tokens')->insert([
            'id' => 'fmcs-readiness-token',
            'user_id' => $user->id,
            'client_id' => 1,
            'name' => 'Automation',
            'scopes' => '[]',
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        $report = app(FmcsReadinessAuditor::class)->audit();
        $finding = collect($report['findings'])->first(
            fn (array $finding): bool =>
            $finding['resource_type'] === 'User' && $finding['resource_id'] === $user->id
        );

        $this->assertSame('blocking', $finding['severity']);
        $this->assertSame('admin, API token owner', $finding['context']);
    }

    public function testAuditTreatsOrphanedSiteReferencesAsInvalid(): void
    {
        $asset = Asset::factory()->create();
        $missingCompanyId = Company::max('id') + 1000;
        DB::table('assets')->where('id', $asset->id)->update(['company_id' => $missingCompanyId]);

        $report = app(FmcsReadinessAuditor::class)->audit();

        $this->assertTrue(collect($report['findings'])->contains(
            fn (array $finding): bool =>
            $finding['code'] === 'invalid_site'
            && $finding['resource_type'] === 'Asset'
            && $finding['resource_id'] === $asset->id
            && $finding['site_id'] === $missingCompanyId
        ));
    }

    public function testAuditReportsAssetAssignmentAndLocationSiteMismatches(): void
    {
        $assetCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $assignedUser = User::factory()->create(['company_id' => $otherCompany->id]);
        $location = Location::factory()->create(['company_id' => $otherCompany->id]);
        $asset = Asset::factory()->assignedToUser($assignedUser)->create([
            'company_id' => $assetCompany->id,
            'location_id' => $location->id,
        ]);

        $report = app(FmcsReadinessAuditor::class)->audit();
        $findings = collect($report['findings']);

        $this->assertTrue($findings->contains(
            fn (array $finding): bool =>
            $finding['code'] === 'asset_assignment_site_mismatch'
            && $finding['resource_id'] === $asset->id
            && $finding['related_id'] === $assignedUser->id
            && $finding['severity'] === 'blocking'
        ));
        $this->assertTrue($findings->contains(
            fn (array $finding): bool =>
            $finding['code'] === 'location_site_mismatch'
            && $finding['resource_id'] === $asset->id
            && $finding['related_id'] === $location->id
            && $finding['severity'] === 'warning'
        ));
    }

    public function testCommandOutputsJsonAndFailsWhenBlockingGapsExist(): void
    {
        User::factory()->create(['company_id' => null]);

        $exitCode = Artisan::call('snipeit:audit-fmcs-readiness', ['--json' => true]);
        $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertGreaterThan(0, $report['totals']['blocking']);
        $this->assertNotEmpty($report['findings']);
    }

    public function testCommandSucceedsWhenOnlyNonBlockingFindingsExist(): void
    {
        User::factory()->superuser()->create(['company_id' => null]);

        $this->artisan('snipeit:audit-fmcs-readiness', ['--limit' => 0])
            ->assertExitCode(0);
    }

    public function testCommandWritesCompleteCsvBeforeReturningBlockingExitCode(): void
    {
        $user = User::factory()->create(['company_id' => null]);
        $path = sys_get_temp_dir().'/fmcs-readiness-'.Str::uuid().'.csv';

        try {
            $this->artisan('snipeit:audit-fmcs-readiness', ['--csv' => $path, '--limit' => 0])
                ->assertExitCode(1);

            $this->assertFileExists($path);
            $contents = file_get_contents($path);
            $this->assertStringContainsString('severity,code,resource_type,resource_id', $contents);
            $this->assertStringContainsString('blocking,missing_site,User,'.$user->id, $contents);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
