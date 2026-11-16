<?php

namespace Tests\Feature\Licenses\Api;

use App\Models\Company;
use App\Models\License;
use App\Models\Project;
use App\Models\User;
use Tests\TestCase;

class LicenseIndexTest extends TestCase
{
    public function testLicensesAreSortedByProjectNameAcrossPages()
    {
        $alpha = Project::factory()->create(['name' => 'Alpha Project']);
        $zulu = Project::factory()->create(['name' => 'Zulu Project']);
        $zuluLicense = License::factory()->create(['project_id' => $zulu->id]);
        $alphaLicense = License::factory()->create(['project_id' => $alpha->id]);
        $user = User::factory()->superuser()->create();

        $this->actingAsForApi($user)
            ->getJson(route('api.licenses.index', ['sort' => 'project', 'order' => 'asc', 'limit' => 1]))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('rows.0.id', $alphaLicense->id);

        app()->forgetInstance('api_offset_value');
        app()->forgetInstance('api_limit_value');

        $this->actingAsForApi($user)
            ->getJson(route('api.licenses.index', ['sort' => 'project', 'order' => 'asc', 'limit' => 1, 'offset' => 1]))
            ->assertOk()
            ->assertJsonPath('rows.0.id', $zuluLicense->id);

        app()->forgetInstance('api_offset_value');
        app()->forgetInstance('api_limit_value');

        $this->actingAsForApi($user)
            ->getJson(route('api.licenses.index', ['sort' => 'project', 'order' => 'desc']))
            ->assertOk()
            ->assertJsonPath('rows.0.id', $zuluLicense->id)
            ->assertJsonPath('rows.1.id', $alphaLicense->id);
    }

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
