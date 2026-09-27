<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\Official;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One dashboard route: office users (admin/staff) get the office overview,
 * residents are sent to their self-service portal.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        abort_unless($user !== null, 403);

        if ($user->isResident()) {
            return redirect()->route('resident.portal');
        }

        return view('dashboard.office', [
            'counts' => [
                'residents' => Resident::where('status', Resident::STATUS_ACTIVE)->count(),
                'households' => Household::count(),
                'puroks' => Purok::count(),
                'officials' => Official::where('status', Official::STATUS_ACTIVE)->count(),
                'pendingAccounts' => $user->isAdmin()
                    ? User::where('status', User::STATUS_PENDING)->count()
                    : null,
            ],
            'pendingUsers' => $user->isAdmin()
                ? User::pending()->latest()->get()
                : collect(),
        ]);
    }
}
