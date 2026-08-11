<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CheckoutRequest;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\License;
use App\Models\Project;
use App\Models\RegionalAssetCoordinatorAssignment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ManualLicenseReuseQaSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        if (! Setting::query()->exists()) {
            $this->call(SettingsSeeder::class);
        }

        DB::transaction(function () {
            $admin = $this->upsertUser(
                'qa-license-admin',
                'QA',
                'License Admin',
                null,
                ['superuser' => '1']
            );
            $admin->forceFill(['created_by' => $admin->id])->save();

            $sourceCompany = Company::withoutGlobalScopes()->updateOrCreate(
                ['name' => 'QA License Source Company'],
                ['created_by' => $admin->id]
            );
            $destinationCompany = Company::withoutGlobalScopes()->updateOrCreate(
                ['name' => 'QA License Destination Company'],
                ['created_by' => $admin->id]
            );
            $discipline = Discipline::withoutGlobalScopes()->updateOrCreate(
                ['name' => 'QA Software Discipline'],
                [
                    'notes' => 'Deterministic source and destination discipline for license reuse QA.',
                    'created_by' => $admin->id,
                ]
            );

            $requester = $this->upsertUser(
                'qa-license-requester',
                'QA',
                'License Requester',
                $destinationCompany->id,
                [
                    'models.request' => '1',
                    'models.view' => '1',
                    'licenses.request' => '1',
                    'licenses.view' => '1',
                    'projects.view' => '1',
                ],
                $admin->id
            );
            $coordinator = $this->upsertUser(
                'qa-license-rac',
                'QA',
                'License RAC',
                $sourceCompany->id,
                [
                    'licenses.view' => '1',
                    'licenses.checkout' => '1',
                    'licenses.checkin' => '1',
                ],
                $admin->id
            );
            $target = $this->upsertUser(
                'qa-license-target',
                'QA',
                'License Target',
                $destinationCompany->id,
                [],
                $admin->id
            );
            $holder = $this->upsertUser(
                'qa-license-holder',
                'QA',
                'Current License Holder',
                $sourceCompany->id,
                [],
                $admin->id
            );

            $project = Project::withoutGlobalScopes()->updateOrCreate(
                ['name' => 'QA License Reuse Project'],
                [
                    'notes' => 'Deterministic project for the license reuse walkthrough.',
                    'created_by' => $requester->id,
                ]
            );
            $category = Category::withoutGlobalScopes()->updateOrCreate(
                [
                    'name' => 'QA Reusable Software',
                    'category_type' => 'license',
                ],
                [
                    'checkin_email' => 0,
                    'require_acceptance' => 0,
                    'use_default_eula' => 0,
                    'created_by' => $admin->id,
                    'notes' => 'Deterministic category for license reuse QA.',
                ]
            );

            $license = License::withoutGlobalScopes()->firstOrNew([
                'name' => 'QA Microsoft 365 Reuse Pool',
            ]);
            $license->fill([
                'category_id' => $category->id,
                'company_id' => $sourceCompany->id,
                'discipline_id' => $discipline->id,
                'seats' => 3,
                'purchase_cost' => 1500,
                'software_version' => 'QA',
                'reassignable' => true,
                'perpetual' => true,
                'expiration_date' => null,
                'termination_date' => null,
                'created_by' => $admin->id,
                'notes' => 'Two seats are reusable now; one is expected to be released in 14 days.',
            ]);
            $license->save();

            $license->requests()->get()->each(function (CheckoutRequest $checkoutRequest) {
                $checkoutRequest->allocatedLicenseSeats()->detach();
                $checkoutRequest->coordinatorTargets()->delete();
                $checkoutRequest->delete();
            });

            $seats = $license->licenseSeats()->orderBy('id')->get();
            foreach ($seats as $index => $seat) {
                $seat->forceFill([
                    'assigned_to' => $index === 2 ? $holder->id : null,
                    'asset_id' => null,
                    'expected_release_date' => $index === 2 ? now()->addDays(14)->toDateString() : null,
                    'unreassignable_seat' => false,
                    'notes' => $index === 2
                        ? 'QA control seat expected to return in 14 days.'
                        : 'QA reusable seat available now.',
                    'created_by' => $admin->id,
                ])->save();
            }

            RegionalAssetCoordinatorAssignment::query()->updateOrCreate(
                [
                    'user_id' => $coordinator->id,
                    'company_id' => $sourceCompany->id,
                    'discipline_id' => $discipline->id,
                ],
                ['created_by' => $admin->id]
            );

            $this->printSummary($license, $project, $target);
        });
    }

    private function upsertUser(
        string $username,
        string $firstName,
        string $lastName,
        ?int $companyId,
        array $permissions,
        ?int $createdBy = null
    ): User {
        $user = User::withoutGlobalScopes()->firstOrNew(['username' => $username]);
        $user->fill([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $firstName . ' ' . $lastName,
            'email' => $username . '@example.com',
            'activated' => 1,
            'company_id' => $companyId,
            'locale' => 'en-US',
            'notes' => 'Deterministic user for license reuse QA.',
        ]);
        $user->forceFill([
            'permissions' => json_encode($permissions),
            'password' => Hash::make(self::PASSWORD),
            'created_by' => $createdBy,
        ]);
        $user->save();

        return $user;
    }

    private function printSummary(License $license, Project $project, User $target): void
    {
        $this->command?->info('License reuse QA dataset is ready. Existing requests for the QA license were reset.');
        $this->command?->line('Password for every QA user: ' . self::PASSWORD);
        $this->command?->line('Admin: qa-license-admin');
        $this->command?->line('Requester: qa-license-requester');
        $this->command?->line('RAC: qa-license-rac');
        $this->command?->line('Target: qa-license-target (' . $target->display_name . ')');
        $this->command?->line('License: ' . $license->name . ' (2 available now, 1 expected in 14 days)');
        $this->command?->line('Project: ' . $project->name);
        $this->command?->line('Suggested needed-by date: ' . now()->addDays(30)->toDateString());
    }
}
