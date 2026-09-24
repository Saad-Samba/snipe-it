<?php

namespace App\Console\Commands;

use App\Services\FmcsReadinessAuditor;
use Illuminate\Console\Command;
use League\Csv\EscapeFormula;
use RuntimeException;

class AuditFmcsReadiness extends Command
{
    protected $signature = 'snipeit:audit-fmcs-readiness
                            {--include-inactive-users : Include inactive users in Site assignment checks}
                            {--include-deleted : Include soft-deleted records}
                            {--json : Output the complete report as JSON}
                            {--csv= : Write all findings to a new CSV file}
                            {--limit=50 : Maximum findings shown in the console; use 0 for summary only}';

    protected $description = 'Read-only audit of Site assignments and relationships before enabling Full Multiple Site Support.';

    public function handle(FmcsReadinessAuditor $auditor): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0],
        ]);

        if ($limit === false) {
            $this->error('The --limit value must be a non-negative integer.');

            return self::INVALID;
        }

        $report = $auditor->audit(
            (bool) $this->option('include-inactive-users'),
            (bool) $this->option('include-deleted')
        );

        if ($csvPath = $this->option('csv')) {
            try {
                $this->writeCsv((string) $csvPath, $report['findings']);
            } catch (RuntimeException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report['totals']['blocking'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info('FMCS readiness audit completed. No records were changed.');
        $this->table(
            ['Severity', 'Finding', 'Resource', 'Count'],
            array_map(fn (array $row): array => [
                ucfirst($row['severity']),
                $row['code'],
                $row['resource_type'],
                $row['count'],
            ], $report['summary'])
        );

        $this->newLine();
        $this->line('Record coverage by Site:');
        $this->table(
            ['Resource', 'Site ID', 'Site', 'Count'],
            array_map(fn (array $row): array => [
                $row['resource_type'],
                $row['site_id'],
                $row['site_name'] ?? 'Unassigned or invalid',
                $row['count'],
            ], $report['coverage'])
        );

        if ($csvPath) {
            $this->info('CSV report written to '.$csvPath);
        }

        if ($limit > 0 && $report['findings'] !== []) {
            $visibleFindings = array_slice($report['findings'], 0, $limit);
            $this->newLine();
            $this->table(
                ['Severity', 'Finding', 'Resource', 'ID', 'Label', 'Site', 'Related', 'Related Site', 'Context'],
                array_map(fn (array $finding): array => [
                    $finding['severity'],
                    $finding['code'],
                    $finding['resource_type'],
                    $finding['resource_id'],
                    $finding['label'],
                    $finding['site_id'],
                    $finding['related_type'] && $finding['related_id']
                        ? $finding['related_type'].' #'.$finding['related_id']
                        : null,
                    $finding['related_site_id'],
                    $finding['context'],
                ], $visibleFindings)
            );

            if (count($report['findings']) > $limit) {
                $this->warn((count($report['findings']) - $limit).' additional findings omitted. Use --json or --csv for the complete report.');
            }
        }

        $this->newLine();
        $this->line(sprintf(
            'Totals: %d blocking, %d warning, %d informational.',
            $report['totals']['blocking'],
            $report['totals']['warning'],
            $report['totals']['info']
        ));

        if ($report['totals']['blocking'] > 0) {
            $this->error('FMCS readiness blockers found. Resolve them before enabling Full Multiple Site Support.');

            return self::FAILURE;
        }

        $this->info('No FMCS readiness blockers found. Review any location-scoping warnings separately.');

        return self::SUCCESS;
    }

    private function writeCsv(string $path, array $findings): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new RuntimeException('CSV directory is not writable: '.$directory);
        }

        if (file_exists($path)) {
            throw new RuntimeException('CSV report already exists: '.$path);
        }

        $handle = @fopen($path, 'xb');

        if ($handle === false) {
            throw new RuntimeException('Could not create CSV report (the file may already exist): '.$path);
        }

        chmod($path, 0600);
        $formatter = new EscapeFormula('`');
        fputcsv($handle, [
            'severity', 'code', 'resource_type', 'resource_id', 'label', 'site_id',
            'related_type', 'related_id', 'related_site_id', 'context', 'message',
        ]);

        foreach ($findings as $finding) {
            $row = [
                $finding['severity'],
                $finding['code'],
                $finding['resource_type'],
                $finding['resource_id'],
                $finding['label'],
                $finding['site_id'],
                $finding['related_type'],
                $finding['related_id'],
                $finding['related_site_id'],
                $finding['context'],
                $finding['message'],
            ];
            fputcsv(
                $handle,
                config('app.escape_formulas') === false ? $row : $formatter->escapeRecord($row)
            );
        }

        fclose($handle);
    }
}
