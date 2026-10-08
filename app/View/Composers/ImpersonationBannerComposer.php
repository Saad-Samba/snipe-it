<?php

namespace App\View\Composers;

use App\Models\CompanyableScope;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class ImpersonationBannerComposer
{
    public function compose(View $view): void
    {
        $impersonatorId = Session::get('impersonator_id');
        $impersonator = null;

        if ($impersonatorId && Auth::check()) {
            $impersonator = User::withTrashed()
                ->withoutGlobalScope(CompanyableScope::class)
                ->find($impersonatorId);
        }

        $view->with('impersonator', $impersonator);
    }
}
