<?php

namespace Tests\Feature\Licenses\Api;

use App\Models\Company;
use App\Models\License;
use App\Models\User;
use Tests\TestCase;

class LicenseIndexTest extends TestCase
{
    public function testLicensesCanBeFilteredAndSortedBySoftwareVersion()
    {
        $licenseB = License::factory()->create(['software_version' => 'B']);
        $licenseA = License::factory()->create(['software_version' => 'A']);
        $user = User::factory()->superuser()->create();

        $this->actingAsForApi($user)
            ->getJson(route('api.licenses.index', ['software_version' => 'A']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.id', $licenseA->id)
            ->assertJsonPath('rows.0.software_version', 'A');

        $this->actingAsForApi($user)
            ->getJson(route('api.licenses.index', ['sort' => 'software_version', 'order' => 'asc']))
            ->assertOk()
            ->assertJsonPath('rows.0.id', $licenseA->id)
            ->assertJsonPath('rows.1.id', $licenseB->id);
    }

    public function testLicensesCanBeFilteredAndSortedBySerialNumber()
    {
        $licenseB = License::factory()->create(['serial_number' => 'SN-B']);
        $licenseA = License::factory()->create(['serial_number' => 'SN-A']);
        $user = User::factory()->superuser()->create();

        $this->actingAsForApi($user)
            ->getJson(route('api.licenses.index', ['serial_number' => 'SN-A']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.id', $licenseA->id)
            ->assertJsonPath('rows.0.serial_number', 'SN-A');

        $this->actingAsForApi($user)
            ->getJson(route('api.licenses.index', ['sort' => 'serial_number', 'order' => 'asc']))
            ->assertOk()
            ->assertJsonPath('rows.0.id', $licenseA->id)
            ->assertJsonPath('rows.1.id', $licenseB->id);
    }

    public function testLicensesIndexAdheresToCompanyScoping()
    {
        [$companyA, $companyB] = Company::factory()->count(2)->create();

        $licenseA = License::factory()->for($companyA)->create();
        $licenseB = License::factory()->for($companyB)->create();

        $superUser = $companyA->users()->save(User::factory()->superuser()->make());
        $userInCompanyA = $companyA->users()->save(User::factory()->viewLicenses()->make());
        $userInCompanyB = $companyB->users()->save(User::factory()->viewLicenses()->make());

        $this->settings->disableMultipleFullCompanySupport();

        $this->actingAsForApi($superUser)
            ->getJson(route('api.licenses.index'))
            ->assertResponseContainsInRows($licenseA)
            ->assertResponseContainsInRows($licenseB);

        $this->actingAsForApi($userInCompanyA)
            ->getJson(route('api.licenses.index'))
            ->assertResponseContainsInRows($licenseA)
            ->assertResponseContainsInRows($licenseB);

        $this->actingAsForApi($userInCompanyB)
            ->getJson(route('api.licenses.index'))
            ->assertResponseContainsInRows($licenseA)
            ->assertResponseContainsInRows($licenseB);

        $this->settings->enableMultipleFullCompanySupport();

        $this->actingAsForApi($superUser)
            ->getJson(route('api.licenses.index'))
            ->assertResponseContainsInRows($licenseA)
            ->assertResponseContainsInRows($licenseB);

        $this->actingAsForApi($userInCompanyA)
            ->getJson(route('api.licenses.index'))
            ->assertResponseContainsInRows($licenseA)
            ->assertResponseDoesNotContainInRows($licenseB);

        $this->actingAsForApi($userInCompanyB)
            ->getJson(route('api.licenses.index'))
            ->assertResponseDoesNotContainInRows($licenseA)
            ->assertResponseContainsInRows($licenseB);
    }
}
