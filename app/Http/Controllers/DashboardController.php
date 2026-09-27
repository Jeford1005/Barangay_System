<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One dashboard route, three presentations: admin, staff and resident each
 * get their own view built from the data they are allowed to see.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        abort_unless($user !== null, 403);

        if ($user->isAdmin()) {
            return view('dashboard.admin', [
                'counts' => [
                    'total' => User::count(),
                    'admin' => User::where('role', User::ROLE_ADMIN)->count(),
                    'staff' => User::where('role', User::ROLE_STAFF)->count(),
                    'resident' => User::where('role', User::ROLE_RESIDENT)->count(),
                    'pending' => User::where('status', User::STATUS_PENDING)->count(),
                ],
                'pendingUsers' => User::pending()->latest()->get(),
            ]);
        }

        if ($user->isStaff()) {
            return view('dashboard.staff');
        }

        return view('dashboard.resident', [
            'resident' => $user,
        ]);
    }
}
