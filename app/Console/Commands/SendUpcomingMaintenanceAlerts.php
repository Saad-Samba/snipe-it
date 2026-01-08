<?php

namespace App\Console\Commands;

use App\Helpers\Helper;
use App\Mail\SendUpcomingMaintenanceMail;
use App\Models\Maintenance;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendUpcomingMaintenanceAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'snipeit:upcoming-maintenances {--with-output : Display the results in a table in your console in addition to sending the email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send email notifications for upcoming asset maintenances.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $settings = Setting::getSettings();
        $threshold = $settings->alert_interval ?? 0;
        $today = Carbon::now();
        $intervalDate = $today->copy()->addDays($threshold);

        if (!$this->option('with-output')) {
            $this->info('Run this command with the --with-output option to see the full list in the console.');
        }

        $maintenancesQuery = Maintenance::with(['asset', 'supplier'])
            ->whereNull('deleted_at')
            ->whereDate('start_date', '>=', $today->toDateString())
            ->whereDate('start_date', '<=', $intervalDate->toDateString())
            ->orderBy('start_date', 'asc');

        $maintenanceCount = $maintenancesQuery->count();

        $this->info($maintenanceCount . ' maintenances start on or before ' . Helper::getFormattedDateObject($intervalDate, 'date', false));

        if ($maintenanceCount === 0) {
            $this->info('There are no maintenances due to start in the next ' . $threshold . ' days.');
            return;
        }

        $maintenancesForEmail = $maintenancesQuery->limit(30)->get();

        if ($settings->alert_email != '') {
            $recipients = collect(explode(',', $settings->alert_email))
                ->map(fn ($item) => trim($item))
                ->filter(fn ($item) => !empty($item))
                ->all();

            Mail::to($recipients)->send(new SendUpcomingMaintenanceMail($maintenancesForEmail, $threshold, $maintenanceCount));

            $this->info('Upcoming maintenance notification sent to: ' . $settings->alert_email);
        } else {
            $this->info('There is no admin alert email set so no email will be sent.');
        }

        if ($this->option('with-output')) {
            $maintenancesForOutput = $maintenancesQuery->get();

            $this->table(
                [
                    trans('general.id'),
                    trans('general.name'),
                    trans('admin/maintenances/form.start_date'),
                    trans('admin/hardware/form.tag'),
                    trans('admin/maintenances/table.asset_name'),
                    trans('general.supplier'),
                ],
                $maintenancesForOutput->map(fn ($maintenance) => [
                    trans('general.id') => $maintenance->id,
                    trans('general.name') => $maintenance->name,
                    trans('admin/maintenances/form.start_date') => Helper::getFormattedDateObject($maintenance->start_date, 'date', false),
                    trans('admin/hardware/form.tag') => $maintenance->asset?->asset_tag,
                    trans('admin/maintenances/table.asset_name') => $maintenance->asset?->name,
                    trans('general.supplier') => $maintenance->supplier?->name,
                ])
            );
        }
    }
}
