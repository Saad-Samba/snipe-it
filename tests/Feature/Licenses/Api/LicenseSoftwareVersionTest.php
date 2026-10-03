<?php

namespace Tests\Feature\Licenses\Api;

use App\Models\Category;
use App\Models\Company;
use App\Models\License;
use App\Models\SoftwareModel;
use App\Models\User;
use Tests\TestCase;

class LicenseSoftwareVersionTest extends TestCase
{
    public function testCanStoreLicenseWithSoftwareVersion()
    {
        $category = Category::factory()->forLicenses()->create();
        $company = Company::factory()->create();

        $this->actingAsForApi(User::factory()->createLicenses()->create())
            ->postJson(route('api.licenses.store'), [
                'name' => 'Versioned API License',
                'software_version' => 'R2026b',
                'seats' => 1,
                'category_id' => $category->id,
                'company_id' => $company->id,
                'expiration_date' => now()->addYear()->format('Y-m-d'),
                'last_physical_verification_date' => now()->format('Y-m-d'),
                'perpetual' => true,
                'serial' => 'PK-API-SOFTWARE-VERSION',
            ])
            ->assertStatusMessageIs('success');

        $this->assertDatabaseHas('licenses', [
            'name' => 'Versioned API License',
            'software_version' => 'R2026b',
        ]);
    }

    public function testCanUpdateLicenseSoftwareVersion()
    {
        $license = License::factory()->create([
            'company_id' => Company::factory()->create()->id,
            'software_version' => '2025.1',
        ]);

        $this->actingAsForApi(User::factory()->editLicenses()->create())
            ->patchJson(route('api.licenses.update', $license), [
                'software_version' => '2026.2',
            ])
            ->assertStatusMessageIs('success');

        $this->assertSame('2026.2', $license->fresh()->software_version);
    }

    public function testSoftwareModelSetsCanonicalLicenseMetadata()
    {
        $softwareModel = SoftwareModel::factory()->create(['name' => 'Davinci Developer Classic']);

        $this->actingAsForApi(User::factory()->createLicenses()->create())
            ->postJson(route('api.licenses.store'), [
                'name' => 'Unnormalized API name',
                'software_model_id' => $softwareModel->id,
                'seats' => 1,
                'category_id' => Category::factory()->forLicenses()->create()->id,
                'perpetual' => true,
            ])
            ->assertStatusMessageIs('success');

        $this->assertDatabaseHas('licenses', [
            'software_model_id' => $softwareModel->id,
            'name' => 'Davinci Developer Classic',
            'category_id' => $softwareModel->category_id,
            'manufacturer_id' => $softwareModel->manufacturer_id,
        ]);
    }
}
