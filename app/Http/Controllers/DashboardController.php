<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\RedirectResponse;
use \Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Session;


/**
 * This controller handles all actions related to the Admin Dashboard
 * for the Snipe-IT Asset Management application.
 *
 * @author A. Gianotto <snipe@snipe.net>
 * @version v1.0
 */
class DashboardController extends Controller
{
    /**
     * Check authorization and display admin dashboard, otherwise display
     * the user's checked-out assets.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v1.0]
     */
    public function index(\Illuminate\Http\Request $request) : View | RedirectResponse
    {
        // Show the page
        if (auth()->user()->hasAccess('admin')) {
            $asset_stats = null;

            $disciplineField = \App\Models\CustomField::where('name', 'Discipline')->first();
            $disciplineColumn = $disciplineField->db_column ?? \App\Models\CustomField::name_to_db_name('Discipline');
            $hasDisciplineColumn = \Illuminate\Support\Facades\Schema::hasColumn('assets', $disciplineColumn);
            $selectedDiscipline = ($hasDisciplineColumn) ? $request->input('discipline') : null;
            $selectedCompany = $request->input('company_id');

            $assetQuery = \App\Models\Asset::query();

            if ($selectedCompany) {
                $assetQuery->where('company_id', $selectedCompany);
            }

            if ($hasDisciplineColumn && $selectedDiscipline) {
                $assetQuery->where($disciplineColumn, $selectedDiscipline);
            }

            $counts['asset'] = (clone $assetQuery)->count();
            $counts['accessory'] = \App\Models\Accessory::when($selectedCompany, function ($query) use ($selectedCompany) {
                return $query->where('company_id', $selectedCompany);
            })->count();
            $licenseSeatsQuery = \App\Models\LicenseSeat::query()->whereNull('deleted_at');

            if ($selectedCompany) {
                $licenseSeatsQuery->where(function ($query) use ($selectedCompany) {
                    $query->whereHas('license', function ($licenseQuery) use ($selectedCompany) {
                        $licenseQuery->where('company_id', $selectedCompany);
                    })->orWhereHas('asset', function ($assetQuery) use ($selectedCompany) {
                        $assetQuery->where('company_id', $selectedCompany);
                    });
                });
            }

            if ($hasDisciplineColumn && $selectedDiscipline) {
                $licenseSeatsQuery->where(function ($query) use ($disciplineColumn, $selectedDiscipline, $selectedCompany) {
                    $query->whereHas('asset', function ($assetQuery) use ($disciplineColumn, $selectedDiscipline, $selectedCompany) {
                        if ($selectedCompany) {
                            $assetQuery->where('company_id', $selectedCompany);
                        }

                        $assetQuery->where($disciplineColumn, $selectedDiscipline);
                    })
                    ->orWhereHas('license', function ($licenseQuery) use ($selectedDiscipline, $selectedCompany) {
                        if ($selectedCompany) {
                            $licenseQuery->where('company_id', $selectedCompany);
                        }
                        $licenseQuery->where('discipline', $selectedDiscipline);
                    });
                });
            }

            $counts['license'] = $licenseSeatsQuery->count();
            $counts['consumable'] = \App\Models\Consumable::when($selectedCompany, function ($query) use ($selectedCompany) {
                return $query->where('company_id', $selectedCompany);
            })->count();
            $counts['component'] = \App\Models\Component::when($selectedCompany, function ($query) use ($selectedCompany) {
                return $query->where('company_id', $selectedCompany);
            })->count();
            $counts['user'] = \App\Models\Company::scopeCompanyables(auth()->user())->count();
            $counts['grand_total'] = $counts['asset'] + $counts['accessory'] + $counts['license'] + $counts['consumable'];

            $disciplines = collect();

            if ($hasDisciplineColumn) {
                $assetDisciplines = \App\Models\Asset::query()
                    ->whereNotNull($disciplineColumn)
                    ->where($disciplineColumn, '!=', '')
                    ->select($disciplineColumn)
                    ->distinct()
                    ->pluck($disciplineColumn);

                $licenseDisciplines = \App\Models\License::query()
                    ->whereNotNull('discipline')
                    ->where('discipline', '!=', '')
                    ->select('discipline')
                    ->distinct()
                    ->pluck('discipline');

                $disciplines = $assetDisciplines->merge($licenseDisciplines)->unique()->sort()->values();
            }

            $companies = \App\Models\Company::orderBy('name')->get();

            if ((! file_exists(storage_path().'/oauth-private.key')) || (! file_exists(storage_path().'/oauth-public.key'))) {
                Artisan::call('migrate', ['--force' => true]);
                Artisan::call('passport:install', ['--no-interaction' => true]);
            }

            return view('dashboard')
                ->with('asset_stats', $asset_stats)
                ->with('counts', $counts)
                ->with('disciplines', $disciplines)
                ->with('companies', $companies)
                ->with('disciplineColumn', $hasDisciplineColumn ? $disciplineColumn : null)
                ->with('hasDisciplineColumn', $hasDisciplineColumn)
                ->with('selectedDiscipline', $selectedDiscipline)
                ->with('selectedCompany', $selectedCompany);
        } else {
            Session::reflash();

            // Redirect to the profile page
            return redirect()->intended('account/view-assets');
        }
    }
}
