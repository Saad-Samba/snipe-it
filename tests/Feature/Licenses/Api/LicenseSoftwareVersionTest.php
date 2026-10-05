<?php

namespace Tests\Feature\Licenses\Api;

use App\Models\Category;
use App\Models\License;
use App\Models\User;
use Tests\TestCase;

class LicenseSoftwareVersionTest extends TestCase
{
    public function testCanStoreLicenseWithSoftwareVersion()
    {
        $category = Category::factory()->forLicenses()->create();

        $this->actingAsForApi(User::factory()->createLicenses()->create())
            ->postJson(route('api.licenses.store'), [
                'name' => 'Versioned API License',
                'software_version' => 'R2026b',
                'seats' => 1,
                'category_id' => $category->id,
                'perpetual' => true,
            ])
            ->assertStatusMessageIs('success');

        $this->assertDatabaseHas('licenses', [
            'name' => 'Versioned API License',
            'software_version' => 'R2026b',
        ]);
    }

    public function testCanUpdateLicenseSoftwareVersion()
    {
        $license = License::factory()->create(['software_version' => '2025.1']);

        $this->actingAsForApi(User::factory()->editLicenses()->create())
            ->patchJson(route('api.licenses.update', $license), [
                'software_version' => '2026.2',
            ])
            ->assertStatusMessageIs('success');

        $this->assertSame('2026.2', $license->fresh()->software_version);
    }

    public function testMaintainedPerpetualLicenseRequiresMaintenanceExpiryDate(): void
    {
        $category = Category::factory()->forLicenses()->create();
        $user = User::factory()->createLicenses()->create();

        $this->actingAsForApi($user)
            ->postJson(route('api.licenses.store'), [
                'name' => 'Missing Maintenance Date',
                'seats' => 1,
                'category_id' => $category->id,
                'perpetual' => true,
                'maintained' => true,
            ])
            ->assertStatusMessageIs('error')
            ->assertJsonPath('messages.maintenance_expires_at.0', 'The maintenance expires at field is required.');

        $this->actingAsForApi($user)
            ->postJson(route('api.licenses.store'), [
                'name' => 'API Maintenance Date',
                'seats' => 1,
                'category_id' => $category->id,
                'perpetual' => true,
                'maintained' => true,
                'maintenance_expires_at' => '2027-12-31',
            ])
            ->assertStatusMessageIs('success');

        $this->assertDatabaseHas('licenses', [
            'name' => 'API Maintenance Date',
            'maintenance_expires_at' => '2027-12-31',
        ]);
    }
}
