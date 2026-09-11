<?php

namespace Tests\Feature\Console;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\OffboardingReportDelivery;
use App\Models\OffboardingReportRun;
use App\Models\RegionalAssetCoordinatorAssignment;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\OffboardingAssignmentsNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProcessOffboardingReportTest extends TestCase
{
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_dry_run_matches_users_and_reports_only_assets_and_licenses(): void
    {
        Notification::fake();
        [$user] = $this->createRoutedAssignments();
        $csv = $this->makeCsv([
            $this->csvRow($user),
            $this->csvRow(null, ['SamAccountName' => 'missing.user', 'Email' => 'missing@example.com', 'EmployeeID' => 'MISSING']),
            $this->csvRow(null, ['SamAccountName' => 'AdminExample', 'Email' => '', 'EmployeeID' => '', 'Country' => 'Plant Site Administrators', 'Region' => 'Administrator Groups']),
        ]);

        $this->artisan('snipeit:process-offboarding-report', ['csv' => $csv])
            ->expectsOutput('Dry-run complete. No email was sent and LEAMS inventory was not changed.')
            ->assertExitCode(0);

        Notification::assertNothingSent();
        $run = OffboardingReportRun::query()->firstOrFail();
        $this->assertSame(2, $run->summary['candidate_accounts']);
        $this->assertSame(1, $run->summary['skipped_non_person_accounts']);
        $this->assertSame(1, $run->summary['matched_users']);
        $this->assertSame(1, $run->summary['unresolved_users']);
        $this->assertSame(2, $run->summary['obligations']);
        $this->assertSame(1, $run->summary['notification_recipients']);
        $this->assertSame(OffboardingReportRun::STATUS_REVIEWED, $run->status);
    }

    public function test_send_with_override_uses_native_notification_and_prevents_duplicate_delivery(): void
    {
        Notification::fake();
        [$user, $coordinator, $asset, $license] = $this->createRoutedAssignments();
        $csv = $this->makeCsv([$this->csvRow($user)]);
        $arguments = [
            'csv' => $csv,
            '--send' => true,
            '--recipient-override' => 'reviewer@example.com',
        ];

        $this->artisan('snipeit:process-offboarding-report', $arguments)
            ->expectsOutput('Email delivery complete: 1 sent, 0 failed, 0 already delivered.')
            ->assertExitCode(0);

        Notification::assertSentOnDemand(
            OffboardingAssignmentsNotification::class,
            function (OffboardingAssignmentsNotification $notification, array $channels, object $notifiable) use ($asset, $coordinator, $license) {
                $renderedMail = $notification->toMail($notifiable)->render();

                return $notifiable->routes['mail'] === 'reviewer@example.com'
                    && $notification->intendedRecipient() === $coordinator->email
                    && $notification->isTestMode()
                    && count($notification->lines()) === 2
                    && str_contains($renderedMail, route('hardware.show', $asset->id))
                    && str_contains($renderedMail, route('licenses.show', $license->id))
                    && ! str_contains($renderedMail, route('users.index'));
            }
        );
        $this->assertDatabaseHas('offboarding_report_deliveries', [
            'mode' => 'test',
            'intended_recipient' => $coordinator->email,
            'delivery_recipient' => 'reviewer@example.com',
            'status' => OffboardingReportDelivery::STATUS_SENT,
        ]);

        $this->artisan('snipeit:process-offboarding-report', $arguments)
            ->expectsOutput('Email delivery complete: 0 sent, 0 failed, 1 already delivered.')
            ->assertExitCode(0);

        $this->assertSame(1, OffboardingReportDelivery::query()->count());
        $this->assertSame(1, OffboardingReportRun::query()->count());

        $this->artisan('snipeit:process-offboarding-report', ['csv' => $csv])
            ->assertExitCode(0);

        $this->assertSame(
            OffboardingReportRun::STATUS_TEST_SENT,
            OffboardingReportRun::query()->firstOrFail()->status
        );
    }

    public function test_dry_run_includes_company_owned_license_seats_when_fmcs_is_enabled(): void
    {
        Notification::fake();
        $settings = Setting::getSettings();
        $settings->full_multiple_companies_support = 1;
        $settings->save();

        [$user] = $this->createRoutedAssignments();
        $csv = $this->makeCsv([$this->csvRow($user)]);

        $this->artisan('snipeit:process-offboarding-report', ['csv' => $csv])
            ->assertExitCode(0);

        $summary = OffboardingReportRun::query()->firstOrFail()->summary;
        $this->assertSame(2, $summary['obligations']);
        $this->assertSame(1, $summary['notification_recipients']);
    }

    public function test_conflicting_identifiers_are_not_notified(): void
    {
        Notification::fake();
        $first = User::factory()->create([
            'username' => 'first.user',
            'email' => 'first@example.com',
            'employee_num' => 'EMP-1',
        ]);
        $second = User::factory()->create([
            'username' => 'second.user',
            'email' => 'second@example.com',
            'employee_num' => 'EMP-2',
        ]);
        $csv = $this->makeCsv([
            $this->csvRow($first, [
                'SamAccountName' => $second->username,
                'Email' => $first->email,
                'EmployeeID' => 'UNRELATED',
            ]),
        ]);

        $this->artisan('snipeit:process-offboarding-report', ['csv' => $csv, '--send' => true])
            ->expectsOutput('Email delivery complete: 0 sent, 0 failed, 0 already delivered.')
            ->assertExitCode(0);

        Notification::assertNothingSent();
        $this->assertSame(1, OffboardingReportRun::query()->firstOrFail()->summary['unresolved_users']);
    }

    public function test_unrouted_assignments_use_existing_administrator_alert_recipients(): void
    {
        Notification::fake();
        $settings = Setting::getSettings();
        $settings->forceFill([
            'alerts_enabled' => 1,
            'alert_email' => 'asset-admin@example.com, invalid-address, ASSET-ADMIN@example.com',
        ])->save();

        $company = Company::factory()->create(['name' => 'Unrouted QA Plant']);
        $creator = User::factory()->superuser()->create();
        $discipline = Discipline::create([
            'name' => 'Unrouted QA Engineering',
            'created_by' => $creator->id,
        ]);
        $user = User::factory()->create([
            'username' => 'qa.offboarding.unrouted',
            'email' => 'qa.offboarding.unrouted@example.com',
            'employee_num' => 'QA-OFF-UNROUTED',
            'company_id' => $company->id,
        ]);
        Asset::factory()->create([
            'asset_tag' => 'QA-OFF-UNROUTED-001',
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'assigned_to' => $user->id,
            'assigned_type' => User::class,
        ]);
        $csv = $this->makeCsv([$this->csvRow($user)]);

        $this->artisan('snipeit:process-offboarding-report', [
            'csv' => $csv,
            '--send' => true,
            '--recipient-override' => 'reviewer@example.com',
        ])->assertExitCode(0);

        Notification::assertSentOnDemand(
            OffboardingAssignmentsNotification::class,
            function (OffboardingAssignmentsNotification $notification, array $channels, object $notifiable) {
                $renderedMail = $notification->toMail($notifiable)->render();

                return $notification->intendedRecipient() === 'asset-admin@example.com'
                    && count($notification->lines()) === 1
                    && str_contains($renderedMail, 'RAC routing warning')
                    && str_contains($renderedMail, 'no active RAC for Unrouted QA Plant / Unrouted QA Engineering');
            }
        );

        $summary = OffboardingReportRun::query()->firstOrFail()->summary;
        $this->assertSame(1, $summary['routing_warnings']);
        $this->assertSame(1, $summary['notification_recipients']);
    }

    private function createRoutedAssignments(): array
    {
        $company = Company::factory()->create(['name' => 'QA Plant']);
        $creator = User::factory()->superuser()->create();
        $discipline = Discipline::create([
            'name' => 'QA Engineering',
            'created_by' => $creator->id,
        ]);
        $coordinator = User::factory()->create([
            'email' => 'qa-rac@example.com',
            'company_id' => $company->id,
        ]);
        RegionalAssetCoordinatorAssignment::create([
            'user_id' => $coordinator->id,
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'created_by' => $creator->id,
        ]);
        $user = User::factory()->create([
            'first_name' => 'Asset',
            'last_name' => 'Leaver',
            'username' => 'qa.offboarding.asset',
            'email' => 'qa.offboarding.asset@example.com',
            'employee_num' => 'QA-OFF-1001',
            'company_id' => $company->id,
        ]);
        $asset = Asset::factory()->create([
            'name' => 'QA Assigned Laptop',
            'asset_tag' => 'QA-OFF-ASSET-001',
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'assigned_to' => $user->id,
            'assigned_type' => User::class,
        ]);
        $license = License::factory()->create([
            'name' => 'QA Engineering Suite',
            'company_id' => $company->id,
            'discipline_id' => $discipline->id,
            'seats' => 1,
        ]);
        LicenseSeat::factory()->create([
            'license_id' => $license->id,
            'assigned_to' => $user->id,
        ]);

        return [$user, $coordinator, $asset, $license];
    }

    private function makeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'offboarding-report-');
        $handle = fopen($path, 'wb');
        $headers = array_keys($this->csvRow());
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }
        fclose($handle);
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function csvRow(?User $user = null, array $overrides = []): array
    {
        return array_merge([
            'DateDisabled' => '9/8/2026 8:00:00 AM',
            'LastLogon' => '9/7/2026 5:00:00 PM',
            'Method' => 'Manual',
            'Type' => 'DIS',
            'SamAccountName' => $user?->username ?? 'example.user',
            'DisplayName' => $user ? $user->last_name.', '.$user->first_name : 'User, Example',
            'Email' => $user?->email ?? 'example.user@example.com',
            'Location' => 'QA Plant',
            'Department' => 'QA Engineering',
            'Gecos' => '',
            'Manager' => '',
            'EmployeeID' => $user?->employee_num ?? 'EXAMPLE',
            'Country' => 'MA',
            'Region' => 'EMEA',
            'Guid' => '00000000-0000-0000-0000-000000000001',
        ], $overrides);
    }
}
