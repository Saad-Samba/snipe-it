<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\Project;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class ProjectRequestsReuseAnalysisExport
{
    private const HEADER_ROW = 3;
    private const DATA_START_ROW = 4;
    private const TEMPLATE_DATA_ROW = 4;
    private const TEMPLATE_SAMPLE_ROW_COUNT = 5;
    private const TEMPLATE_TOTAL_ROW = 9;
    private const LAST_TEMPLATE_COLUMN = 'N';

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

        $this->removeSpacerColumns($worksheet);
        $this->rewriteHeaders($worksheet);

        $requests = $requests
            ->sortBy([
                fn (CheckoutRequest $checkoutRequest) => mb_strtolower((string) optional($checkoutRequest->requestedDiscipline)->name),
                fn (CheckoutRequest $checkoutRequest) => mb_strtolower((string) $checkoutRequest->name()),
            ])
            ->values();

        $requestCount = $requests->count();
        $dataRowCount = max($requestCount, 1);
        $totalRowIndex = self::TEMPLATE_TOTAL_ROW - (self::TEMPLATE_SAMPLE_ROW_COUNT - 1);

        if (self::TEMPLATE_SAMPLE_ROW_COUNT > 1) {
            $worksheet->removeRow(self::TEMPLATE_DATA_ROW + 1, self::TEMPLATE_SAMPLE_ROW_COUNT - 1);
        }

        if ($dataRowCount > 1) {
            $worksheet->insertNewRowBefore($totalRowIndex, $dataRowCount - 1);

            for ($row = self::TEMPLATE_DATA_ROW + 1; $row <= self::DATA_START_ROW + $dataRowCount - 1; $row++) {
                $this->copyRowStyle($worksheet, self::TEMPLATE_DATA_ROW, $row);
            }

            $totalRowIndex += $dataRowCount - 1;
        }

        $lastDataRow = self::DATA_START_ROW + $dataRowCount - 1;

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

            $totals['total_need_cost'] += (float) ($rowData['G'] ?? 0);
            $totals['reuse_value'] += (float) ($rowData['J'] ?? 0);
            $totals['net_saving'] += (float) ($rowData['K'] ?? 0);
            $totals['amount_to_buy'] += (float) ($rowData['M'] ?? 0);

            foreach ($rowData as $column => $value) {
                $worksheet->setCellValue($column.$rowNumber, $value);
            }

            $this->centerNumericCells($worksheet, $rowNumber);
        }

        $this->clearWritableColumns($worksheet, $totalRowIndex);
        $worksheet->setCellValue('D'.$totalRowIndex, 'Total (USD)');
        $worksheet->setCellValue('G'.$totalRowIndex, round($totals['total_need_cost'], 2));
        $worksheet->setCellValue('J'.$totalRowIndex, round($totals['reuse_value'], 2));
        $worksheet->setCellValue('K'.$totalRowIndex, round($totals['net_saving'], 2));
        $worksheet->setCellValue('M'.$totalRowIndex, round($totals['amount_to_buy'], 2));
        $this->centerNumericCells($worksheet, $totalRowIndex);

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
            'F' => $requiredQuantity,
            'G' => $unitPrice !== null ? round($unitPrice * $requiredQuantity, 2) : null,
            'H' => null,
            'I' => $reuseQuantity,
            'J' => $reuseValue,
            'K' => $reuseValue,
            'L' => $procurementShortfall,
            'M' => $amountToBuy,
            'N' => $this->reservedAssetSerials($checkoutRequest),
        ];
    }

    private function rewriteHeaders(Worksheet $worksheet): void
    {
        $worksheet->setCellValue('B'.self::HEADER_ROW, 'Discipline');
        $worksheet->setCellValue('C'.self::HEADER_ROW, 'Category');
        $worksheet->setCellValue('D'.self::HEADER_ROW, 'Model');
        $worksheet->setCellValue('E'.self::HEADER_ROW, 'Reference Price');
        $worksheet->setCellValue('F'.self::HEADER_ROW, 'Quantity');
        $worksheet->setCellValue('G'.self::HEADER_ROW, 'Total Need Cost');
        $worksheet->setCellValue('H'.self::HEADER_ROW, 'Transfer Cost');
        $worksheet->setCellValue('I'.self::HEADER_ROW, 'Reuse Qty');
        $worksheet->setCellValue('J'.self::HEADER_ROW, 'Reuse Value');
        $worksheet->setCellValue('K'.self::HEADER_ROW, 'Net Saving');
        $worksheet->setCellValue('L'.self::HEADER_ROW, 'Shortfall');
        $worksheet->setCellValue('M'.self::HEADER_ROW, 'Amount to Buy');
        $worksheet->setCellValue('N'.self::HEADER_ROW, 'Reused Asset List (S/N)');
    }

    private function removeSpacerColumns(Worksheet $worksheet): void
    {
        foreach (['S', 'P', 'N', 'K', 'I', 'F'] as $column) {
            $worksheet->removeColumn($column, 1);
        }
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

    private function reservedAssetSerials(CheckoutRequest $checkoutRequest): ?string
    {
        $serials = $checkoutRequest->reservedAssetsQuery()
            ->orderBy('serial')
            ->pluck('serial')
            ->filter(fn ($serial) => filled($serial))
            ->values();

        if ($serials->isEmpty()) {
            return null;
        }

        return $serials->implode(', ');
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
            'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N',
        ];

        foreach ($columnsToClear as $column) {
            $worksheet->setCellValue($column.$rowNumber, null);
        }
    }

    private function centerNumericCells(Worksheet $worksheet, int $rowNumber): void
    {
        foreach (['E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M'] as $column) {
            $worksheet->getStyle($column.$rowNumber)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }
    }
}
