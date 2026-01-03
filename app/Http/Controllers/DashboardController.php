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

            $disciplines = \App\Models\Discipline::orderBy('name')->get();
            $selectedDisciplineId = $request->input('discipline_id');
            $selectedCompany = $request->input('company_id');

            $assetQuery = \App\Models\Asset::query();

            if ($selectedCompany) {
                $assetQuery->where('company_id', $selectedCompany);
            }

            if ($selectedDisciplineId) {
                $assetQuery->where('discipline_id', $selectedDisciplineId);
            }

            $counts['asset'] = (clone $assetQuery)->count();
            // Accessories are not discipline-aware; return 0 when a discipline filter is applied.
            $counts['accessory'] = $selectedDisciplineId
                ? 0
                : \App\Models\Accessory::when($selectedCompany, function ($query) use ($selectedCompany) {
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

            if ($selectedDisciplineId) {
                $licenseSeatsQuery->where(function ($query) use ($selectedDisciplineId, $selectedCompany) {
                    $query->whereHas('asset', function ($assetQuery) use ($selectedDisciplineId, $selectedCompany) {
                        if ($selectedCompany) {
                            $assetQuery->where('company_id', $selectedCompany);
                        }

                        $assetQuery->where('discipline_id', $selectedDisciplineId);
                    })
                    ->orWhereHas('license', function ($licenseQuery) use ($selectedDisciplineId, $selectedCompany) {
                        if ($selectedCompany) {
                            $licenseQuery->where('company_id', $selectedCompany);
                        }
                        $licenseQuery->where('discipline_id', $selectedDisciplineId);
                    });
                });
            }

            $counts['license'] = $licenseSeatsQuery->count();
            $counts['consumable'] = $selectedDisciplineId
                ? 0
                : \App\Models\Consumable::when($selectedCompany, function ($query) use ($selectedCompany) {
                    return $query->where('company_id', $selectedCompany);
                })->count();
            $counts['component'] = $selectedDisciplineId
                ? 0
                : \App\Models\Component::when($selectedCompany, function ($query) use ($selectedCompany) {
                    return $query->where('company_id', $selectedCompany);
                })->count();
            $counts['user'] = \App\Models\Company::scopeCompanyables(auth()->user())->count();
            $counts['grand_total'] = $counts['asset'] + $counts['accessory'] + $counts['license'] + $counts['consumable'];

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
                ->with('disciplineColumn', null)
                ->with('hasDisciplineColumn', $disciplines->isNotEmpty())
                ->with('selectedDiscipline', $selectedDisciplineId ? optional($disciplines->firstWhere('id', $selectedDisciplineId))->name : null)
                ->with('selectedDisciplineId', $selectedDisciplineId)
                ->with('selectedCompany', $selectedCompany);
        } else {
            Session::reflash();

            // Redirect to the profile page
            return redirect()->intended('account/view-assets');
        }
    }
}
