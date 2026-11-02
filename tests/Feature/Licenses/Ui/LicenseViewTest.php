<?php

namespace Tests\Feature\Licenses\Ui;

use App\Models\License;
use App\Models\User;
use Tests\TestCase;

class LicenseViewTest extends TestCase
{
    public function testPermissionRequiredToViewLicense()
    {
        $license = License::factory()->create();
        $this->actingAs(User::factory()->create())
            ->get(route('licenses.show', $license))
            ->assertForbidden();
    }

    public function testPageRenders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('licenses.show', License::factory()->create()->id))
            ->assertOk();
    }
    
    public function testLicenseSerialNumberIsVisible()
    {
        $license = License::factory()->create(['serial_number' => 'LIC-SN-VIEW-001']);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('licenses.show', $license))
            ->assertOk()
            ->assertSee('LIC-SN-VIEW-001', false);
    }

    public function testSoftwareVersionIsVisible()
    {
        $license = License::factory()->create(['software_version' => 'R2026b']);

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('licenses.show', $license))
            ->assertOk()
            ->assertSee(trans('admin/licenses/form.software_version'))
            ->assertSee('R2026b', false);
    }
}
