<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class RegionalAssetCoordinatorsController extends Controller
{
    public function index(): View
    {
        return view('account/regional-asset-coordinators');
    }
}
