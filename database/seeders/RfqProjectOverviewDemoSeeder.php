<?php

namespace Database\Seeders;

use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RfqProjectOverviewDemoSeeder extends Seeder
{
    public const PASSWORD = 'Demo-RFQ-2026!';

    private const PROJECT_NAME = 'DEMO - RFQ Project Overview';

    private const ELECTRICAL_BATCH = '00000000-0000-4000-8000-000000000201';

    private const AUTOMATION_BATCH = '00000000-0000-4000-8000-000000000202';

    private const VALIDATION_BATCH = '00000000-0000-4000-8000-000000000203';

    private const POWER_BATCH = '00000000-0000-4000-8000-000000000204';

    private const ELECTRICAL_SUPPORT_BATCH = '00000000-0000-4000-8000-000000000205';

    public function run(): void
    {
        $admin = User::withoutGlobalScopes()->where('username', 'demo-GSA')->firstOrFail();
        $company = Company::withoutGlobalScopes()->where('name', 'Rabat')->firstOrFail();
        $project = Project::withoutGlobalScopes()->updateOrCreate(
            ['name' => self::PROJECT_NAME],
            [
                'notes' => 'Manual QA scenario for the RFQ Project Request Overview.',
                'created_by' => $admin->id,
            ]
        );

        $lead = $this->upsertUser(
            'demo-RFQ-LEAD',
            'QA RFQ',
            'Lead',
            'demo-rfq-lead@example.com',
            ['projects.view' => '1'],
            $admin->id,
            $company->id
        );
        $electricalRequester = $this->upsertUser(
            'demo-RFQ-ELECTRICAL',
            'QA RFQ',
            'Electrical',
            'demo-rfq-electrical@example.com',
            ['models.request' => '1', 'models.request.all_companies' => '1'],
            $admin->id,
            $company->id
        );
        $automationRequester = $this->upsertUser(
            'demo-RFQ-AUTOMATION',
            'QA RFQ',
            'Automation',
            'demo-rfq-automation@example.com',
            ['models.request' => '1', 'models.request.all_companies' => '1'],
            $admin->id,
            $company->id
        );
        $validationRequester = $this->upsertUser(
            'demo-RFQ-VALIDATION',
            'QA RFQ',
            'Validation',
            'demo-rfq-validation@example.com',
            ['models.request' => '1', 'models.request.all_companies' => '1'],
            $admin->id,
            $company->id
        );
        $powerRequester = $this->upsertUser(
            'demo-RFQ-POWER',
            'QA RFQ',
            'Power',
            'demo-rfq-power@example.com',
            ['models.request' => '1', 'models.request.all_companies' => '1'],
            $admin->id,
            $company->id
        );
        $electricalSupportRequester = $this->upsertUser(
            'demo-RFQ-ELECTRICAL-SUPPORT',
            'QA RFQ',
            'Electrical Support',
            'demo-rfq-electrical-support@example.com',
            ['models.request' => '1', 'models.request.all_companies' => '1'],
            $admin->id,
            $company->id
        );

        $electrical = Discipline::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'QA RFQ Electrical'],
            ['notes' => 'QA discipline for Project Request Overview.', 'created_by' => $admin->id]
        );
        $automation = Discipline::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'QA RFQ Automation'],
            ['notes' => 'QA discipline for Project Request Overview.', 'created_by' => $admin->id]
        );
        $validation = Discipline::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'QA RFQ Validation'],
            ['notes' => 'QA discipline for Project Request Overview.', 'created_by' => $admin->id]
        );
        $power = Discipline::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'QA RFQ Power Electronics'],
            ['notes' => 'QA discipline for Project Request Overview.', 'created_by' => $admin->id]
        );

        $network = AssetModel::withoutGlobalScopes()->where('name', 'Vector VN1630A CAN/LIN Interface')->firstOrFail();
        $pcan = AssetModel::withoutGlobalScopes()->where('name', 'PEAK PCAN-USB FD Interface')->firstOrFail();
        $oscilloscope = AssetModel::withoutGlobalScopes()->where('name', 'Tektronix MDO3024 Oscilloscope')->firstOrFail();
        $keysightScope = AssetModel::withoutGlobalScopes()->where('name', 'Keysight DSOX1204G Oscilloscope')->firstOrFail();
        $debugProbe = AssetModel::withoutGlobalScopes()->where('name', 'SEGGER J-Link PRO Debug Probe')->firstOrFail();
        $trace32Probe = AssetModel::withoutGlobalScopes()->where('name', 'Lauterbach TRACE32 Debug Probe')->firstOrFail();
        $powerSupply = AssetModel::withoutGlobalScopes()->where('name', 'EA-PS 9080-60 DC Power Supply')->firstOrFail();

        CheckoutRequest::withoutGlobalScopes()
            ->whereIn('submission_batch_id', [
                self::ELECTRICAL_BATCH,
                self::AUTOMATION_BATCH,
                self::VALIDATION_BATCH,
                self::POWER_BATCH,
                self::ELECTRICAL_SUPPORT_BATCH,
            ])
            ->forceDelete();

        $this->createRequest($electricalRequester, $electrical, $company, $project, $network, 2, '2026-11-30', self::ELECTRICAL_BATCH);
        $this->createRequest($electricalRequester, $electrical, $company, $project, $oscilloscope, 1, '2026-11-30', self::ELECTRICAL_BATCH);
        $this->createRequest($electricalRequester, $electrical, $company, $project, $pcan, 4, '2026-12-06', self::ELECTRICAL_BATCH);
        $this->createRequest($electricalRequester, $electrical, $company, $project, $network, 1, '2026-12-12', self::ELECTRICAL_BATCH);
        $this->createRequest($electricalSupportRequester, $electrical, $company, $project, $pcan, 1, '2026-12-03', self::ELECTRICAL_SUPPORT_BATCH);
        $this->createRequest($electricalSupportRequester, $electrical, $company, $project, $network, 2, '2026-12-14', self::ELECTRICAL_SUPPORT_BATCH);
        $this->createRequest($automationRequester, $automation, $company, $project, $debugProbe, 3, '2026-12-02', self::AUTOMATION_BATCH);
        $this->createRequest($automationRequester, $automation, $company, $project, $trace32Probe, 1, '2026-12-04', self::AUTOMATION_BATCH);
        $this->createRequest($automationRequester, $automation, $company, $project, $pcan, 2, '2026-12-09', self::AUTOMATION_BATCH);
        $this->createRequest($validationRequester, $validation, $company, $project, $keysightScope, 2, '2026-11-27', self::VALIDATION_BATCH);
        $this->createRequest($validationRequester, $validation, $company, $project, $oscilloscope, 1, '2026-12-01', self::VALIDATION_BATCH);
        $this->createRequest($validationRequester, $validation, $company, $project, $debugProbe, 2, '2026-12-08', self::VALIDATION_BATCH);
        $this->createRequest($powerRequester, $power, $company, $project, $powerSupply, 2, '2026-11-29', self::POWER_BATCH);
        $this->createRequest($powerRequester, $power, $company, $project, $powerSupply, 1, '2026-12-05', self::POWER_BATCH);
        $this->createRequest($powerRequester, $power, $company, $project, $keysightScope, 1, '2026-12-10', self::POWER_BATCH);

        $this->command?->info('Seeded RFQ Project Overview QA scenario.');
        $this->command?->line('Lead: demo-RFQ-LEAD / '.self::PASSWORD);
        $this->command?->line('Project: '.self::PROJECT_NAME);
        $this->command?->line('Seeded 15 request lines across Electrical, Automation, Validation, and Power Electronics.');
    }

    private function upsertUser(
        string $username,
        string $firstName,
        string $lastName,
        string $email,
        array $permissions,
        int $createdBy,
        int $companyId
    ): User {
        $user = User::withoutGlobalScopes()->firstOrNew(['username' => $username]);
        $user->forceFill([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $firstName.' '.$lastName,
            'email' => $email,
            'activated' => 1,
            'company_id' => $companyId,
            'locale' => 'en-US',
            'permissions' => json_encode($permissions),
            'password' => Hash::make(self::PASSWORD),
            'notes' => 'Manual QA account created by RfqProjectOverviewDemoSeeder.',
            'created_by' => $createdBy,
        ]);
        $user->saveOrFail();

        return $user;
    }

    private function createRequest(
        User $requester,
        Discipline $discipline,
        Company $company,
        Project $project,
        AssetModel $model,
        int $quantity,
        string $neededByDate,
        string $submissionBatchId
    ): void {
        CheckoutRequest::withoutGlobalScopes()->create([
            'user_id' => $requester->id,
            'requested_discipline_id' => $discipline->id,
            'company_id' => $company->id,
            'project_id' => $project->id,
            'requestable_type' => AssetModel::class,
            'requestable_id' => $model->id,
            'quantity' => $quantity,
            'needed_by_date' => $neededByDate,
            'status' => CheckoutRequest::STATUS_PENDING,
            'rac_routing_status' => CheckoutRequest::RAC_ROUTING_NOT_REQUIRED,
            'submission_batch_id' => $submissionBatchId,
            'reference_price_snapshot' => $model->reference_price,
            'note' => 'Seeded RFQ Project Overview QA request.',
        ]);
    }
}
