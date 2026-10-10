<?php

namespace Tests\Feature\Licenses\Ui;

use App\Models\Category;
use App\Models\Company;
use App\Models\License;
use App\Models\SoftwareModel;
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

    public function testCanUpdateLicenseSeats()
    {
        $admin = User::factory()->superuser()->create();
        $license_category = Category::factory()->forLicenses()->create()->id;
        $company = Company::factory()->create();
        $softwareModel = SoftwareModel::factory()->create(['name' => 'Test Update License', 'category_id' => $license_category]);
        $response = $this->actingAs($admin)
            ->from(route('licenses.create'))
            ->post(route('licenses.store'), [
                'software_model_id' => $softwareModel->id,
                'seats' => '9999',
                'category_id' => $license_category,
                'company_id' => $company->id,
                'expiration_date' => now()->addYear()->format('Y-m-d'),
                'last_physical_verification_date' => now()->format('Y-m-d'),
                'serial' => 'LIC-PK-UPDATE-SEATS',
            ]);
        $response->assertStatus(302);
        $license = License::where('name', 'Test Update License')->sole();
        $this->assertNotNull($license);

        $this->actingAs($admin)
            ->put(route('licenses.update', $license->id), [
                'name' => 'Test Update License',
                'seats' => '19999',
                'category_id' => $license_category,
                'company_id' => $company->id,
                'expiration_date' => now()->addYear()->format('Y-m-d'),
                'last_physical_verification_date' => now()->format('Y-m-d'),
                'serial' => 'LIC-PK-UPDATE-SEATS',
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
        $company = Company::factory()->create();
        $softwareModel = SoftwareModel::factory()->create(['name' => 'Test Update License', 'category_id' => $license_category]);
        $response = $this->actingAs($admin)
            ->from(route('licenses.create'))
            ->post(route('licenses.store'), [
                'software_model_id' => $softwareModel->id,
                'seats' => '9999',
                'category_id' => $license_category,
                'company_id' => $company->id,
                'expiration_date' => now()->addYear()->format('Y-m-d'),
                'last_physical_verification_date' => now()->format('Y-m-d'),
                'serial' => 'LIC-PK-SEATS-TOO-MUCH',
            ]);
        $response->assertStatus(302);
        $license = License::where('name', 'Test Update License')->sole();
        $this->assertNotNull($license);

        $this->actingAs($admin)
            ->put(route('licenses.update', $license->id), [
                'name' => 'Test Update License',
                'seats' => '29999',
                'category_id' => $license_category,
                'company_id' => $company->id,
                'expiration_date' => now()->addYear()->format('Y-m-d'),
                'last_physical_verification_date' => now()->format('Y-m-d'),
                'serial' => 'LIC-PK-SEATS-TOO-MUCH',
            ])
            ->assertStatus(302);

        $license->refresh();
        $this->assertEquals($license->licenseseats()->count(), $license->seats);
        $this->assertEquals($license->licenseseats()->count(), 9999);
    }

    public function testCanMarkLicensePerpetualAndKeepExpirationDate()
    {
        $admin = User::factory()->superuser()->create();
        $expirationDate = now()->addYear()->format('Y-m-d');
        $license = License::factory()->create([
            'company_id' => Company::factory()->create()->id,
            'expiration_date' => now()->addMonth()->format('Y-m-d'),
            'perpetual' => false,
        ]);

        $this->actingAs($admin)
            ->put(route('licenses.update', $license->id), [
                'name' => $license->name,
                'seats' => $license->seats,
                'category_id' => $license->category_id,
                'company_id' => $license->company_id,
                'expiration_date' => $expirationDate,
                'last_physical_verification_date' => now()->format('Y-m-d'),
                'serial' => $license->serial,
                'perpetual' => '1',
            ])
            ->assertStatus(302);

        $license->refresh();
        $this->assertTrue($license->perpetual);
        $this->assertSame($expirationDate, $license->expiration_date->format('Y-m-d'));
    }

    public function testCanRemoveLicenseSeats()
    {
        $admin = User::factory()->superuser()->create();
        $license_category = Category::factory()->forLicenses()->create()->id;
        $company = Company::factory()->create();
        $softwareModel = SoftwareModel::factory()->create(['name' => 'Test Remove License Seats', 'category_id' => $license_category]);
        $response = $this->actingAs($admin)
            ->from(route('licenses.create'))
            ->post(route('licenses.store'), [
                'software_model_id' => $softwareModel->id,
                'seats' => '9999',
                'category_id' => $license_category,
                'company_id' => $company->id,
                'expiration_date' => now()->addYear()->format('Y-m-d'),
                'last_physical_verification_date' => now()->format('Y-m-d'),
                'serial' => 'LIC-PK-REMOVE-SEATS',
            ]);
        $response->assertStatus(302);
        $license = License::where('name', 'Test Remove License Seats')->sole();
        $this->assertNotNull($license);

        $this->actingAs($admin)
            ->put(route('licenses.update', $license->id), [
                'name' => 'Test Remove License Seats',
                'seats' => '5000',
                'category_id' => $license_category,
                'company_id' => $company->id,
                'expiration_date' => now()->addYear()->format('Y-m-d'),
                'last_physical_verification_date' => now()->format('Y-m-d'),
                'serial' => 'LIC-PK-REMOVE-SEATS',
            ])
            ->assertStatus(302);

        $license->refresh();
        $this->assertEquals($license->licenseseats()->count(), $license->seats);
        $this->assertEquals($license->licenseseats()->count(), 5000);
    }


}
