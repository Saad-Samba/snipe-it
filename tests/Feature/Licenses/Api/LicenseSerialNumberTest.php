<?php

namespace Tests\Feature\Licenses\Api;

use App\Models\Category;
use App\Models\License;
use App\Models\User;
use Tests\TestCase;

class LicenseSerialNumberTest extends TestCase
{
    public function testCanStoreLicenseWithSerialNumber()
    {
        $category = Category::factory()->forLicenses()->create();

        $this->actingAsForApi(User::factory()->createLicenses()->create())
            ->postJson(route('api.licenses.store'), [
                'name' => 'API License',
                'seats' => 1,
                'category_id' => $category->id,
                'perpetual' => true,
                'serial' => 'PK-API-STORE',
                'serial_number' => 'SN-API-STORE',
            ])
            ->assertStatusMessageIs('success');

        $this->assertDatabaseHas('licenses', [
            'name' => 'API License',
            'serial' => 'PK-API-STORE',
            'serial_number' => 'SN-API-STORE',
        ]);
    }

    public function testCanUpdateLicenseSerialNumber()
    {
        $license = License::factory()->create(['serial_number' => 'SN-API-OLD']);

        $this->actingAsForApi(User::factory()->editLicenses()->create())
            ->patchJson(route('api.licenses.update', $license), [
                'serial_number' => 'SN-API-NEW',
            ])
            ->assertStatusMessageIs('success');

        $this->assertSame('SN-API-NEW', $license->fresh()->serial_number);
    }
}
