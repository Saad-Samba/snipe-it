<?php

namespace Tests\Feature\Requests;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\LearAssignedCandidatesQaSeeder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class RequestSourcingExportTest extends TestCase
{
    private function workbook($response)
    {
        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'sourcing-test-');
        try {
            file_put_contents($path, $response->streamedContent());
            return IOFactory::load($path);
        } finally {
            unlink($path);
        }
    }

    public function test_export_is_a_complete_disjoint_asset_breakdown_independent_of_tab_filters(): void
    {
        $this->seed(LearAssignedCandidatesQaSeeder::class);
        $admin = User::where('username', 'le-qa-admin')->firstOrFail();
        $model = AssetModel::where('name', 'VN1630A')->firstOrFail();
        $request = CheckoutRequest::where('requestable_id', $model->id)->firstOrFail();
        $asset = Asset::where('asset_tag', 'LE-QA-OTHER')->firstOrFail();
        $asset->project->update(['name' => '=UNTRUSTED()', 'is_rfq' => true, 'rfq_needed_by_date' => '2026-11-20']);
        $this->actingAs($admin)->get(route('hardware.index', ['request_id' => $request->id, 'request_bucket' => 'reusable_now']))
            ->assertOk()->assertSee('Export Sourcing Breakdown')
            ->assertSee(route('hardware.requests.sourcing-export', $request), false);
        $response = $this->actingAs($admin)->get(route('hardware.requests.sourcing-export', [
            'checkoutRequest' => $request->id, 'request_bucket' => 'reusable_now', 'search' => 'unmatched', 'limit' => 1,
        ]));
        $response->assertDownload('request-'.$request->id.'-sourcing-breakdown.xlsx');
        $book = $this->workbook($response);
        $rows = collect(array_slice($book->getSheetByName('Asset Breakdown')->toArray(), 1));
        $this->assertCount(12, $rows);
        $this->assertCount(12, $rows->pluck(0)->unique());
        $this->assertSame(12, $rows->sum(8));
        $byTag = $rows->keyBy(0);
        foreach (['AVAILABLE' => 'Available Now', 'SAME' => 'In Use by This Project', 'OTHER' => 'In Use by Other Projects',
            'NO-PROJECT' => 'In Use Without a Project', 'DUE' => 'Due Back by Needed Date',
            'RESERVED' => 'Reserved for Other RFQs', 'REPAIR' => 'Unavailable', 'PENDING' => 'Unavailable', 'ARCHIVED' => 'Unavailable'] as $tag => $group) {
            $this->assertSame($group, $byTag['LE-QA-'.$tag][2]);
        }
        $sheet = $book->getSheetByName('Asset Breakdown');
        foreach ($rows->values() as $i => $row) {
            if ($row[3] === '=UNTRUSTED()') {
                $this->assertSame('s', $sheet->getCell('D'.($i + 2))->getDataType());
            }
        }
        $this->assertSame('Request Summary', $book->getSheet(0)->getTitle());
        $book->disconnectWorksheets();
    }

    public function test_export_enforces_request_access_and_company_visibility(): void
    {
        $this->seed(LearAssignedCandidatesQaSeeder::class);
        $model = AssetModel::where('name', 'VN1630A')->firstOrFail();
        $request = CheckoutRequest::where('requestable_id', $model->id)->firstOrFail();
        foreach (['le-qa-requester', 'le-qa-outsider'] as $name) {
            $this->flushSession();
            $this->actingAs(User::where('username', $name)->firstOrFail())
                ->get(route('hardware.requests.sourcing-export', $request))->assertForbidden();
        }
        $settings = Setting::getSettings();
        $settings->full_multiple_companies_support = 1;
        $settings->save();
        Setting::$_cache = $settings->fresh();
        $coordinator = User::where('username', 'le-qa-coordinator')->firstOrFail();
        $coordinator->update(['company_id' => Company::withoutGlobalScopes()->where('name', 'IEC')->firstOrFail()->id]);
        $this->flushSession();
        $book = $this->workbook($this->actingAs($coordinator->fresh())->get(route('hardware.requests.sourcing-export', $request)));
        $rows = array_slice($book->getSheetByName('Asset Breakdown')->toArray(), 1);
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) { $this->assertSame('IEC', $row[5]); }
        $book->disconnectWorksheets();
    }

    public function test_zero_inventory_export_preserves_headers_and_request_summary(): void
    {
        $this->seed(LearAssignedCandidatesQaSeeder::class);
        $admin = User::where('username', 'le-qa-admin')->firstOrFail();
        $request = CheckoutRequest::factory()->forAssetModel()->create([
            'requestable_id' => AssetModel::where('name', 'SM660-AR-11')->firstOrFail()->id,
        ]);
        $book = $this->workbook($this->actingAs($admin)->get(route('hardware.requests.sourcing-export', $request)));
        $this->assertSame(1, $book->getSheetByName('Asset Breakdown')->getHighestRow());
        $this->assertSame('Asset Tag', $book->getSheetByName('Asset Breakdown')->getCell('A1')->getValue());
        $book->disconnectWorksheets();
    }
}
