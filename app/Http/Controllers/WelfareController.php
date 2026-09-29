<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Resident;
use App\Models\Welfare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WelfareController extends Controller
{
    public function index(Request $request)
    {
        $query = Welfare::query()->latest('request_date')->latest('id');

        if ($request->filled('search')) {
            $search = mb_substr(strip_tags((string) $request->search), 0, 100);
            $query->where(function ($q) use ($search) {
                $q->where('beneficiary_name', 'like', '%'.$search.'%')
                    ->orWhere('program_name', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('status') && in_array($request->status, ['Requested', 'Under Review', 'Approved', 'Denied', 'Released'], true)) {
            $query->where('status', $request->status);
        }

        if ($request->filled('assistance_type') && in_array($request->assistance_type, ['Financial', 'Food', 'Medical', 'Educational', 'Housing', 'Other'], true)) {
            $query->where('assistance_type', $request->assistance_type);
        }

        $welfares = $query->paginate(20);
        $pendingCount = Welfare::requested()->count();
        $pendingAmount = Welfare::approved()->sum('approved_amount');

        return view('welfare.index', compact('welfares', 'pendingCount', 'pendingAmount'))
            ->with('i', ($request->input('page', 1) - 1) * $welfares->perPage());
    }

    public function create()
    {
        return view('welfare.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        // Staff may record intake only. Approval, release, denial, and
        // monetary decisions remain administrator-controlled.
        if (Auth::user()?->isStaff()) {
            $request->merge([
                'status' => 'Requested',
                'approved_amount' => 0,
                'approval_date' => null,
                'release_date' => null,
            ]);
        }

        $validated = $this->synchronizeLinkedResidentFields($this->validateWelfare($request));

        $welfare = DB::transaction(function () use ($request, $validated) {
            $welfare = Welfare::create($validated);

            AuditLog::record(
                'welfare.created',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['beneficiary' => $welfare->beneficiary_name, 'program' => $welfare->program_name],
            );

            return $welfare;
        });

        return redirect()->route('welfare.index')
            ->with('success', "Assistance request for {$welfare->beneficiary_name} recorded successfully.");
    }

    public function edit(Welfare $welfare)
    {
        abort_unless(Auth::user()?->hasPermission('welfare.approve'), 403);

        return view('welfare.edit', array_merge(
            ['welfare' => $welfare],
            $this->formOptions($welfare),
        ));
    }

    public function update(Request $request, Welfare $welfare)
    {
        abort_unless(Auth::user()?->hasPermission('welfare.approve'), 403);

        $validated = $this->synchronizeLinkedResidentFields($this->validateWelfare($request, $welfare));

        DB::transaction(function () use ($request, $welfare, $validated) {
            $welfare->update($validated);

            AuditLog::record(
                'welfare.updated',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['beneficiary' => $welfare->beneficiary_name, 'program' => $welfare->program_name],
            );
        });

        return redirect()->route('welfare.index')
            ->with('success', "Assistance request for {$welfare->beneficiary_name} updated successfully.");
    }

    public function destroy(Request $request, Welfare $welfare)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $beneficiary = $welfare->beneficiary_name;

        DB::transaction(function () use ($request, $welfare) {
            $welfare->delete();

            AuditLog::record(
                'welfare.deleted',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['beneficiary' => $welfare->beneficiary_name, 'program' => $welfare->program_name],
            );
        });

        return redirect()->route('welfare.index')
            ->with('success', "Assistance request for {$beneficiary} deleted successfully.");
    }

    private function synchronizeLinkedResidentFields(array $validated): array
    {
        if (blank($validated['beneficiary_id'] ?? null)) {
            return $validated;
        }

        $resident = Resident::withTrashed()->findOrFail($validated['beneficiary_id']);
        $validated['beneficiary_name'] = $resident->full_name;
        $validated['beneficiary_address'] = $resident->address;
        $validated['beneficiary_phone'] = $resident->phone_number;

        return $validated;
    }

    private function validateWelfare(Request $request, ?Welfare $welfare = null): array
    {
        $validated = $request->validate([
            'beneficiary_id' => ['nullable', 'integer', Rule::exists('residents', 'id')],
            'beneficiary_name' => 'required_without:beneficiary_id|nullable|string|max:255',
            'beneficiary_address' => 'nullable|string|max:255',
            'beneficiary_phone' => ['nullable', 'string', 'max:15', 'regex:/^(?=.*\d)\+?[0-9()\-\s]+$/'],
            'assistance_type' => 'required|in:Financial,Food,Medical,Educational,Housing,Other',
            'program_name' => 'required|string|max:255',
            'program_description' => 'nullable|string|max:2000',
            'requested_amount' => 'required|numeric|decimal:0,2|min:0|max:99999999.99',
            'approved_amount' => 'nullable|numeric|decimal:0,2|min:0|max:99999999.99',
            'status' => 'required|in:Requested,Under Review,Approved,Denied,Released',
            'request_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'approval_date' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'release_date' => 'nullable|date_format:Y-m-d|before_or_equal:today|after_or_equal:approval_date',
            'remarks' => 'nullable|string|max:2000',
        ], [
            'beneficiary_phone.regex' => 'The beneficiary phone number must contain at least one digit. Spaces, +, -, and parentheses are allowed.',
            'beneficiary_id.integer' => 'Please select a registered resident from the list, or leave it blank for a walk-in.',
            'beneficiary_id.exists' => 'The selected resident is no longer available. Please choose another.',
        ]);

        $residentId = $validated['beneficiary_id'] ?? null;
        if (filled($residentId)) {
            $resident = Resident::withTrashed()->find($residentId);
            $isCurrent = $welfare && (int) $welfare->beneficiary_id === (int) $residentId;
            if (! $resident || ($resident->status !== 'Active' && ! $isCurrent)) {
                throw ValidationException::withMessages([
                    'beneficiary_id' => 'The selected resident is archived or inactive. Please choose an active resident.',
                ]);
            }
        }

        // The column is NOT NULL DEFAULT 0: an unapproved request stores 0,
        // never NULL (browsers submit the empty field as an empty string).
        $validated['approved_amount'] = $validated['approved_amount'] ?? 0;

        // Workflow integrity: money needs an approval date, release needs a date.
        if ($validated['status'] === 'Approved') {
            $request->validate([
                'approval_date' => 'required|date_format:Y-m-d|before_or_equal:today',
                'approved_amount' => 'required|numeric|decimal:0,2|gt:0',
            ]);
        }

        if ($validated['status'] === 'Released') {
            $request->validate([
                'approval_date' => 'required|date_format:Y-m-d|before_or_equal:today',
                'approved_amount' => 'required|numeric|decimal:0,2|gt:0',
                'release_date' => 'required|date_format:Y-m-d|before_or_equal:today|after_or_equal:approval_date',
            ]);
        }

        // An approval can never exceed the request. Without this the office
        // could approve a peso amount against a zero request, and the printed
        // welfare report totals it as disbursed.
        if ((float) $validated['approved_amount'] > (float) $validated['requested_amount']) {
            throw ValidationException::withMessages([
                'approved_amount' => 'The approved amount cannot be greater than the requested amount.',
            ]);
        }

        // A denied request must record why, and must not carry money or dates
        // that imply funds moved.
        if ($validated['status'] === 'Denied') {
            if (blank($validated['remarks'] ?? null)) {
                throw ValidationException::withMessages([
                    'remarks' => 'Record a reason when denying an assistance request.',
                ]);
            }

            if ((float) $validated['approved_amount'] > 0) {
                throw ValidationException::withMessages([
                    'approved_amount' => 'A denied request cannot carry an approved amount.',
                ]);
            }
        }

        return $validated;
    }

    private function formOptions(?Welfare $welfare = null): array
    {
        $currentResidentIds = $welfare && $welfare->beneficiary_id ? [(int) $welfare->beneficiary_id] : [];

        return [
            'residents' => Resident::withTrashed()
                ->where(function ($query) use ($currentResidentIds) {
                    $query->where('status', 'Active')->whereNull('deleted_at');
                    if ($currentResidentIds !== []) {
                        $query->orWhereIn('id', $currentResidentIds);
                    }
                })
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'address', 'phone_number', 'status', 'deleted_at']),
        ];
    }
}
