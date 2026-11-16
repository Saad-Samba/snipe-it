<?php

namespace Tests\Feature\Licenses\Ui;

use App\Models\Category;
use App\Models\License;
use App\Models\User;
use Tests\TestCase;

class UpdateLicenseTest extends TestCase
{
    public function testPageRenders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('licenses.edit', License::factory()->create()->id))
            ->assertOk();
    }

    public function testCanUncheckMaintenanceOnMaintainedPerpetualLicense(): void
    {
        $license = License::factory()->create([
            'perpetual' => true,
            'maintained' => true,
            'maintenance_expires_at' => '2027-12-31',
        ]);
        $this->actingAs(User::factory()->superuser()->create())
            ->patch(route('licenses.update', $license), [
                'name' => $license->name,
                'seats' => $license->seats,
                'category_id' => $license->category_id,
                'perpetual' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $license->refresh();
        $this->assertFalse((bool) $license->maintained);
        $this->assertTrue($license->perpetual);
        $this->assertNull($license->maintenance_expires_at);
    }

    public function testCanUncheckPerpetualWhileKeepingMaintenance(): void
    {
        $license = License::factory()->create([
            'perpetual' => true,
            'maintained' => true,
            'maintenance_expires_at' => '2027-12-31',
        ]);
        $this->actingAs(User::factory()->superuser()->create())
            ->patch(route('licenses.update', $license), [
                'name' => $license->name,
                'seats' => $license->seats,
                'category_id' => $license->category_id,
                'expiration_date' => '2028-12-31',
                'maintained' => '1',
                'maintenance_expires_at' => '2027-12-31',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $license->refresh();
        $this->assertFalse($license->perpetual);
        $this->assertTrue((bool) $license->maintained);
        $this->assertSame('2028-12-31', $license->expiration_date->toDateString());
        $this->assertSame('2027-12-31', $license->maintenance_expires_at->toDateString());
    }

    public function testCanUpdateLicenseSeats()
    {
        $admin = User::factory()->superuser()->create();
        $license_category = Category::factory()->forLicenses()->create()->id;
        $response = $this->actingAs($admin)
            ->from(route('licenses.create'))
            ->post(route('licenses.store'), [
                'name' => 'Test Update License',
                'seats' => '9999',
                'category_id' => $license_category,
                'expiration_date' => now()->addYear()->format('Y-m-d'),
            ]);
        $response->assertStatus(302);
        $license = License::where('name', 'Test Update License')->sole();
        $this->assertNotNull($license);

        $this->actingAs($admin)
            ->put(route('licenses.update', $license->id), [
                'name' => 'Test Update License',
                'seats' => '19999',
                'category_id' => $license_category,
                'expiration_date' => now()->addYear()->format('Y-m-d'),
                'serial_number' => 'LIC-SN-2001',
                'software_version' => '2027 R2',
            ])
            ->assertStatus(302);

        $license->refresh();
        $this->assertEquals($license->licenseseats()->count(), $license->seats);
        $this->assertEquals($license->licenseseats()->count(), 19999);
        $this->assertSame('LIC-SN-2001', $license->serial_number);
        $this->assertSame('2027 R2', $license->software_version);
    }

    public function testCannotUpdateLicenseSeatsTooMuch()
    {
        $admin = User::factory()->superuser()->create();
        $license_category = Category::factory()->forLicenses()->create()->id;
        $response = $this->actingAs($admin)
            ->from(route('licenses.create'))
            ->post(route('licenses.store'), [
                'name' => 'Test Update License',
                'seats' => '9999',
                'category_id' => $license_category,
                'expiration_date' => now()->addYear()->format('Y-m-d'),
            ]);
        $response->assertStatus(302);
        $license = License::where('name', 'Test Update License')->sole();
        $this->assertNotNull($license);

        $this->actingAs($admin)
            ->put(route('licenses.update', $license->id), [
                'name' => 'Test Update License',
                'seats' => '29999',
                'category_id' => $license_category,
                'expiration_date' => now()->addYear()->format('Y-m-d'),
            ])
            ->assertStatus(302);

        $license->refresh();
        $this->assertEquals($license->licenseseats()->count(), $license->seats);
        $this->assertEquals($license->licenseseats()->count(), 9999);
    }

    public function testCanMarkLicensePerpetualAndClearExpirationDate()
    {
        $admin = User::factory()->superuser()->create();
        $license = License::factory()->create([
            'expiration_date' => now()->addMonth()->format('Y-m-d'),
            'perpetual' => false,
        ]);

        $this->actingAs($admin)
            ->put(route('licenses.update', $license->id), [
                'name' => $license->name,
                'seats' => $license->seats,
                'category_id' => $license->category_id,
                'perpetual' => '1',
            ])
            ->assertStatus(302);

        $license->refresh();
        $this->assertTrue($license->perpetual);
        $this->assertNull($license->expiration_date);
    }

    public function testCanRemoveLicenseSeats()
    {
        $admin = User::factory()->superuser()->create();
        $license_category = Category::factory()->forLicenses()->create()->id;
        $response = $this->actingAs($admin)
            ->from(route('licenses.create'))
            ->post(route('licenses.store'), [
                'name' => 'Test Remove License Seats',
                'seats' => '9999',
                'category_id' => $license_category,
                'expiration_date' => now()->addYear()->format('Y-m-d'),
            ]);
        $response->assertStatus(302);
        $license = License::where('name', 'Test Remove License Seats')->sole();
        $this->assertNotNull($license);

        $this->actingAs($admin)
            ->put(route('licenses.update', $license->id), [
                'name' => 'Test Remove License Seats',
                'seats' => '5000',
                'category_id' => $license_category,
                'expiration_date' => now()->addYear()->format('Y-m-d'),
            ])
            ->assertStatus(302);

        $license->refresh();
        $this->assertEquals($license->licenseseats()->count(), $license->seats);
        $this->assertEquals($license->licenseseats()->count(), 5000);
    }


}
