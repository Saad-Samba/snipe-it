<?php

namespace Tests\Feature\Licenses\Ui;

use App\Models\Category;
use App\Models\Company;
use App\Models\License;
use App\Models\User;
use Tests\TestCase;

class CreateLicenseTest extends TestCase
{
    public function testPermissionRequiredToViewLicense()
    {
        $license = License::factory()->create();
        $this->actingAs(User::factory()->create())
            ->get(route('licenses.create', $license))
            ->assertForbidden();
    }

    public function testPageRenders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('licenses.create'))
            ->assertOk();
    }

    public function testLicenseCreate()
    {
        $response = $this->actingAs(User::factory()->superuser()->create())
            ->from(route('licenses.create'))
            ->post(route('licenses.store'), [
                'name' => 'Test Valid License',
                'seats' => '10',
                'category_id' => Category::factory()->forLicenses()->create()->id,
                'company_id' => Company::factory()->create()->id,
                'expiration_date' => now()->addYear()->format('Y-m-d'),
                'serial' => 'LIC-PK-1001',
                'serial_number' => 'LIC-SN-1001',
                'software_version' => '2026.1',
            ]);
        $response->assertStatus(302);
        $license = License::where('name', 'Test Valid License')->sole();
        $this->assertNotNull($license);
        $this->assertSame('LIC-SN-1001', $license->serial_number);
        $this->assertSame('2026.1', $license->software_version);
        //$license->assetlog()->has_one_of_();
        $this->assertDatabaseHas('action_logs', ['action_type' => 'create', 'item_id' => $license->id, 'item_type' => License::class]);
        $this->assertDatabaseHas('action_logs', ['action_type' => 'add seats', 'item_id' => $license->id, 'item_type' => License::class]);
        $this->assertEquals($license->licenseseats()->count(), 10);
        //test log entries? Sure.

    }

    public function testPerpetualLicenseCanBeCreatedWithExpirationDate()
    {
        $expirationDate = now()->addYear()->format('Y-m-d');

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->from(route('licenses.create'))
            ->post(route('licenses.store'), [
                'name' => 'Test Perpetual License',
                'seats' => '10',
                'category_id' => Category::factory()->forLicenses()->create()->id,
                'company_id' => Company::factory()->create()->id,
                'expiration_date' => $expirationDate,
                'serial' => 'LIC-PK-PERPETUAL',
                'perpetual' => '1',
            ]);

        $response->assertStatus(302);
        $license = License::where('name', 'Test Perpetual License')->sole();
        $this->assertTrue($license->perpetual);
        $this->assertSame($expirationDate, $license->expiration_date->format('Y-m-d'));
    }

    public function testNonPerpetualLicenseWithoutExpirationDateFailsValidation()
    {
        $response = $this->actingAs(User::factory()->superuser()->create())
            ->from(route('licenses.create'))
            ->post(route('licenses.store'), [
                'name' => 'Test Missing Expiration License',
                'seats' => '10',
                'category_id' => Category::factory()->forLicenses()->create()->id,
                'company_id' => Company::factory()->create()->id,
                'serial' => 'LIC-PK-MISSING-EXPIRATION',
            ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('licenses.create'));
        $response->assertInvalid(['expiration_date']);
        $this->assertFalse(License::where('name', 'Test Missing Expiration License')->exists());
    }

    public function testTooManySeatsLicenseCreate()
    {
        $response = $this->actingAs(User::factory()->superuser()->create())
            ->from(route('licenses.create'))
            ->post(route('licenses.store'), [
                'name' => 'Test Valid License',
                'seats' => '100000',
                'category_id' => Category::factory()->forLicenses()->create()->id,
                'company_id' => Company::factory()->create()->id,
                'expiration_date' => now()->addYear()->format('Y-m-d'),
                'serial' => 'LIC-PK-TOO-MANY-SEATS',
            ]);
        $response->assertStatus(302);
        $license = License::where('name', 'Test Valid License')->first();
        $this->assertNull($license);
        //$license->assetlog()->has_one_of_();
//        $this->assertDatabaseMissing('action_logs', ['action_type' => 'create', 'item_id' => $license->id, 'item_type' => License::class]);
//        $this->assertDatabaseMissing('action_logs', ['action_type' => 'add seats', 'item_id' => $license->id, 'item_type' => License::class]);
        //test log entries? Sure.

    }

    public function testLicenseCreateRequiresProductKeyAndCompany()
    {
        $response = $this->actingAs(User::factory()->superuser()->create(['company_id' => null]))
            ->from(route('licenses.create'))
            ->post(route('licenses.store'), [
                'name' => 'Test Missing Required License Fields',
                'seats' => '10',
                'category_id' => Category::factory()->forLicenses()->create()->id,
                'expiration_date' => now()->addYear()->format('Y-m-d'),
            ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('licenses.create'));
        $response->assertInvalid([
            'serial' => 'The product key field is required.',
            'company_id' => 'The site field is required.',
        ]);
        $this->assertFalse(License::where('name', 'Test Missing Required License Fields')->exists());
    }


}
