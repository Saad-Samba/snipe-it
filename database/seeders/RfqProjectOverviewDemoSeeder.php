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

        $electrical = Discipline::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'QA RFQ Electrical'],
            ['notes' => 'QA discipline for Project Request Overview.', 'created_by' => $admin->id]
        );
        $automation = Discipline::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'QA RFQ Automation'],
            ['notes' => 'QA discipline for Project Request Overview.', 'created_by' => $admin->id]
        );

        $network = AssetModel::withoutGlobalScopes()->where('name', 'Vector VN1630A CAN/LIN Interface')->firstOrFail();
        $oscilloscope = AssetModel::withoutGlobalScopes()->where('name', 'Tektronix MDO3024 Oscilloscope')->firstOrFail();
        $debugProbe = AssetModel::withoutGlobalScopes()->where('name', 'SEGGER J-Link PRO Debug Probe')->firstOrFail();

        CheckoutRequest::withoutGlobalScopes()
            ->whereIn('submission_batch_id', [self::ELECTRICAL_BATCH, self::AUTOMATION_BATCH])
            ->forceDelete();

        $this->createRequest($electricalRequester, $electrical, $company, $project, $network, 2, '2026-11-30', self::ELECTRICAL_BATCH);
        $this->createRequest($electricalRequester, $electrical, $company, $project, $oscilloscope, 1, '2026-11-30', self::ELECTRICAL_BATCH);
        $this->createRequest($automationRequester, $automation, $company, $project, $debugProbe, 3, '2026-12-02', self::AUTOMATION_BATCH);

        $this->command?->info('Seeded RFQ Project Overview QA scenario.');
        $this->command?->line('Lead: demo-RFQ-LEAD / '.self::PASSWORD);
        $this->command?->line('Project: '.self::PROJECT_NAME);
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
