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
        $this->assertCount(16, $rows);
        $this->assertCount(16, $rows->pluck(0)->unique());
        $this->assertSame(16, $rows->sum(8));
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
        $chart = $book->getSheetByName('Sunburst Data');
        $chartRows = collect(array_slice($chart->toArray(), 1));
        $this->assertSame(16, $chartRows->sum(2));
        $this->assertSame($rows->groupBy(fn ($row) => json_encode([$row[2], $row[3]]))->count(), $chartRows->count());
        $this->assertSame(1, $chartRows->where(0, 'Reserved for This RFQ')->sum(2));
        $this->assertSame(3, $chartRows->where(0, 'Reserved for Other RFQs')->sum(2));
        $this->assertCount(2, $chartRows->where(0, 'Due Back by Needed Date'));
        $this->assertSame(2, $chartRows->where(0, 'Due Back by Needed Date')->sum(2));
        $this->assertSame(1, $chartRows->where(0, 'Available Now')->sum(2));
        $this->assertSame('Value Sunburst', $book->getActiveSheet()->getTitle());
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
    public function test_submission_export_sums_demand_but_counts_overlapping_assets_once(): void
    {
        $this->seed(LearAssignedCandidatesQaSeeder::class);
        $admin = User::where('username', 'le-qa-admin')->firstOrFail();
        $main = CheckoutRequest::where('requestable_id', AssetModel::where('name', 'VN1630A')->firstOrFail()->id)->firstOrFail();
        $duplicate = CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $main->user_id, 'submission_batch_id' => $main->submission_batch_id,
            'requestable_id' => $main->requestable_id, 'project_id' => $main->project_id,
            'needed_by_date' => $main->needed_by_date, 'quantity' => 3,
        ]);
        // A coincidentally identical batch ID belonging to another requester is not this submission.
        CheckoutRequest::factory()->forAssetModel()->create([
            'submission_batch_id' => $main->submission_batch_id, 'quantity' => 99,
        ]);
        $response = $this->actingAs($admin)->get(route('requests.submission-sourcing-export', $main));
        $response->assertDownload('submission-'.$main->id.'-sourcing-breakdown.xlsx');
        $book = $this->workbook($response);
        $lines = collect(array_slice($book->getSheetByName('Request Lines')->toArray(), 1));
        $assets = collect(array_slice($book->getSheetByName('Unique Assets')->toArray(), 1));
        $links = collect(array_slice($book->getSheetByName('Line Candidates')->toArray(), 1));
        $chart = collect(array_slice($book->getSheetByName('Sunburst Data')->toArray(), 1));
        $summary = collect($book->getSheetByName('Submission Summary')->toArray())->keyBy(0);
        $this->assertCount(4, $lines);
        $this->assertSame(15, $lines->sum(2));
        $this->assertCount(18, $assets);
        $this->assertCount(18, $assets->pluck(9)->unique());
        $this->assertSame(18, $chart->sum(2));
        $this->assertSame(12, (int) $summary['Estimated Qty to Procure (visible inventory)'][1]);
        $this->assertSame('n', $book->getSheetByName('Submission Summary')->getCell('B12')->getDataType());
        $availableId = Asset::where('asset_tag', 'LE-QA-AVAILABLE')->firstOrFail()->id;
        $this->assertCount(2, $links->where(1, $availableId));
        $this->assertEqualsCanonicalizing([$main->id, $duplicate->id], $links->where(1, $availableId)->pluck(0)->all());
        $this->assertFalse($links->pluck(4)->contains('Unavailable'));
        $this->assertFalse($links->pluck(4)->contains('Reserved for Other RFQs'));
        $this->assertSame('Value Sunburst', $book->getActiveSheet()->getTitle());
        $values = collect(array_slice($book->getSheetByName('Value Sunburst')->rangeToArray('A1:C'.$book->getSheetByName('Value Sunburst')->getHighestRow(), null, true, false), 1));
        $this->assertSame(20800.0, (float) $values->sum(2));
        $this->assertStringContainsString('assets · EUR ', $values->first()[0]);
        $costs = collect(array_slice($book->getSheetByName('Cost Summary')->toArray(null, true, false), 1));
        $this->assertCount(3, $costs);
        $this->assertSame(19200.0, (float) $costs->sum(7));
        $this->assertSame(15600.0, (float) $costs->sum(8));
        $this->assertSame(3600.0, (float) $costs->sum(9));
        $this->assertSame('EUR', $book->getSheetByName('Unique Assets')->getCell('L2')->getValue());
        $this->assertSame('n', $book->getSheetByName('Unique Assets')->getCell('K2')->getDataType());
        $book->disconnectWorksheets();
    }

    public function test_submission_export_requires_access_to_all_lines_and_rejects_mixed_context(): void
    {
        $this->seed(LearAssignedCandidatesQaSeeder::class);
        $main = CheckoutRequest::where('requestable_id', AssetModel::where('name', 'VN1630A')->firstOrFail()->id)->firstOrFail();
        $coordinator = User::where('username', 'le-qa-coordinator')->firstOrFail();
        $this->actingAs($coordinator)->get(route('requests.submission-sourcing-export', $main))->assertForbidden();
        $this->flushSession();
        $admin = User::where('username', 'le-qa-admin')->firstOrFail();
        $other = CheckoutRequest::where('submission_batch_id', $main->submission_batch_id)->where('id', '!=', $main->id)->firstOrFail();
        $other->update(['needed_by_date' => now()->addDays(40)]);
        $this->actingAs($admin)->get(route('requests.submission-sourcing-export', $main))->assertStatus(422);
        $other->forceFill(['needed_by_date' => $main->needed_by_date, 'canceled_at' => now()])->save();
        $book = $this->workbook($this->get(route('requests.submission-sourcing-export', $main)));
        $this->assertSame(3, $book->getSheetByName('Request Lines')->getHighestRow());
        $book->disconnectWorksheets();
    }

    public function test_value_chart_exposes_unpriced_and_zero_value_assets_without_inventing_values(): void
    {
        $this->seed(LearAssignedCandidatesQaSeeder::class);
        $admin = User::where('username', 'le-qa-admin')->firstOrFail();
        $main = CheckoutRequest::where('requestable_id', AssetModel::where('name', 'VN1630A')->firstOrFail()->id)->firstOrFail();
        AssetModel::where('name', 'LA-3080')->firstOrFail()->update(['reference_price' => null]);
        $book = $this->workbook($this->actingAs($admin)->get(route('requests.submission-sourcing-export', $main)));
        $sheet = $book->getSheetByName('Value Sunburst');
        $coverage = collect($sheet->rangeToArray('E1:F14', null, true, false))->keyBy(0);
        $this->assertSame(2, $coverage['Unpriced Assets'][1]);
        $this->assertSame(4, $coverage['Unpriced Requested Qty'][1]);
        $this->assertStringContainsString('Partial', $coverage['Price Coverage'][1]);
        $this->assertSame(19200.0, (float) $coverage['Known Inventory Reference Value'][1]);
        $costs = collect(array_slice($book->getSheetByName('Cost Summary')->toArray(null, true, false), 1));
        $this->assertNull($costs->firstWhere(0, 'LA-3080')[8]);
        $this->assertSame(8800.0, (float) $costs->sum(8));
        $detail = collect(array_slice($book->getSheetByName('Value Coverage')->toArray(null, true, false), 1));
        $this->assertSame(18, $detail->sum(2));
        $this->assertSame(2, $detail->sum(4));
        $book->disconnectWorksheets();

        AssetModel::where('name', 'VN1630A')->firstOrFail()->update(['reference_price' => 0]);
        $book = $this->workbook($this->get(route('requests.submission-sourcing-export', $main)));
        $sheet = $book->getSheetByName('Value Sunburst');
        $this->assertSame('Reference Value', $sheet->getCell('C1')->getValue());
        $this->assertNull($sheet->getCell('C2')->getValue());
        $coverage = collect($sheet->rangeToArray('E1:F14', null, true, false))->keyBy(0);
        $this->assertSame(16, $coverage['Zero-value Assets'][1]);
        $this->assertSame(2, $coverage['Unpriced Assets'][1]);
        $this->assertStringContainsString('No positive reference values', $coverage['Chart Instructions'][1]);
        $book->disconnectWorksheets();
    }

}
