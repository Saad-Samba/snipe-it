<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\Project;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class ProjectRequestsReuseAnalysisExport
{
    private const DATA_START_ROW = 9;
    private const TEMPLATE_DATA_ROW = 9;
    private const TEMPLATE_TOTAL_ROW = 46;
    private const LAST_TEMPLATE_COLUMN = 'AG';

    /**
     * @param  Collection<int, CheckoutRequest>  $requests
     */
    public function create(Project $project, Collection $requests): string
    {
        $templatePath = resource_path('templates/reuse-analysis-template.xlsx');
        if (! is_file($templatePath)) {
            throw new RuntimeException('Reuse analysis export template not found.');
        }

        $spreadsheet = IOFactory::load($templatePath);
        $worksheet = $spreadsheet->getSheetByName('Reuse Analysis');

        if (! $worksheet instanceof Worksheet) {
            throw new RuntimeException('Reuse analysis worksheet not found in template.');
        }

        $requestCount = $requests->count();
        $dataRowCount = max($requestCount, 1);

        if ($dataRowCount > 1) {
            $worksheet->insertNewRowBefore(self::TEMPLATE_TOTAL_ROW, $dataRowCount - 1);

            for ($row = self::TEMPLATE_DATA_ROW + 1; $row <= self::DATA_START_ROW + $dataRowCount - 1; $row++) {
                $this->copyRowStyle($worksheet, self::TEMPLATE_DATA_ROW, $row);
            }
        }

        $lastDataRow = self::DATA_START_ROW + $dataRowCount - 1;
        $totalRowIndex = self::TEMPLATE_TOTAL_ROW + ($dataRowCount - 1);

        if ($lastDataRow + 1 <= $totalRowIndex - 1) {
            $worksheet->removeRow($lastDataRow + 1, $totalRowIndex - $lastDataRow - 1);
            $totalRowIndex = $lastDataRow + 1;
        }

        $totals = [
            'total_need_cost' => 0.0,
            'reuse_value' => 0.0,
            'net_saving' => 0.0,
            'amount_to_buy' => 0.0,
        ];

        foreach (range(self::DATA_START_ROW, $lastDataRow) as $rowNumber) {
            $this->clearWritableColumns($worksheet, $rowNumber);
        }

        foreach ($requests->values() as $index => $checkoutRequest) {
            $rowNumber = self::DATA_START_ROW + $index;
            $rowData = $this->buildRowData($checkoutRequest);

            $totals['total_need_cost'] += (float) ($rowData['H'] ?? 0);
            $totals['reuse_value'] += (float) ($rowData['Z'] ?? 0);
            $totals['net_saving'] += (float) ($rowData['AB'] ?? 0);
            $totals['amount_to_buy'] += (float) ($rowData['AE'] ?? 0);

            foreach ($rowData as $column => $value) {
                $worksheet->setCellValue($column.$rowNumber, $value);
            }
        }

        $this->clearWritableColumns($worksheet, $totalRowIndex);
        $worksheet->setCellValue('D'.$totalRowIndex, 'Total (USD)');
        $worksheet->setCellValue('H'.$totalRowIndex, round($totals['total_need_cost'], 2));
        $worksheet->setCellValue('Z'.$totalRowIndex, round($totals['reuse_value'], 2));
        $worksheet->setCellValue('AB'.$totalRowIndex, round($totals['net_saving'], 2));
        $worksheet->setCellValue('AE'.$totalRowIndex, round($totals['amount_to_buy'], 2));

        $outputPath = tempnam(sys_get_temp_dir(), 'reuse-analysis-');
        if ($outputPath === false) {
            throw new RuntimeException('Unable to create a temporary export file.');
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($outputPath);
        $spreadsheet->disconnectWorksheets();

        return $outputPath;
    }

    private function buildRowData(CheckoutRequest $checkoutRequest): array
    {
        $liveMetrics = $checkoutRequest->liveRequestMetrics();
        $unitPrice = $checkoutRequest->reference_price_snapshot !== null ? (float) $checkoutRequest->reference_price_snapshot : null;
        $requiredQuantity = (int) $checkoutRequest->quantity;
        $reuseQuantity = (int) ($liveMetrics['reusable_quantity'] ?? 0);
        $reuseValue = round((float) ($liveMetrics['estimated_savings'] ?? 0), 2);
        $procurementShortfall = (int) ($liveMetrics['procurement_shortfall'] ?? 0);
        $amountToBuy = round((float) ($liveMetrics['amount_to_buy'] ?? 0), 2);

        return [
            'B' => optional($checkoutRequest->requestedDiscipline)->name,
            'C' => $this->assetFamilyName($checkoutRequest),
            'D' => $checkoutRequest->name(),
            'E' => $unitPrice,
            'G' => $requiredQuantity,
            'H' => $unitPrice !== null ? round($unitPrice * $requiredQuantity, 2) : null,
            'Y' => $reuseQuantity,
            'Z' => $reuseValue,
            'AB' => $reuseValue,
            'AD' => $procurementShortfall,
            'AE' => $amountToBuy,
        ];
    }

    private function assetFamilyName(CheckoutRequest $checkoutRequest): ?string
    {
        if ($checkoutRequest->requestable_type === AssetModel::class) {
            $assetModel = AssetModel::query()
                ->with('category')
                ->find($checkoutRequest->requestable_id);

            return optional(optional($assetModel)->category)->name;
        }

        if ($checkoutRequest->requestable_type === Asset::class) {
            $asset = Asset::query()
                ->with('model.category')
                ->find($checkoutRequest->requestable_id);

            return optional(optional(optional($asset)->model)->category)->name;
        }

        return null;
    }

    private function copyRowStyle(Worksheet $worksheet, int $sourceRow, int $targetRow): void
    {
        $worksheet->duplicateStyle(
            $worksheet->getStyle('B'.$sourceRow.':'.self::LAST_TEMPLATE_COLUMN.$sourceRow),
            'B'.$targetRow.':'.self::LAST_TEMPLATE_COLUMN.$targetRow
        );

        $worksheet->getRowDimension($targetRow)->setRowHeight(
            $worksheet->getRowDimension($sourceRow)->getRowHeight()
        );
    }

    private function clearWritableColumns(Worksheet $worksheet, int $rowNumber): void
    {
        $columnsToClear = [
            'B', 'C', 'D', 'E', 'G', 'H',
            'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U',
            'W', 'Y', 'Z', 'AB', 'AD', 'AE', 'AG',
        ];

        foreach ($columnsToClear as $column) {
            $worksheet->setCellValue($column.$rowNumber, null);
        }
    }
}
