<?php

namespace App\Console\Commands;

use App\Models\OffboardingReportDelivery;
use App\Models\OffboardingReportRun;
use App\Notifications\OffboardingAssignmentsNotification;
use App\Services\Offboarding\OffboardingReportProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ProcessOffboardingReport extends Command
{
    protected $signature = 'snipeit:process-offboarding-report
                            {csv : Path to the Active Directory disabled-accounts CSV}
                            {--send : Send consolidated RAC notifications}
                            {--recipient-override= : Deliver every notification to one test recipient}
                            {--force : Resend notifications already delivered for this report}';

    protected $description = 'Review disabled AD accounts for assigned LEAMS assets and licenses.';

    public function handle(OffboardingReportProcessor $processor): int
    {
        $csvPath = (string) $this->argument('csv');
        $override = trim((string) $this->option('recipient-override'));
        if ($override !== '' && filter_var($override, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('The recipient override must be a valid email address.');

            return self::FAILURE;
        }
        $sourceHash = is_file($csvPath) ? hash_file('sha256', $csvPath) : false;
        if ($sourceHash === false) {
            $this->error("CSV file is not readable: {$csvPath}");

            return self::FAILURE;
        }

        try {
            $result = $processor->process($csvPath);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $run = OffboardingReportRun::query()->updateOrCreate(
            ['source_hash' => $sourceHash],
            [
                'source_filename' => basename($csvPath),
                'status' => OffboardingReportRun::STATUS_REVIEWED,
                'summary' => $result['summary'],
                'processed_at' => now(),
            ]
        );

        $this->renderSummary($result);

        if (! $this->option('send')) {
            $this->info('Dry-run complete. No email was sent and LEAMS inventory was not changed.');

            return self::SUCCESS;
        }

        $deliveryResult = $this->sendNotifications($run, $result['notifications']);
        $run->update([
            'status' => $deliveryResult['failed'] > 0
                ? OffboardingReportRun::STATUS_PARTIAL_FAILURE
                : ($deliveryResult['mode'] === 'test'
                    ? OffboardingReportRun::STATUS_TEST_SENT
                    : OffboardingReportRun::STATUS_SENT),
            'summary' => array_merge($result['summary'], [
                'emails_sent' => $deliveryResult['sent'],
                'emails_failed' => $deliveryResult['failed'],
                'emails_skipped' => $deliveryResult['skipped'],
            ]),
        ]);

        $this->info(sprintf(
            'Email delivery complete: %d sent, %d failed, %d already delivered.',
            $deliveryResult['sent'],
            $deliveryResult['failed'],
            $deliveryResult['skipped']
        ));

        return $deliveryResult['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function renderSummary(array $result): void
    {
        $summary = $result['summary'];
        $this->table(['Metric', 'Count'], [
            ['Candidate accounts', $summary['candidate_accounts']],
            ['Skipped non-person accounts', $summary['skipped_non_person_accounts']],
            ['Matched users', $summary['matched_users']],
            ['Unresolved users', $summary['unresolved_users']],
            ['Users with assets or licenses', $summary['users_with_obligations']],
            ['Assets and licenses', $summary['obligations']],
            ['Routing warnings', $summary['routing_warnings']],
            ['Notification recipients', $summary['notification_recipients']],
        ]);

        $this->table(
            ['Row', 'Source user', 'Match', 'Assets/licenses', 'Recipients', 'Warnings'],
            collect($result['reviews'])->map(fn (array $review) => [
                $review['source_row'],
                $review['source_username'],
                $review['match_status'],
                $review['obligation_count'],
                implode(', ', $review['recipients']),
                implode(' | ', $review['routing_warnings']),
            ])->all()
        );
    }

    private function sendNotifications(OffboardingReportRun $run, array $notifications): array
    {
        $override = trim((string) $this->option('recipient-override'));
        $mode = $override !== '' ? 'test' : 'live';
        $force = (bool) $this->option('force');
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($notifications as $intendedRecipient => $lines) {
            $deliveryRecipient = $override ?: $intendedRecipient;
            $alreadySent = $run->deliveries()
                ->where('mode', $mode)
                ->where('intended_recipient', $intendedRecipient)
                ->where('delivery_recipient', $deliveryRecipient)
                ->where('status', OffboardingReportDelivery::STATUS_SENT)
                ->exists();
            if ($alreadySent && ! $force) {
                $skipped++;
                continue;
            }

            $delivery = $run->deliveries()->create([
                'mode' => $mode,
                'intended_recipient' => $intendedRecipient,
                'delivery_recipient' => $deliveryRecipient,
                'status' => OffboardingReportDelivery::STATUS_PENDING,
                'attempted_at' => now(),
            ]);

            try {
                Notification::route('mail', $deliveryRecipient)->notify(
                    new OffboardingAssignmentsNotification(
                        $lines,
                        $intendedRecipient,
                        $mode === 'test'
                    )
                );
                $delivery->update([
                    'status' => OffboardingReportDelivery::STATUS_SENT,
                    'sent_at' => now(),
                ]);
                $sent++;
            } catch (Throwable $exception) {
                report($exception);
                $delivery->update([
                    'status' => OffboardingReportDelivery::STATUS_FAILED,
                    'error' => $exception->getMessage(),
                ]);
                $failed++;
            }
        }

        return compact('mode', 'sent', 'failed', 'skipped');
    }
}
