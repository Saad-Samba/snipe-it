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
}
