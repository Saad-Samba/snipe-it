<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\Project;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class ProjectRequestsReuseAnalysisExport
{
    private const RAW_SHEET_NAME = 'Reuse Analysis';
    private const SUMMARY_SHEET_NAME = 'Discipline Summary';
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
        $rawWorksheet = $spreadsheet->getSheetByName(self::RAW_SHEET_NAME);

        if (! $rawWorksheet instanceof Worksheet) {
            throw new RuntimeException('Reuse analysis worksheet not found in template.');
        }

        $requests = $requests
            ->sortBy([
                fn (CheckoutRequest $checkoutRequest) => mb_strtolower((string) optional($checkoutRequest->requestedDiscipline)->name),
                fn (CheckoutRequest $checkoutRequest) => mb_strtolower((string) $checkoutRequest->name()),
            ])
            ->values();

        $this->prepareRawWorksheet($rawWorksheet, $requests);
        $this->buildSummaryWorksheet($spreadsheet, $requests);

        $outputPath = tempnam(sys_get_temp_dir(), 'reuse-analysis-');
        if ($outputPath === false) {
            throw new RuntimeException('Unable to create a temporary export file.');
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($outputPath);
        $spreadsheet->disconnectWorksheets();

        return $outputPath;
    }

    /**
     * @param  Collection<int, CheckoutRequest>  $requests
     */
    private function prepareRawWorksheet(Worksheet $worksheet, Collection $requests): void
    {
        $this->removeSpacerColumns($worksheet);
        $this->rewriteRawHeaders($worksheet);

        if (self::TEMPLATE_SAMPLE_ROW_COUNT > 1) {
            $worksheet->removeRow(self::TEMPLATE_DATA_ROW + 1, self::TEMPLATE_SAMPLE_ROW_COUNT - 1);
        }

        $worksheet->removeRow(self::DATA_START_ROW + 1, 1);

        $requestCount = $requests->count();
        $dataRowCount = max($requestCount, 1);

        if ($dataRowCount > 1) {
            $worksheet->insertNewRowBefore(self::DATA_START_ROW + 1, $dataRowCount - 1);

            for ($row = self::TEMPLATE_DATA_ROW + 1; $row <= self::DATA_START_ROW + $dataRowCount - 1; $row++) {
                $this->copyRowStyle($worksheet, self::TEMPLATE_DATA_ROW, $row);
            }
        }

        $lastDataRow = self::DATA_START_ROW + $dataRowCount - 1;

        foreach (range(self::DATA_START_ROW, $lastDataRow) as $rowNumber) {
            $this->clearRawWritableColumns($worksheet, $rowNumber);
        }

        foreach ($requests->values() as $index => $checkoutRequest) {
            $rowNumber = self::DATA_START_ROW + $index;

            foreach ($this->buildRawRowData($checkoutRequest) as $column => $value) {
                $worksheet->setCellValue($column.$rowNumber, $value);
            }

            $this->centerNumericCells($worksheet, $rowNumber, ['E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M']);
        }
    }

    /**
     * @param  Collection<int, CheckoutRequest>  $requests
     */
    private function buildSummaryWorksheet(Spreadsheet $spreadsheet, Collection $requests): void
    {
        $existingWorksheet = $spreadsheet->getSheetByName(self::SUMMARY_SHEET_NAME);
        if ($existingWorksheet instanceof Worksheet) {
            $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($existingWorksheet));
        }

        $worksheet = new Worksheet($spreadsheet, self::SUMMARY_SHEET_NAME);
        $spreadsheet->addSheet($worksheet);

        $headers = [
            'A1' => 'Discipline',
            'B1' => 'Quantity',
            'C1' => 'Total Need Cost',
            'D1' => 'Transfer Cost',
            'E1' => 'Reuse Qty',
            'F1' => 'Reuse Value',
            'G1' => 'Net Saving',
            'H1' => 'Shortfall',
            'I1' => 'Amount to Buy',
        ];

        foreach ($headers as $cell => $value) {
            $worksheet->setCellValue($cell, $value);
        }

        $disciplineGroups = $requests->groupBy(
            fn (CheckoutRequest $checkoutRequest) => (string) (optional($checkoutRequest->requestedDiscipline)->name ?: 'Unassigned')
        );

        $rowNumber = 2;
        $grandTotals = [
            'quantity' => 0,
            'total_need_cost' => 0.0,
            'reuse_qty' => 0,
            'reuse_value' => 0.0,
            'net_saving' => 0.0,
            'shortfall' => 0,
            'amount_to_buy' => 0.0,
        ];

        foreach ($disciplineGroups as $discipline => $disciplineRequests) {
            $summary = $this->summarizeDisciplineRequests($disciplineRequests);

            $worksheet->setCellValue('A'.$rowNumber, $discipline);
            $worksheet->setCellValue('B'.$rowNumber, $summary['quantity']);
            $worksheet->setCellValue('C'.$rowNumber, $summary['total_need_cost']);
            $worksheet->setCellValue('D'.$rowNumber, null);
            $worksheet->setCellValue('E'.$rowNumber, $summary['reuse_qty']);
            $worksheet->setCellValue('F'.$rowNumber, $summary['reuse_value']);
            $worksheet->setCellValue('G'.$rowNumber, $summary['net_saving']);
            $worksheet->setCellValue('H'.$rowNumber, $summary['shortfall']);
            $worksheet->setCellValue('I'.$rowNumber, $summary['amount_to_buy']);

            $grandTotals['quantity'] += $summary['quantity'];
            $grandTotals['total_need_cost'] += $summary['total_need_cost'];
            $grandTotals['reuse_qty'] += $summary['reuse_qty'];
            $grandTotals['reuse_value'] += $summary['reuse_value'];
            $grandTotals['net_saving'] += $summary['net_saving'];
            $grandTotals['shortfall'] += $summary['shortfall'];
            $grandTotals['amount_to_buy'] += $summary['amount_to_buy'];

            $rowNumber++;
        }

        $worksheet->setCellValue('A'.$rowNumber, 'Grand Total');
        $worksheet->setCellValue('B'.$rowNumber, $grandTotals['quantity']);
        $worksheet->setCellValue('C'.$rowNumber, round($grandTotals['total_need_cost'], 2));
        $worksheet->setCellValue('D'.$rowNumber, null);
        $worksheet->setCellValue('E'.$rowNumber, $grandTotals['reuse_qty']);
        $worksheet->setCellValue('F'.$rowNumber, round($grandTotals['reuse_value'], 2));
        $worksheet->setCellValue('G'.$rowNumber, round($grandTotals['net_saving'], 2));
        $worksheet->setCellValue('H'.$rowNumber, $grandTotals['shortfall']);
        $worksheet->setCellValue('I'.$rowNumber, round($grandTotals['amount_to_buy'], 2));

        $worksheet->getStyle('A1:I1')->applyFromArray($this->summaryHeaderStyle());
        $worksheet->getStyle('A'.$rowNumber.':I'.$rowNumber)->applyFromArray($this->summaryTotalStyle());
        $this->centerNumericCells($worksheet, $rowNumber, ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I']);

        foreach (range(2, $rowNumber - 1) as $dataRow) {
            $this->centerNumericCells($worksheet, $dataRow, ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I']);
        }

        foreach (range('A', 'I') as $column) {
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }

        $worksheet->freezePane('A2');
    }

    private function buildRawRowData(CheckoutRequest $checkoutRequest): array
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

    /**
     * @param  Collection<int, CheckoutRequest>  $requests
     * @return array{quantity:int,total_need_cost:float,reuse_qty:int,reuse_value:float,net_saving:float,shortfall:int,amount_to_buy:float}
     */
    private function summarizeDisciplineRequests(Collection $requests): array
    {
        $summary = [
            'quantity' => 0,
            'total_need_cost' => 0.0,
            'reuse_qty' => 0,
            'reuse_value' => 0.0,
            'net_saving' => 0.0,
            'shortfall' => 0,
            'amount_to_buy' => 0.0,
        ];

        foreach ($requests as $checkoutRequest) {
            $rowData = $this->buildRawRowData($checkoutRequest);

            $summary['quantity'] += (int) ($rowData['F'] ?? 0);
            $summary['total_need_cost'] += (float) ($rowData['G'] ?? 0);
            $summary['reuse_qty'] += (int) ($rowData['I'] ?? 0);
            $summary['reuse_value'] += (float) ($rowData['J'] ?? 0);
            $summary['net_saving'] += (float) ($rowData['K'] ?? 0);
            $summary['shortfall'] += (int) ($rowData['L'] ?? 0);
            $summary['amount_to_buy'] += (float) ($rowData['M'] ?? 0);
        }

        $summary['total_need_cost'] = round($summary['total_need_cost'], 2);
        $summary['reuse_value'] = round($summary['reuse_value'], 2);
        $summary['net_saving'] = round($summary['net_saving'], 2);
        $summary['amount_to_buy'] = round($summary['amount_to_buy'], 2);

        return $summary;
    }

    private function rewriteRawHeaders(Worksheet $worksheet): void
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

    private function removeSpacerColumns(Worksheet $worksheet): void
    {
        foreach (['S', 'P', 'N', 'K', 'I', 'F'] as $column) {
            $worksheet->removeColumn($column, 1);
        }
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

    private function clearRawWritableColumns(Worksheet $worksheet, int $rowNumber): void
    {
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N'] as $column) {
            $worksheet->setCellValue($column.$rowNumber, null);
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function centerNumericCells(Worksheet $worksheet, int $rowNumber, array $columns): void
    {
        foreach ($columns as $column) {
            $worksheet->getStyle($column.$rowNumber)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }
    }

    private function summaryHeaderStyle(): array
    {
        return [
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9EDF7'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ];
    }

    private function summaryTotalStyle(): array
    {
        return [
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'EAF7FF'],
            ],
            'borders' => [
                'top' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
                'bottom' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ];
    }
}
