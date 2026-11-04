<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\Setting;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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
            ->with('project')->orderBy('assets.id')->get() as $asset) {
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
            $rows[$asset->id] = [$group, $asset->project?->name ?? 'No Project / RFQ',
                $model->reference_price === null ? null : (int) round((float) $model->reference_price * 100)];
        }
        return $rows;
    }

    private function download(Request $request, $lines, bool $submission)
    {
        abort_if($lines->isEmpty(), 404);
        foreach ($lines as $line) {
            abort_unless($line->canViewAssignedCandidates($request->user()), 403);
        }
        $lines->each(fn ($line) => $line->load('project'));
        // A submission is one project/RFQ and deadline. Never silently classify legacy mixed-context lines against the first line.
        abort_if($lines->pluck('project_id')->unique()->count() > 1
            || $lines->map(fn ($line) => $line->needed_by_date?->toDateString())->unique()->count() > 1,
            422, 'This submission has different projects or needed dates. Align its submission context before exporting.');
        $assets = [];
        foreach ($lines->groupBy('requestable_id') as $modelId => $modelLines) {
            $model = AssetModel::findOrFail($modelId);
            foreach ($this->modelRows($request, $modelLines->first(), $model) as $assetId => $row) {
                $assets[$assetId] = $row;
            }
        }
        $groups = [];
        foreach ($assets as [$group, $project, $cents]) {
            $key = json_encode([$group, $project], JSON_THROW_ON_ERROR);
            $groups[$key] ??= ['group' => $group, 'project' => $project, 'cents' => 0, 'unpriced' => false];
            if ($cents === null) { $groups[$key]['unpriced'] = true; }
            else { $groups[$key]['cents'] += $cents; }
        }
        uasort($groups, fn ($a, $b) => [$a['group'], $a['project']] <=> [$b['group'], $b['project']]);
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('Sourcing Breakdown');
        $sheet->fromArray([['Sourcing Group', 'Project / RFQ', 'Total Reference Price']]);
        $rowNumber = 2;
        foreach ($groups as $group) {
            $sheet->setCellValueExplicit('A'.$rowNumber, $group['group'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B'.$rowNumber, $group['project'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C'.$rowNumber, $group['unpriced'] ? 'Unpriced' : $group['cents'] / 100,
                $group['unpriced'] ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC);
            $rowNumber++;
        }
        $currency = Setting::getSettings()->default_currency;
        $book->getProperties()->setTitle('Sourcing Breakdown')->setDescription(
            'Current model reference prices in '.($currency ?: 'the configured currency').'. Each visible asset is counted once. Unpriced means at least one asset in that group has no reference price.'
        );
        if ($rowNumber > 2) {
            $sheet->getStyle('C2:C'.($rowNumber - 1))->getNumberFormat()
                ->setFormatCode($currency ? '"'.str_replace('"', '""', $currency).'" #,##0.00' : '#,##0.00');
        }
        $sheet->addTable(new \PhpOffice\PhpSpreadsheet\Worksheet\Table('A1:C'.($rowNumber - 1), 'SourcingBreakdown'));
        $sheet->freezePane('A2');
        $sheet->getColumnDimension('A')->setWidth(38);
        $sheet->getColumnDimension('B')->setWidth(48);
        $sheet->getColumnDimension('C')->setWidth(25);
        $sheet->getStyle('A1:C1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:C1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF315F7D');
        $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setWrapText(true)->setVertical('top');
        $checkoutRequest = $lines->first();
        return response()->streamDownload(function () use ($book) {
            try { (new Xlsx($book))->save('php://output'); }
            finally { $book->disconnectWorksheets(); }
        }, ($submission ? 'submission-' : 'request-').$checkoutRequest->id.'-sourcing-breakdown.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
