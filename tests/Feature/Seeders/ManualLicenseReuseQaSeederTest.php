<?php

namespace Tests\Feature\Seeders;

use App\Models\Company;
use App\Models\Discipline;
use App\Models\License;
use App\Models\Project;
use App\Models\RegionalAssetCoordinatorAssignment;
use App\Models\User;
use Database\Seeders\ManualLicenseReuseQaSeeder;
use Tests\TestCase;

class ManualLicenseReuseQaSeederTest extends TestCase
{
    public function test_seeder_is_repeatable_and_builds_the_license_reuse_walkthrough(): void
    {
        $this->seed(ManualLicenseReuseQaSeeder::class);
        $this->seed(ManualLicenseReuseQaSeeder::class);

        $license = License::query()->where('name', 'QA Microsoft 365 Reuse Pool')->firstOrFail();
        $sourceCompany = Company::query()->where('name', 'QA License Source Company')->firstOrFail();
        $discipline = Discipline::query()->where('name', 'QA Software Discipline')->firstOrFail();
        $coordinator = User::query()->where('username', 'qa-license-rac')->firstOrFail();
        $requester = User::query()->where('username', 'qa-license-requester')->firstOrFail();

        $this->assertSame(3, $license->licenseSeats()->count());
        $this->assertSame(2, $license->availableReusableSeats()->count());
        $this->assertSame(1, $license->expectedReleaseSeatsByDate(now()->addDays(30)->toDateString())->count());
        $this->assertSame($sourceCompany->id, $license->company_id);
        $this->assertSame($discipline->id, $license->discipline_id);
        $this->assertTrue($requester->hasAccess('licenses.request'));
        $this->assertTrue($requester->hasAccess('models.request'));
        $this->assertTrue($coordinator->hasAccess('licenses.checkout'));
        $this->assertTrue(Project::query()->where('name', 'QA License Reuse Project')->exists());
        $this->assertTrue(RegionalAssetCoordinatorAssignment::query()
            ->where('user_id', $coordinator->id)
            ->where('company_id', $sourceCompany->id)
            ->where('discipline_id', $discipline->id)
            ->exists());
    }
}
