<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Blotter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ResidentBlotterController extends Controller
{
    /**
     * The incidents this resident has filed from the portal.
     */
    public function index(Request $request): View
    {
        $resident = $this->resident($request);

        return view('resident.blotter', [
            'resident' => $resident,
            'cases' => Blotter::where('complainant_id', $resident->id)
                ->where('reported_by_resident', true)
                ->latest('complaint_date')
                ->latest('id')
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    /**
     * File a new incident report.
     *
     * The report becomes a real Open case in the office's own blotter queue
     * straight away — one queue, not a second one — with the signed-in
     * resident recorded as the complainant.
     */
    public function store(Request $request): RedirectResponse
    {
        $resident = $this->resident($request);

        $validated = $request->validate([
            'complaint_type' => 'required|string|max:100',
            'complaint_date' => 'required|date_format:Y-m-d|before_or_equal:today|after_or_equal:1900-01-01',
            'accused_name' => 'required|string|max:255',
            'alleged_offense' => 'required|string|max:2000',
        ], [
            'complaint_type.required' => 'Tell us what kind of complaint this is.',
            'complaint_date.required' => 'Give the date the incident happened.',
            'complaint_date.before_or_equal' => 'The incident date cannot be in the future.',
            'complaint_date.after_or_equal' => 'The incident date must be on or after 1900-01-01.',
            'accused_name.required' => 'Name the person or party involved in the incident.',
            'alleged_offense.required' => 'Describe what happened.',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($resident, $user, $request, $validated) {
            // The case number is server-generated: assign it explicitly, never
            // through mass assignment.
            $case = new Blotter([
                'complainant_id' => $resident->id,
                'complainant_name' => $resident->full_name,
                'complainant_address' => $resident->address,
                'complainant_phone' => $resident->phone_number,
                'accused_name' => $validated['accused_name'],
                'complaint_type' => $validated['complaint_type'],
                'complaint_date' => $validated['complaint_date'],
                'alleged_offense' => $validated['alleged_offense'],
                'status' => 'Open',
                'arrest_made' => 'No',
                'created_by' => $user->id,
                'reported_by_resident' => true,
            ]);
            $case->case_number = BlotterController::getNextCaseNumber();
            $case->save();

            AuditLog::record(
                'resident.blotter_report_filed',
                $user->id,
                $user->email,
                $request->ip(),
                $request->userAgent(),
                ['blotter_id' => $case->id, 'case_number' => $case->case_number],
            );
        });

        return back()->with('success', 'Your report has been filed. The barangay office will review it and may contact you.');
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
