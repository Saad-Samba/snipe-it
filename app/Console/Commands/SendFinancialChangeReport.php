<?php

namespace App\Console\Commands;

use App\Mail\FinancialChangeRecipientIssuesMail;
use App\Mail\FinancialChangeReportMail;
use App\Models\FinancialChangeDelivery;
use App\Models\FinancialChangeEvent;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendFinancialChangeReport extends Command
{
    protected $signature = 'snipeit:financial-change-report {--force : Bypass the 14-day cadence check for a manual run}';

    protected $description = 'Send company-scoped finance reports for asset status and company changes.';

    public function handle(): int
    {
        $settings = Setting::getSettings();
        $forcedRun = (bool) $this->option('force');

        if (! $settings?->finance_report_enabled) {
            $this->info('Finance reporting is disabled.');
            return self::SUCCESS;
        }

        if (empty($settings->finance_report_email)) {
            $this->info('No finance report recipients configured.');
            return self::SUCCESS;
        }

        if (! $forcedRun && ! $this->shouldRunForCurrentCadence($settings)) {
            $this->info('Finance report cadence has not elapsed yet.');
            return self::SUCCESS;
        }

        [$validUsers, $issues] = $this->resolveRecipients($settings->finance_report_email);
        $sentReports = 0;
        $deliveredEvents = 0;

        foreach ($validUsers as $user) {
            $statusEvents = FinancialChangeEvent::query()
                ->with(['asset', 'company', 'previousStatus', 'newStatus', 'changedBy'])
                ->where('event_type', 'status_change')
                ->where('company_id', $user->company_id)
                ->whereDoesntHave('deliveries', fn ($query) => $query->where('user_id', $user->id))
                ->orderBy('effective_at')
                ->get();

            $companyEvents = FinancialChangeEvent::query()
                ->with(['asset', 'previousCompany', 'newCompany', 'changedBy'])
                ->where('event_type', 'company_change')
                ->where(function ($query) use ($user) {
                    $query->where('previous_company_id', $user->company_id)
                        ->orWhere('new_company_id', $user->company_id);
                })
                ->whereDoesntHave('deliveries', fn ($query) => $query->where('user_id', $user->id))
                ->orderBy('effective_at')
                ->get()
                ->map(function (FinancialChangeEvent $event) use ($user) {
                    $event->direction = $event->new_company_id === $user->company_id ? 'entered' : 'left';
                    return $event;
                });

            if ($statusEvents->isEmpty() && $companyEvents->isEmpty()) {
                continue;
            }

            Mail::to($user)->send(new FinancialChangeReportMail($user, $statusEvents, $companyEvents, $user->company));

            $eventCount = $statusEvents->count() + $companyEvents->count();

            $deliveries = $statusEvents
                ->concat($companyEvents)
                ->map(fn (FinancialChangeEvent $event) => [
                    'financial_change_event_id' => $event->id,
                    'user_id' => $user->id,
                    'reported_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            FinancialChangeDelivery::insertOrIgnore($deliveries);
            $sentReports++;
            $deliveredEvents += $eventCount;

            $this->info('Sent finance report to '.$user->email);
        }

        if ($issues->isNotEmpty()) {
            foreach ($issues as $issue) {
                Log::warning('Finance report recipient issue', $issue);
                $this->warn($issue['email'].': '.$issue['reason']);
            }

            if (! empty($settings->alert_email)) {
                $recipients = collect(explode(',', $settings->alert_email))
                    ->map(fn ($email) => trim($email))
                    ->filter()
                    ->all();

                Mail::to($recipients)->send(new FinancialChangeRecipientIssuesMail($issues));
            }
        }

        if ($sentReports === 0) {
            $message = $validUsers->isEmpty()
                ? 'No valid finance report recipients resolved.'
                : 'No undelivered financial change events found for configured recipients.';

            $this->info($message);
            Log::info('Finance report completed without deliveries.', [
                'valid_recipient_count' => $validUsers->count(),
                'recipient_issue_count' => $issues->count(),
            ]);

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Sent %d finance report(s) covering %d event(s).',
            $sentReports,
            $deliveredEvents
        ));

        if (! $forcedRun) {
            $settings->finance_report_last_sent_at = now();
            $settings->save();
        }

        return self::SUCCESS;
    }

    protected function shouldRunForCurrentCadence(Setting $settings): bool
    {
        $runDate = now()->startOfDay();

        if (! $settings->finance_report_anchor_date instanceof Carbon) {
            $settings->finance_report_anchor_date = $runDate;
            $settings->save();

            return true;
        }

        return $settings->finance_report_anchor_date
            ->copy()
            ->startOfDay()
            ->diffInWeeks($runDate) % 2 === 0;
    }

    protected function resolveRecipients(string $recipientList): array
    {
        $validUsers = collect();
        $issues = collect();

        collect(explode(',', $recipientList))
            ->map(fn ($email) => trim(mb_strtolower($email)))
            ->filter()
            ->unique()
            ->each(function (string $email) use (&$validUsers, &$issues) {
                $users = User::query()->whereRaw('LOWER(email) = ?', [$email])->get();

                if ($users->count() === 0) {
                    $issues->push(['email' => $email, 'reason' => 'No matching user found.']);
                    return;
                }

                if ($users->count() > 1) {
                    $issues->push(['email' => $email, 'reason' => 'Multiple users share this email address.']);
                    return;
                }

                $user = $users->first();

                if (! $user->activated) {
                    $issues->push(['email' => $email, 'reason' => 'User is inactive.']);
                    return;
                }

                if (empty($user->company_id)) {
                    $issues->push(['email' => $email, 'reason' => 'User has no company scope.']);
                    return;
                }

                $validUsers->put($user->id, $user);
            });

        return [$validUsers, $issues];
    }
}
