<?php

namespace Tests\Feature\Reporting;

use App\Models\License;
use App\Models\Depreciation;
use App\Models\User;
use League\Csv\Reader;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsPermissionsRequirement;
use Tests\TestCase;

class LicenseReportTest extends TestCase implements TestsPermissionsRequirement
{
    public function testRequiresPermission()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reports/export/licenses'))
            ->assertForbidden();
    }

    #[Test]
    public function exportIncludesSeparateProductKeyAndSerialNumberColumns(): void
    {
        $license = License::factory()->create([
            'name' => 'Adobe CC',
            'serial' => 'PK-ABC-123',
            'serial_number' => 'SN-XYZ-789',
            'software_version' => '2026.4',
            'seats' => 5,
            'depreciation_id' => Depreciation::factory()->create()->id,
        ]);

        $response = $this->actingAs(User::factory()->canViewReports()->create())
            ->get(route('reports/export/licenses'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $records = array_values(iterator_to_array(Reader::createFromString($response->getContent())->getRecords()));

        $headers = array_map('trim', $records[0]);

        $this->assertContains(trans('admin/licenses/form.license_key'), $headers);
        $this->assertContains(trans('general.serial_number'), $headers);
        $this->assertContains(trans('admin/licenses/form.software_version'), $headers);

        $matchingRow = collect($records)
            ->skip(1)
            ->first(fn (array $row) => $row[0] === $license->name);

        $this->assertNotNull($matchingRow);
        $this->assertSame('2026.4', $matchingRow[1]);
        $this->assertSame('PK-ABC-123', $matchingRow[2]);
        $this->assertSame('SN-XYZ-789', $matchingRow[3]);
    }

    #[Test]
    public function primaryLicenseExportIncludesSoftwareVersion(): void
    {
        $license = License::factory()->create([
            'name' => 'MATLAB',
            'software_version' => 'R2026b',
            'serial' => 'PK-MATLAB',
            'serial_number' => 'SN-MATLAB',
        ]);

        $response = $this->actingAs(User::factory()->viewLicenses()->create())
            ->get(route('licenses.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $records = array_values(iterator_to_array(Reader::createFromString($response->streamedContent())->getRecords()));
        $headers = $records[0];
        $matchingRow = collect($records)
            ->skip(1)
            ->first(fn (array $row) => $row[0] === (string) $license->id);
        $softwareVersionIndex = array_search(trans('admin/licenses/form.software_version'), $headers);
        $productKeyIndex = array_search(trans('admin/licenses/form.license_key'), $headers);
        $serialNumberIndex = array_search(trans('general.serial_number'), $headers);

        $this->assertNotNull($matchingRow);
        $this->assertNotFalse($softwareVersionIndex);
        $this->assertNotFalse($productKeyIndex);
        $this->assertNotFalse($serialNumberIndex);
        $this->assertSame('R2026b', $matchingRow[$softwareVersionIndex]);
        $this->assertSame('PK-MATLAB', $matchingRow[$productKeyIndex]);
        $this->assertSame('SN-MATLAB', $matchingRow[$serialNumberIndex]);
    }
}
