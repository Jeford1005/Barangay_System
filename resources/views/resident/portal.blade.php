<x-app-layout>
@section('page_header')
    <x-page-header title="Profile" subtitle="Your resident record on file with the barangay office." />
@endsection

@section('content')

<div class="max-w-5xl mx-auto space-y-6">

    {{-- What is on file for this resident. Identity and record details only —
         the standing of their requests lives on the Request page, not here. --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-slate-900">{{ $resident->full_name }}</h2>
                <p class="text-sm text-slate-500">Resident record</p>
            </div>
            @unless ($editing)
                {{-- Record edits are requested, never applied directly, so this
                     opens the correction form below rather than editing in place. --}}
                <a href="{{ route('resident.portal', ['edit' => 1]) }}"
                    class="btn btn-neutral">Edit</a>
            @endunless
        </div>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 p-6 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-slate-500">Birth date</dt>
                <dd class="text-slate-800">{{ optional($resident->birth_date)->format('M j, Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Sex</dt>
                <dd class="text-slate-800">{{ $resident->sex ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Civil status</dt>
                <dd class="text-slate-800">{{ $resident->civil_status ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Nationality</dt>
                <dd class="text-slate-800">{{ $resident->nationality ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Occupation</dt>
                <dd class="text-slate-800">{{ $resident->occupation ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Purok</dt>
                <dd class="text-slate-800">{{ $resident->purok?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Household</dt>
                <dd class="text-slate-800">{{ $resident->household?->household_code ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Residency status</dt>
                <dd class="text-slate-800">{{ $resident->residency_status ?? '—' }}</dd>
            </div>
        </dl>
        <p class="border-t border-slate-200 bg-slate-50 px-6 py-3 text-xs text-slate-500">
            These details are maintained by the barangay office. Press Edit to request a change — the record updates once staff approve it.
        </p>
    </div>

    {{-- The correction form used to be its own module; it is now revealed by
         Edit above and still submits to the same office queue as before. --}}
    @if ($editing)
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Request a profile correction</h2>
                    <p class="mt-1 max-w-2xl text-sm text-slate-600">Changes to your official record require barangay review. Your current record stays unchanged until staff approve the request.</p>
                </div>
                <a href="{{ route('resident.portal') }}" class="btn btn-neutral">Cancel</a>
            </div>

            {{-- Field errors render inside x-form.field, so only the
                 form-level message needs a block of its own. --}}
            @error('changes')
                <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</p>
            @enderror

            <form method="POST" action="{{ route('resident.changes.store') }}" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                @csrf
                <x-form.field name="occupation" label="Occupation" :value="old('occupation', $resident->occupation)" maxlength="100" />
                <x-form.field name="religion" label="Religion" :value="old('religion', $resident->religion)" maxlength="100" />
                <x-form.field name="residency_status" label="Residency status" :value="old('residency_status', $resident->residency_status)" maxlength="50" />
                <x-form.field name="purok_id" label="Purok" type="select" :options="$puroks" :value="old('purok_id', $resident->purok_id)" optional-hint />
                <x-form.field name="household_id" label="Household code" type="select" :options="$households" :value="old('household_id', $resident->household_id)" optional-hint />
                <div class="sm:col-span-2">
                    <x-form.field name="notes" label="Notes for staff (optional)" type="textarea" :rows="3" maxlength="1000" :value="old('notes')" />
                </div>
                <div class="sm:col-span-2 flex justify-end">
                    <button type="submit" class="btn btn-primary">Submit correction request</button>
                </div>
            </form>
        </section>
    @endif

    {{-- Listed only when there is something to follow: Profile stays a record
         page until the resident has actually sent a request. --}}
    @if ($changes->isNotEmpty())
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-semibold text-slate-900">Correction requests</h2></div>
            <ul class="divide-y divide-slate-100">
                @foreach ($changes as $change)
                    <li class="p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-slate-900">{{ implode(', ', array_map(fn ($field) => str_replace('_', ' ', $field), array_keys($change->changes))) }}</p>
                                <p class="mt-1 text-xs text-slate-500">Submitted {{ $change->created_at->format('M j, Y g:i A') }}</p>
                                @if ($change->review_note)<p class="mt-2 text-sm text-slate-700">Staff note: {{ $change->review_note }}</p>@endif
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ ['Pending' => 'bg-amber-100 text-amber-800', 'Approved' => 'bg-emerald-100 text-emerald-800', 'Rejected' => 'bg-red-100 text-red-800', 'Cancelled' => 'bg-slate-100 text-slate-700'][$change->status] }}">{{ $change->status }}</span>
                                @if ($change->status === 'Pending')
                                    <form method="POST" action="{{ route('resident.changes.cancel', $change) }}" data-confirm="Cancel this request?" data-confirm-title="Cancel request" data-confirm-accept="Cancel" data-confirm-dismiss="Keep" data-confirm-icon="x-mark">
                                        @csrf
                                        <button class="btn btn-outline-danger btn-row" type="submit">Cancel</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="px-5 pb-4">{{ $changes->withQueryString()->links() }}</div>
        </section>
    @endif

    {{-- The details this resident controls, and the household they belong to. --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
                <h2 class="font-semibold text-slate-900">Contact details</h2>
                <p class="text-sm text-slate-500">Update these yourself: profile photo, phone number and address.</p>
            </div>
            <form method="POST" action="{{ route('resident.contact.update') }}" enctype="multipart/form-data" class="space-y-4 p-6">
                @csrf
                @method('PUT')
                @if (session('success'))
                    <p role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</p>
                @endif
                <div>
                    <label for="phone_number" class="mb-1.5 block text-sm font-medium text-slate-700">Phone number</label>
                    <input id="phone_number" type="tel" name="phone_number" value="{{ old('phone_number', $resident->phone_number) }}"
                        placeholder="09XX XXX XXXX" maxlength="15" inputmode="numeric" data-phone="true" pattern="[0-9+()\- ]*"
                        class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                    @error('phone_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="address" class="mb-1.5 block text-sm font-medium text-slate-700">Home address *</label>
                    <input id="address" type="text" name="address" value="{{ old('address', $resident->address) }}" required maxlength="255"
                        class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('address') border-red-500! @enderror">
                    @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="photo" class="mb-1.5 block text-sm font-medium text-slate-700">Profile photo (optional)</label>
                    @if ($resident->photo)
                        {{-- The button sits with the photo it removes but belongs to
                             #remove-photo-form below (HTML `form` attribute), so it
                             confirms and submits on its own instead of saving the
                             contact fields along with it. --}}
                        <div class="mb-2 flex items-center gap-3">
                            <img src="{{ route('resident.photo') }}" alt="Current profile photo" class="h-16 w-16 rounded-full object-cover">
                            <button type="submit" form="remove-photo-form"
                                class="btn btn-outline-danger">Remove</button>
                        </div>
                    @endif
                    <input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-file-max-kb="2048" class="block min-h-11 w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sky-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-sky-700 hover:file:bg-sky-100">
                    @error('photo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Account sign-in is a separate concern from the contact details
                     above, so it is kept under its own heading and never mixed in. --}}
                <div class="border-t border-slate-200 pt-4">
                    <h3 class="text-sm font-semibold text-slate-800">Sign-in email (optional)</h3>
                    <p class="mt-1 text-xs text-slate-500">You sign in as {{ auth()->user()->email }}. Leave the fields below blank to keep it; a change requires your current password.</p>
                    <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">New email</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" maxlength="150" autocomplete="email"
                                class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('email') border-red-500! @enderror">
                            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="email_confirmation" class="mb-1.5 block text-sm font-medium text-slate-700">Confirm new email</label>
                            <input id="email_confirmation" type="email" name="email_confirmation" value="{{ old('email_confirmation') }}" maxlength="150" autocomplete="email"
                                class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('email_confirmation') border-red-500! @enderror">
                            @error('email_confirmation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="mt-4">
                        <label for="current_password" class="mb-1.5 block text-sm font-medium text-slate-700">Current password</label>
                        <input id="current_password" type="password" name="current_password" autocomplete="current-password"
                            class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                        @error('current_password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <button type="submit"
                    class="btn btn-primary w-full">
                    Save changes
                </button>
            </form>

            {{-- Forms cannot nest, so the removal posts from here while the Remove
                 button above joins this form through the `form` attribute. Only
                 CSRF and the method override travel with it: removing a photo must
                 not depend on the phone, address and email validating. --}}
            @if ($resident->photo)
                <form id="remove-photo-form" method="POST" action="{{ route('resident.photo.destroy') }}"
                    data-confirm="Remove your profile photo?"
                    data-confirm-title="Remove photo"
                    data-confirm-accept="Remove"
                    data-confirm-dismiss="Cancel"
                    data-confirm-icon="trash">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        </div>

        {{-- Household: who else is on the same record. --}}
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden self-start">
            <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
                <h2 class="font-semibold text-slate-900">Household members</h2>
                <p class="text-sm text-slate-500">
                    {{ $resident->household?->household_code ?? '—' }} · {{ $householdMembers->count() + 1 }} on record · you are {{ $resident->is_household_head ? 'the household head' : 'a member' }}
                </p>
            </div>
            @if ($householdMembers->isEmpty())
                <p class="px-6 py-6 text-sm text-slate-500">No other members are listed on this household.</p>
            @else
                <ul class="divide-y divide-slate-200">
                    @foreach ($householdMembers as $member)
                        <li class="flex items-center justify-between gap-3 px-6 py-3">
                            <span class="text-sm font-medium text-slate-900">{{ $member->full_name }}</span>
                            @if ($member->is_household_head)
                                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Head</span>
                            @else
                                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Member</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

</div>

@endsection
</x-app-layout>
