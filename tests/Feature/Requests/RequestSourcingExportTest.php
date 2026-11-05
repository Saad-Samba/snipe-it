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
        } finally { unlink($path); }
    }

    private function seedRequest()
    {
        $this->seed(LearAssignedCandidatesQaSeeder::class);
        return CheckoutRequest::where('requestable_id', AssetModel::where('name', 'VN1630A')->firstOrFail()->id)->firstOrFail();
    }

    public function test_single_model_export_has_only_the_requested_columns_and_all_groups(): void
    {
        $request = $this->seedRequest();
        $admin = User::where('username', 'admin')->firstOrFail();
        $this->actingAs($admin)->get(route('hardware.index', ['request_id' => $request->id, 'request_bucket' => 'reusable_now']))
            ->assertOk()->assertSee('Export Sourcing Breakdown');
        $response = $this->get(route('hardware.requests.sourcing-export', [
            'checkoutRequest' => $request->id, 'search' => 'unmatched', 'request_bucket' => 'reusable_now', 'limit' => 1,
        ]));
        $response->assertDownload('request-'.$request->id.'-sourcing-breakdown.xlsx');
        $book = $this->workbook($response);
        $this->assertSame(1, $book->getSheetCount());
        $sheet = $book->getActiveSheet();
        $this->assertSame('E', $sheet->getHighestColumn());
        $rows = collect($sheet->toArray(null, true, false));
        $this->assertSame(['Sourcing Group', 'Project / RFQ', 'Total Reference Price', 'Model', 'Quantity'], $rows->shift());
        $this->assertSame(19200.0, (float) $rows->sum(2));
        foreach (['Available Now' => 1200, 'Due Back' => 2400, 'In Use' => 7200,
            'Reserved for This RFQ' => 1200, 'Reserved for Other RFQs' => 3600, 'Unavailable' => 3600] as $group => $value) {
            $this->assertSame((float) $value, (float) $rows->where(0, $group)->sum(2));
        }
        $this->assertCount(2, $rows->where(0, 'Due Back'));
        $this->assertSame(1, $rows->where(0, 'Reserved for This RFQ')->sum(4));
        $this->assertSame(3, $rows->where(0, 'Reserved for Other RFQs')->sum(4));
        $this->assertSame($request->project->name, $rows->where(0, 'Reserved for This RFQ')->first()[1]);
        $this->assertTrue($rows->pluck(1)->contains('Project Missing'));
        $this->assertSame('n', $sheet->getCell('C2')->getDataType());
        $this->assertStringContainsString('EUR', $sheet->getStyle('C2')->getNumberFormat()->getFormatCode());
        $book->disconnectWorksheets();
    }

    public function test_submission_export_deduplicates_assets_across_repeated_model_lines(): void
    {
        $request = $this->seedRequest();
        CheckoutRequest::factory()->forAssetModel()->create([
            'user_id' => $request->user_id, 'submission_batch_id' => $request->submission_batch_id,
            'requestable_id' => $request->requestable_id, 'project_id' => $request->project_id,
            'needed_by_date' => $request->needed_by_date, 'quantity' => 3,
        ]);
        CheckoutRequest::factory()->forAssetModel()->create(['submission_batch_id' => $request->submission_batch_id]);
        $admin = User::where('username', 'admin')->firstOrFail();
        $book = $this->workbook($this->actingAs($admin)->get(route('requests.submission-sourcing-export', $request)));
        $this->assertSame(1, $book->getSheetCount());
        $rows = collect(array_slice($book->getActiveSheet()->toArray(null, true, false), 1));
        $this->assertSame(20800.0, (float) $rows->sum(2));
        $this->assertSame(18, $rows->sum(4));
        $this->assertCount(2, $rows->pluck(3)->unique());
        $this->assertSame(8800.0, (float) $rows->where(0, 'In Use')->sum(2));
        $book->disconnectWorksheets();
    }

    public function test_missing_prices_are_explicit_and_zero_is_not_missing(): void
    {
        $request = $this->seedRequest();
        AssetModel::where('name', 'LA-3080')->firstOrFail()->update(['reference_price' => null]);
        AssetModel::where('name', 'VN1630A')->firstOrFail()->update(['reference_price' => 0]);
        $admin = User::where('username', 'admin')->firstOrFail();
        $book = $this->workbook($this->actingAs($admin)->get(route('requests.submission-sourcing-export', $request)));
        $rows = collect(array_slice($book->getActiveSheet()->toArray(null, true, false), 1));
        $this->assertTrue($rows->where(0, 'In Use')->pluck(2)->contains('Unpriced'));
        $this->assertSame(0.0, (float) $rows->where(0, 'Available Now')->first()[2]);
        $book->disconnectWorksheets();
    }

    public function test_export_checks_all_line_permissions_and_shared_context(): void
    {
        $request = $this->seedRequest();
        foreach (['le-qa-requester', 'le-qa-outsider'] as $name) {
            $this->flushSession();
            $this->actingAs(User::where('username', $name)->firstOrFail())
                ->get(route('hardware.requests.sourcing-export', $request))->assertForbidden();
        }
        $this->flushSession();
        $this->actingAs(User::where('username', 'le-qa-coordinator')->firstOrFail())
            ->get(route('requests.submission-sourcing-export', $request))->assertForbidden();
        $other = CheckoutRequest::where('submission_batch_id', $request->submission_batch_id)->where('id', '!=', $request->id)->firstOrFail();
        $other->update(['needed_by_date' => now()->addDays(40)]);
        $this->flushSession();
        $this->actingAs(User::where('username', 'admin')->firstOrFail())
            ->get(route('requests.submission-sourcing-export', $request))->assertStatus(422);
        $other->forceFill(['needed_by_date' => $request->needed_by_date, 'canceled_at' => now()])->save();
        $book = $this->workbook($this->get(route('requests.submission-sourcing-export', $request)));
        $this->assertSame(19200.0, (float) collect(array_slice($book->getActiveSheet()->toArray(null, true, false), 1))->sum(2));
        $book->disconnectWorksheets();
    }

    public function test_export_respects_company_visibility_and_preserves_literal_project_names(): void
    {
        $request = $this->seedRequest();
        $asset = Asset::where('asset_tag', 'LE-QA-OTHER')->firstOrFail();
        $asset->project->update(['name' => '=UNTRUSTED()']);
        $settings = Setting::getSettings();
        $settings->full_multiple_companies_support = 1;
        $settings->save();
        Setting::$_cache = $settings->fresh();
        $coordinator = User::where('username', 'le-qa-coordinator')->firstOrFail();
        $coordinator->update(['company_id' => Company::withoutGlobalScopes()->where('name', 'IEC')->firstOrFail()->id]);
        $book = $this->workbook($this->actingAs($coordinator->fresh())->get(route('hardware.requests.sourcing-export', $request)));
        $sheet = $book->getActiveSheet();
        $visibleCount = Asset::visibleTo($coordinator->fresh())->where('assets.model_id', $request->requestable_id)->count();
        $rows = collect(array_slice($sheet->toArray(null, true, false), 1));
        $this->assertSame($visibleCount * 1200.0, (float) $rows->sum(2));
        foreach ($rows->values() as $i => $row) {
            if ($row[1] === '=UNTRUSTED()') { $this->assertSame('s', $sheet->getCell('B'.($i + 2))->getDataType()); }
        }
        $this->assertTrue($rows->pluck(1)->contains('=UNTRUSTED()'));
        $book->disconnectWorksheets();
    }

    public function test_zero_inventory_export_contains_only_headers(): void
    {
        $this->seedRequest();
        $request = CheckoutRequest::where('requestable_id', AssetModel::where('name', 'SM660-AR-11')->firstOrFail()->id)->firstOrFail();
        $admin = User::where('username', 'admin')->firstOrFail();
        $book = $this->workbook($this->actingAs($admin)->get(route('hardware.requests.sourcing-export', $request)));
        $this->assertSame(1, $book->getSheetCount());
        $this->assertSame(1, $book->getActiveSheet()->getHighestRow());
        $book->disconnectWorksheets();
    }
}
