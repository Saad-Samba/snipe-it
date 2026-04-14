<?php

namespace Tests\Feature\Console;

use App\Mail\FinancialChangeRecipientIssuesMail;
use App\Mail\FinancialChangeReportMail;
use App\Models\Asset;
use App\Models\Company;
use App\Models\FinancialChangeDelivery;
use App\Models\FinancialChangeEvent;
use App\Models\Setting;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendFinancialChangeReportTest extends TestCase
{
    public function testCommandSendsCompanyScopedReportsAndTracksDeliveries()
    {
        Mail::fake();

        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $recipientA = User::factory()->create(['email' => 'finance-a@example.com', 'company_id' => $companyA->id, 'activated' => 1]);
        $recipientB = User::factory()->create(['email' => 'finance-b@example.com', 'company_id' => $companyB->id, 'activated' => 1]);
        $statusOld = Statuslabel::factory()->create();
        $statusNew = Statuslabel::factory()->create();

        $eventA = FinancialChangeEvent::create([
            'asset_id' => Asset::factory()->create(['company_id' => $companyA->id])->id,
            'event_type' => 'status_change',
            'company_id' => $companyA->id,
            'previous_status_id' => $statusOld->id,
            'new_status_id' => $statusNew->id,
            'effective_at' => now()->subDay(),
        ]);

        $eventB = FinancialChangeEvent::create([
            'asset_id' => Asset::factory()->create(['company_id' => $companyB->id])->id,
            'event_type' => 'status_change',
            'company_id' => $companyB->id,
            'previous_status_id' => $statusOld->id,
            'new_status_id' => $statusNew->id,
            'effective_at' => now()->subDay(),
        ]);

        $this->settings->enableFinanceReport('finance-a@example.com,finance-b@example.com');

        $this->artisan('snipeit:financial-change-report')->assertExitCode(0);

        Mail::assertSent(FinancialChangeReportMail::class, function (FinancialChangeReportMail $mail) use ($recipientA, $eventA) {
            $attachments = $mail->attachments();

            return $mail->hasTo($recipientA->email)
                && $mail->statusEvents->pluck('id')->contains($eventA->id)
                && $mail->statusEvents->count() === 1
                && count($attachments) === 1
                && str_contains(($attachments[0]->as ?? ''), 'financial-change-report-');
        });

        Mail::assertSent(FinancialChangeReportMail::class, function (FinancialChangeReportMail $mail) use ($recipientB, $eventB) {
            return $mail->hasTo($recipientB->email)
                && $mail->statusEvents->pluck('id')->contains($eventB->id)
                && $mail->statusEvents->count() === 1;
        });

        $this->assertDatabaseHas('financial_change_deliveries', [
            'financial_change_event_id' => $eventA->id,
            'user_id' => $recipientA->id,
        ]);

        $this->assertDatabaseHas('financial_change_deliveries', [
            'financial_change_event_id' => $eventB->id,
            'user_id' => $recipientB->id,
        ]);
    }

    public function testCommandWarnsAboutInvalidRecipientsAndStillSendsValidReports()
    {
        Mail::fake();

        $company = Company::factory()->create();
        $recipient = User::factory()->create(['email' => 'finance@example.com', 'company_id' => $company->id, 'activated' => 1]);
        User::factory()->create(['email' => 'duplicate@example.com', 'company_id' => $company->id, 'activated' => 1]);
        User::factory()->create(['email' => 'duplicate@example.com', 'company_id' => $company->id, 'activated' => 1]);
        User::factory()->create(['email' => 'nocompany@example.com', 'company_id' => null, 'activated' => 1]);

        $event = FinancialChangeEvent::create([
            'asset_id' => Asset::factory()->create(['company_id' => $company->id])->id,
            'event_type' => 'status_change',
            'company_id' => $company->id,
            'previous_status_id' => Statuslabel::factory()->create()->id,
            'new_status_id' => Statuslabel::factory()->create()->id,
            'effective_at' => now()->subDay(),
        ]);

        $this->settings->enableAlertEmail('alerts@example.com')->enableFinanceReport('finance@example.com,missing@example.com,duplicate@example.com,nocompany@example.com');

        $this->artisan('snipeit:financial-change-report')->assertExitCode(0);

        Mail::assertSent(FinancialChangeReportMail::class, function (FinancialChangeReportMail $mail) use ($recipient, $event) {
            return $mail->hasTo($recipient->email) && $mail->statusEvents->pluck('id')->contains($event->id);
        });

        Mail::assertSent(FinancialChangeRecipientIssuesMail::class, function (FinancialChangeRecipientIssuesMail $mail) {
            return $mail->hasTo('alerts@example.com') && $mail->issues->count() === 3;
        });
    }

    public function testCommandDoesNotResendAlreadyDeliveredEvents()
    {
        Mail::fake();

        $company = Company::factory()->create();
        $recipient = User::factory()->create(['email' => 'finance@example.com', 'company_id' => $company->id, 'activated' => 1]);
        $event = FinancialChangeEvent::create([
            'asset_id' => Asset::factory()->create(['company_id' => $company->id])->id,
            'event_type' => 'status_change',
            'company_id' => $company->id,
            'previous_status_id' => Statuslabel::factory()->create()->id,
            'new_status_id' => Statuslabel::factory()->create()->id,
            'effective_at' => now()->subDay(),
        ]);

        FinancialChangeDelivery::create([
            'financial_change_event_id' => $event->id,
            'user_id' => $recipient->id,
            'reported_at' => now()->subDay(),
        ]);

        $this->settings->enableFinanceReport('finance@example.com');

        $this->artisan('snipeit:financial-change-report')
            ->expectsOutput('No undelivered financial change events found for configured recipients.')
            ->assertExitCode(0);

        Mail::assertNotSent(FinancialChangeReportMail::class);
        $this->assertNull(Setting::getSettings()->finance_report_last_sent_at);
    }

    public function testCommandCanBypassCadenceWithForceOption()
    {
        Mail::fake();

        $company = Company::factory()->create();
        $recipient = User::factory()->create(['email' => 'finance@example.com', 'company_id' => $company->id, 'activated' => 1]);
        $event = FinancialChangeEvent::create([
            'asset_id' => Asset::factory()->create(['company_id' => $company->id])->id,
            'event_type' => 'status_change',
            'company_id' => $company->id,
            'previous_status_id' => Statuslabel::factory()->create()->id,
            'new_status_id' => Statuslabel::factory()->create()->id,
            'effective_at' => now()->subDay(),
        ]);

        $this->settings->enableFinanceReport('finance@example.com')->set([
            'finance_report_anchor_date' => now()->subWeek(),
            'finance_report_last_sent_at' => now()->subWeeks(2),
        ]);

        $this->artisan('snipeit:financial-change-report --force')->assertExitCode(0);

        Mail::assertSent(FinancialChangeReportMail::class, function (FinancialChangeReportMail $mail) use ($recipient, $event) {
            return $mail->hasTo($recipient->email)
                && $mail->statusEvents->pluck('id')->contains($event->id);
        });

        $this->assertEquals(
            now()->subWeeks(2)->toDateTimeString(),
            Setting::getSettings()->finance_report_last_sent_at?->toDateTimeString()
        );
    }

    public function testCommandSkipsOffWeeksForScheduledCadence()
    {
        Mail::fake();

        $company = Company::factory()->create();
        User::factory()->create(['email' => 'finance@example.com', 'company_id' => $company->id, 'activated' => 1]);

        FinancialChangeEvent::create([
            'asset_id' => Asset::factory()->create(['company_id' => $company->id])->id,
            'event_type' => 'status_change',
            'company_id' => $company->id,
            'previous_status_id' => Statuslabel::factory()->create()->id,
            'new_status_id' => Statuslabel::factory()->create()->id,
            'effective_at' => now()->subDay(),
        ]);

        $this->settings->enableFinanceReport('finance@example.com')->set([
            'finance_report_anchor_date' => now()->subWeek(),
        ]);

        $this->artisan('snipeit:financial-change-report')
            ->expectsOutput('Finance report cadence has not elapsed yet.')
            ->assertExitCode(0);

        Mail::assertNotSent(FinancialChangeReportMail::class);
    }

    public function testCommandRequiresFullFourteenDaysBeforeScheduledRun()
    {
        Mail::fake();

        $company = Company::factory()->create();
        User::factory()->create(['email' => 'finance@example.com', 'company_id' => $company->id, 'activated' => 1]);

        FinancialChangeEvent::create([
            'asset_id' => Asset::factory()->create(['company_id' => $company->id])->id,
            'event_type' => 'status_change',
            'company_id' => $company->id,
            'previous_status_id' => Statuslabel::factory()->create()->id,
            'new_status_id' => Statuslabel::factory()->create()->id,
            'effective_at' => now()->subDay(),
        ]);

        $this->settings->enableFinanceReport('finance@example.com')->set([
            'finance_report_anchor_date' => now()->subDays(13),
        ]);

        $this->artisan('snipeit:financial-change-report')
            ->expectsOutput('Finance report cadence has not elapsed yet.')
            ->assertExitCode(0);

        Mail::assertNotSent(FinancialChangeReportMail::class);
    }

    public function testCommandReportsWhenNoEventsNeedDelivery()
    {
        Mail::fake();

        $company = Company::factory()->create();
        User::factory()->create(['email' => 'finance@example.com', 'company_id' => $company->id, 'activated' => 1]);

        $this->settings->enableFinanceReport('finance@example.com');

        $this->artisan('snipeit:financial-change-report')
            ->expectsOutput('No undelivered financial change events found for configured recipients.')
            ->assertExitCode(0);

        Mail::assertNotSent(FinancialChangeReportMail::class);
        $this->assertNull(Setting::getSettings()->finance_report_last_sent_at);
    }

    public function testCommandIgnoresSoftDeletedUsersWhenResolvingRecipients()
    {
        Mail::fake();

        $company = Company::factory()->create();
        $recipient = User::factory()->create([
            'email' => 'finance@example.com',
            'company_id' => $company->id,
            'activated' => 1,
        ]);
        User::factory()->deleted()->create([
            'email' => 'finance@example.com',
            'company_id' => $company->id,
            'activated' => 1,
        ]);

        $event = FinancialChangeEvent::create([
            'asset_id' => Asset::factory()->create(['company_id' => $company->id])->id,
            'event_type' => 'status_change',
            'company_id' => $company->id,
            'previous_status_id' => Statuslabel::factory()->create()->id,
            'new_status_id' => Statuslabel::factory()->create()->id,
            'effective_at' => now()->subDay(),
        ]);

        $this->settings
            ->enableAlertEmail('alerts@example.com')
            ->enableFinanceReport('finance@example.com');

        $this->artisan('snipeit:financial-change-report')->assertExitCode(0);

        Mail::assertSent(FinancialChangeReportMail::class, function (FinancialChangeReportMail $mail) use ($recipient, $event) {
            return $mail->hasTo($recipient->email)
                && $mail->statusEvents->pluck('id')->contains($event->id);
        });

        Mail::assertNotSent(FinancialChangeRecipientIssuesMail::class);
    }

    public function testFinancialChangeReportMailBuildsCsvAttachment()
    {
        $company = Company::factory()->create(['name' => 'REC']);
        $actor = User::factory()->create(['first_name' => 'Saad', 'last_name' => 'Samba', 'activated' => 1]);
        $recipient = User::factory()->create(['email' => 'finance@example.com', 'company_id' => $company->id, 'activated' => 1]);
        $statusEvent = FinancialChangeEvent::create([
            'asset_id' => Asset::factory()->create([
                'company_id' => $company->id,
                'asset_tag' => '50400001712',
            ])->id,
            'event_type' => 'status_change',
            'company_id' => $company->id,
            'previous_status_id' => Statuslabel::factory()->create(['name' => 'In Stock'])->id,
            'new_status_id' => Statuslabel::factory()->create(['name' => 'Capitalized'])->id,
            'changed_by' => $actor->id,
            'effective_at' => now()->subDay(),
        ]);

        $statusEvent->load(['asset', 'company', 'previousStatus', 'newStatus', 'changedBy']);

        $mail = new FinancialChangeReportMail(
            $recipient,
            collect([$statusEvent]),
            collect(),
            $company,
        );

        $attachment = $mail->attachments()[0];
        $csv = null;

        $attachment->attachWith(
            fn () => null,
            function ($data, $attachment) use (&$csv) {
                $csv = $data();

                return [$attachment->as ?? null, $attachment->mime ?? null];
            }
        );

        $this->assertStringContainsString('event_type,asset_id,previous_status,new_status,previous_company,new_company,effective_at,changed_by', $csv);
        $this->assertStringContainsString('status_change', $csv);
        $this->assertStringContainsString((string) $statusEvent->asset_id, $csv);
        $this->assertStringContainsString('Capitalized', $csv);
    }
}
