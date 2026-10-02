<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Welfare;
use App\Services\QueueTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResidentWelfareController extends Controller
{
    /**
     * The assistance requests this resident has submitted.
     */
    public function index(Request $request): View
    {
        $resident = $this->resident($request);

        $requests = Welfare::where('beneficiary_id', $resident->id)
            ->latest('request_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        // Queue position + honest ETA per waiting card, counted in SQL
        // aggregates (never hydrated): same-type Requested/Under Review
        // rows for the position, decided rows from the last 30 days for
        // the pace.
        $queue = QueueTracker::lines(
            $requests->getCollection(),
            fn (Welfare $row) => (string) $row->assistance_type,
            fn (Welfare $row) => in_array($row->status, ['Requested', 'Under Review'], true),
            fn ($assistanceType) => Welfare::query()
                ->where('assistance_type', $assistanceType)
                ->whereIn('status', ['Requested', 'Under Review']),
            fn (array $assistanceTypes) => Welfare::query()
                ->whereIn('assistance_type', $assistanceTypes)
                ->whereIn('status', ['Approved', 'Denied', 'Released'])
                ->where('updated_at', '>=', now()->subDays(QueueTracker::WINDOW_DAYS))
                ->selectRaw('assistance_type as k, COUNT(*) as c')
                ->groupBy('assistance_type')
                ->pluck('c', 'k'),
        );

        return view('resident.welfare', [
            'resident' => $resident,
            'requests' => $requests,
            'queue' => $queue,
        ]);
    }

    /**
     * Submit a new assistance request.
     *
     * It enters the office's welfare queue as "Requested" with the resident
     * already linked as the beneficiary, so staff review it the same way as
     * anything taken at the desk.
     */
    public function store(Request $request): RedirectResponse
    {
        $resident = $this->resident($request);

        $validated = $request->validate([
            'assistance_type' => 'required|in:Financial,Food,Medical,Educational,Housing,Other',
            'program_name' => 'required|string|max:255',
            'requested_amount' => 'required|numeric|decimal:0,2|gt:0|max:99999999.99',
            'remarks' => 'nullable|string|max:1000',
        ], [
            'assistance_type.required' => 'Choose the kind of assistance you need.',
            'program_name.required' => 'Tell us which program or assistance you are asking for.',
            'requested_amount.required' => 'Enter the amount you are requesting.',
            'requested_amount.decimal' => 'The amount may only contain numbers, with up to two decimal places.',
        ]);

        $user = $request->user();

        $welfare = Welfare::create([
            'beneficiary_id' => $resident->id,
            'beneficiary_name' => $resident->full_name,
            'beneficiary_address' => $resident->address,
            'beneficiary_phone' => $resident->phone_number,
            'assistance_type' => $validated['assistance_type'],
            'program_name' => $validated['program_name'],
            'requested_amount' => $validated['requested_amount'],
            'remarks' => $validated['remarks'] ?? null,
            'status' => 'Requested',
            'request_date' => now()->toDateString(),
        ]);

        AuditLog::record(
            'resident.welfare_requested',
            $user->id,
            $user->email,
            $request->ip(),
            $request->userAgent(),
            ['welfare_id' => $welfare->id, 'program' => $welfare->program_name],
        );

        return back()->with('success', 'Your assistance request has been submitted for review.');
    }

    /**
     * @return \App\Models\Resident
     */
    private function resident(Request $request)
    {
        $resident = $request->user()?->residentProfile;

        abort_if(! $resident, 404, 'No resident profile is linked to this account. Please contact the barangay office.');
        abort_if($resident->status !== 'Active', 403, 'This resident record is archived. Please contact the barangay office.');

        return $resident;
    }
}
