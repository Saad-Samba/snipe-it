<?php

namespace Tests\Feature\Licenses\Ui;

use App\Models\Category;
use App\Models\License;
use App\Models\SoftwareModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ProvidesDataForFullMultipleCompanySupportTesting;
use Tests\TestCase;

class StoreLicenseWithFullMultipleCompanySupportTest extends TestCase
{
    use ProvidesDataForFullMultipleCompanySupportTesting;

    #[DataProvider('dataForFullMultipleCompanySupportTesting')]
    public function testAdheresToFullMultipleCompaniesSupportScoping($data)
    {
        ['actor' => $actor, 'company_attempting_to_associate' => $company, 'assertions' => $assertions] = $data();

        $this->settings->enableMultipleFullCompanySupport();

        $category = Category::factory()->forLicenses()->create();
        $softwareModel = SoftwareModel::factory()->create(['name' => 'My Cool License', 'category_id' => $category->id]);

        $response = $this->actingAs($actor)
            ->post(route('licenses.store'), [
                'software_model_id' => $softwareModel->id,
                'seats' => '1',
                'category_id' => $category->id,
                'company_id' => $company->id,
                'expiration_date' => now()->addYear()->format('Y-m-d'),
                'last_physical_verification_date' => now()->format('Y-m-d'),
                'serial' => 'LIC-PK-FMCS',
            ]);

        if (is_null($actor->company_id) && (! $actor->isSuperUser())) {
            $response->assertSessionHasErrors([
                'company_id' => 'The site field is required.',
            ]);
            $this->assertFalse(License::where('name', 'My Cool License')->exists());

            return;
        }

        $license = License::where('name', 'My Cool License')->sole();

        $assertions($license);
    }
}
