<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\Setting;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RequestSourcingExportController extends Controller
{
    public function __invoke(Request $request, CheckoutRequest $checkoutRequest)
    {
        return $this->download($request, collect([$checkoutRequest]), false);
    }

    public function submission(Request $request, CheckoutRequest $checkoutRequest)
    {
        abort_unless($checkoutRequest->canViewAssignedCandidates($request->user()), 403);
        abort_if($checkoutRequest->canceled_at, 404);
        $lines = $checkoutRequest->submission_batch_id
            ? CheckoutRequest::where('user_id', $checkoutRequest->user_id)
                ->where('submission_batch_id', $checkoutRequest->submission_batch_id)->whereNull('canceled_at')->orderBy('id')->get()
            : collect([$checkoutRequest]);
        return $this->download($request, $lines, true);
    }

    private function modelRows(Request $request, CheckoutRequest $checkoutRequest, AssetModel $model): array
    {
        $reservedStatus = Setting::rfqReservedStatusId();
        $available = $model->availableAssets()->pluck('assets.id')->flip();
        $dueBack = $checkoutRequest->needed_by_date
            ? $model->dueBackAssetsByDate($checkoutRequest->needed_by_date->toDateString())->pluck('assets.id')->flip()
            : collect();
        $inUse = $checkoutRequest->assignedCandidatesQuery($request->user())->pluck('assets.id')->flip();
        $rows = [];
        foreach (Asset::visibleTo($request->user())->where('assets.model_id', $model->id)
            ->with('project', 'company', 'assetstatus')->orderBy('assets.id')->get() as $asset) {
            if ($reservedStatus && (int) $asset->status_id === (int) $reservedStatus) {
                $group = ! $asset->project_id ? 'Reserved Without a Project / RFQ'
                    : ((int) $asset->project_id === (int) $checkoutRequest->project_id
                        ? ($checkoutRequest->project?->is_rfq ? 'Reserved for This RFQ' : 'Reserved for This Project')
                        : ($asset->project?->is_rfq ? 'Reserved for Other RFQs' : 'Reserved for Other Projects'));
            } elseif ($available->has($asset->id)) {
                $group = 'Available Now';
            } elseif ($dueBack->has($asset->id)) {
                $group = 'Due Back by Needed Date';
            } elseif ($inUse->has($asset->id)) {
                $group = ! $asset->project_id ? 'In Use Without a Project'
                    : ((int) $asset->project_id === (int) $checkoutRequest->project_id ? 'In Use by This Project' : 'In Use by Other Projects');
            } else {
                $group = 'Unavailable';
            }
            $rows[] = [$asset->asset_tag, $model->name, $group, $asset->project?->name ?? 'No Project / RFQ',
                $asset->project ? ($asset->project->is_rfq ? 'RFQ' : 'Project') : 'None',
                $asset->company?->name ?? '', $asset->expected_checkin ? Date::dateTimeToExcel(\Carbon\Carbon::parse($asset->expected_checkin)) : '',
                $asset->assetstatus?->name ?? '', 1, $asset->id];
        }
        return $rows;
    }

    private function download(Request $request, $lines, bool $submission)
    {
        abort_if($lines->isEmpty(), 404);
        foreach ($lines as $line) {
            abort_unless($line->canViewAssignedCandidates($request->user()), 403);
        }
        $lines->each(fn ($line) => $line->load('project', 'company', 'requestedDiscipline'));
        // A submission is one project/RFQ and deadline. Never silently classify legacy mixed-context lines against the first line.
        abort_if($lines->pluck('project_id')->unique()->count() > 1
            || $lines->map(fn ($line) => $line->needed_by_date?->toDateString())->unique()->count() > 1,
            422, 'This submission has different projects or needed dates. Align its submission context before exporting.');
        $checkoutRequest = $lines->first();
        $model = AssetModel::findOrFail($checkoutRequest->requestable_id);
        $headers = ['Asset Tag', 'Model', 'Sourcing Group', 'Project / RFQ', 'Project Type', 'Site', 'Expected Return', 'Status', 'Asset Count'];
        $uniqueAssets = [];
        $modelRows = [];
        $lineRows = [['Request Line ID', 'Model', 'Requested Qty', 'Destination Site', 'Discipline', 'Project / RFQ', 'Needed By']];
        $candidateRows = [['Request Line ID', 'Asset ID', 'Asset Tag', 'Model', 'Sourcing Group', 'Project / RFQ', 'Site']];
        foreach ($lines as $line) {
            $lineModel = AssetModel::findOrFail($line->requestable_id);
            $modelRows[$lineModel->id] ??= $this->modelRows($request, $line, $lineModel);
            $lineRows[] = [$line->id, $lineModel->name, (int) $line->quantity, $line->company?->name ?? '',
                $line->requestedDiscipline?->name ?? '', $line->project?->name ?? 'No Project / RFQ',
                $line->needed_by_date ? Date::dateTimeToExcel($line->needed_by_date) : 'Not specified'];
            foreach ($modelRows[$lineModel->id] as $row) {
                $uniqueAssets[$row[9]] = $row;
                if (in_array($row[2], ['Available Now', 'Due Back by Needed Date', 'In Use by This Project',
                    'In Use by Other Projects', 'In Use Without a Project', 'Reserved for This RFQ', 'Reserved for This Project'], true)) {
                    $candidateRows[] = [$line->id, $row[9], $row[0], $row[1], $row[2], $row[3], $row[5]];
                }
            }
        }
        $rows = [$submission ? array_merge($headers, ['Asset ID']) : $headers];
        foreach ($uniqueAssets as $row) { $rows[] = $submission ? $row : array_slice($row, 0, 9); }
        $metrics = $checkoutRequest->liveRequestMetrics();
        $summary = [['Request Summary', 'Value'], ['Request ID', $checkoutRequest->id], ['Model', $model->name],
            ['Requested Qty', (int) $checkoutRequest->quantity], ['Needed By', $checkoutRequest->needed_by_date ? Date::dateTimeToExcel($checkoutRequest->needed_by_date) : 'Not specified'],
            ['Project / RFQ', $checkoutRequest->project?->name ?? 'None'], ['Exported At (UTC)', Date::dateTimeToExcel(now('UTC'))],
            ['Exported Asset Count', count($rows) - 1], ['Asset Scope', 'All non-deleted assets for this model visible to the exporting user; independent of active tab, search and pagination.'],
            ['Available Now (all sites)', (int) ($metrics['reusable_quantity'] ?? 0)],
            ['Due Back by Needed Date (all sites)', (int) ($metrics['due_back_before_needed_by_quantity'] ?? 0)],
            ['Estimated Qty to Procure (all sites)', (int) ($metrics['procurement_shortfall'] ?? 0)],
            ['Estimate Scope', 'Request sourcing estimate across all sites; may differ from the permission-scoped asset breakdown. In-use assets do not reduce it until released.'],
            ['Classification', 'One row per asset. Priority: reservation, available now, due back by needed date, eligible in-use asset, unavailable. No needed date means no due-back deadline.'],
            ['Excel Chart', 'Open Sunburst Data, select columns A:C including the headers and populated rows, then Insert > Hierarchy Chart > Sunburst. The table already groups assets by sourcing group and project/RFQ. No PivotTable or copying is needed.']];
        if ($submission) {
            $visibleShortfall = 0;
            foreach ($lines->groupBy('requestable_id') as $modelId => $modelLines) {
                $coverage = collect($modelRows[$modelId])->filter(fn ($row) => in_array($row[2], ['Available Now', 'Due Back by Needed Date'], true))->count();
                $visibleShortfall += max((int) $modelLines->sum('quantity') - $coverage, 0);
            }
            $summary = [['Submission Summary', 'Value'], ['Submission Reference', $checkoutRequest->submission_batch_id ?: '#'.$checkoutRequest->id],
                ['Request Lines', $lines->count()], ['Requested Qty', (int) $lines->sum('quantity')],
                ['Needed By', $checkoutRequest->needed_by_date ? Date::dateTimeToExcel($checkoutRequest->needed_by_date) : 'Not specified'],
                ['Project / RFQ', $checkoutRequest->project?->name ?? 'No Project / RFQ'], ['Exported At (UTC)', Date::dateTimeToExcel(now('UTC'))],
                ['Unique Asset Count', count($uniqueAssets)], ['Inventory Scope', 'Non-deleted assets visible to the exporting user. Each Asset ID counted once across all lines.'],
                ['Available Now', collect($uniqueAssets)->where(2, 'Available Now')->count()],
                ['Due Back by Needed Date', collect($uniqueAssets)->where(2, 'Due Back by Needed Date')->count()],
                ['Estimated Qty to Procure (visible inventory)', $visibleShortfall],
                ['Estimate Scope', 'Demand is summed by model. Visible Available Now plus Due Back inventory is deducted once per model. Reservations and assets in use require confirmation and are excluded from this estimate. This is not a confirmed allocation or the all-sites estimate from individual lines.'],
                ['Candidate Scope', 'Line Candidates lists potential sourcing links, not allocations. An asset may match multiple lines but appears only once in Unique Assets and Sunburst Data. Unavailable assets and other-project/RFQ reservations remain in inventory but are not candidates.'],
                ['Excel Chart', 'Select populated A:C in Sunburst Data, then Insert > Hierarchy Chart > Sunburst.']];
        }
        $book = new Spreadsheet();
        $book->getActiveSheet()->setTitle($submission ? 'Submission Summary' : 'Request Summary');
        $this->writeSheet($book->getActiveSheet(), $summary);
        $book->getActiveSheet()->getStyle('B5')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $book->getActiveSheet()->getStyle('B7')->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm:ss');
        $sheet = $book->createSheet()->setTitle($submission ? 'Unique Assets' : 'Asset Breakdown');
        $this->writeSheet($sheet, $rows);
        if (count($rows) > 1) {
            $sheet->getStyle('G2:G'.count($rows))->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        }
        $sheet->addTable(new \PhpOffice\PhpSpreadsheet\Worksheet\Table('A1:'.($submission ? 'J' : 'I').count($rows), 'SourcingAssets'));
        $counts = [];
        foreach (array_slice($rows, 1) as $row) {
            $key = json_encode([$row[2], $row[3]], JSON_THROW_ON_ERROR);
            if (! isset($counts[$key])) { $counts[$key] = [$row[2], $row[3], 0]; }
            $counts[$key][2] += $row[8];
        }
        $groups = array_values($counts);
        usort($groups, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        $chartRows = array_merge([['Sourcing Group', 'Project / RFQ', 'Asset Count']], $groups);
        $chartSheet = $book->createSheet()->setTitle('Sunburst Data');
        $this->writeSheet($chartSheet, $chartRows);
        $chartSheet->getColumnDimension('A')->setWidth(38);
        $chartSheet->getColumnDimension('B')->setWidth(45);
        $chartSheet->getColumnDimension('C')->setWidth(16);
        $chartSheet->addTable(new \PhpOffice\PhpSpreadsheet\Worksheet\Table('A1:C'.count($chartRows), 'SunburstData'));
        if ($submission) {
            $lineSheet = $book->createSheet()->setTitle('Request Lines');
            $this->writeSheet($lineSheet, $lineRows);
            if (count($lineRows) > 1) { $lineSheet->getStyle('G2:G'.count($lineRows))->getNumberFormat()->setFormatCode('yyyy-mm-dd'); }
            $lineSheet->addTable(new \PhpOffice\PhpSpreadsheet\Worksheet\Table('A1:G'.count($lineRows), 'RequestLines'));
            $candidateSheet = $book->createSheet()->setTitle('Line Candidates');
            $this->writeSheet($candidateSheet, $candidateRows);
            $candidateSheet->addTable(new \PhpOffice\PhpSpreadsheet\Worksheet\Table('A1:G'.count($candidateRows), 'LineCandidates'));
        }
        $book->setActiveSheetIndex(2);

        return response()->streamDownload(function () use ($book) {
            try { (new Xlsx($book))->save('php://output'); }
            finally { $book->disconnectWorksheets(); }
        }, ($submission ? 'submission-' : 'request-').$checkoutRequest->id.'-sourcing-breakdown.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function writeSheet($sheet, array $rows): void
    {
        foreach ($rows as $r => $row) {
            foreach ($row as $c => $value) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($c + 1).($r + 1), $value,
                    is_int($value) || is_float($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            }
        }
        $lastColumn = Coordinate::stringFromColumnIndex(count($rows[0]));
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF315F7D');
        $sheet->freezePane('A2');
        foreach (range(1, count($rows[0])) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth($column === 2 && count($rows[0]) === 2 ? 95 : 28);
        }
        $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setWrapText(true)->setVertical('top');
    }
}
