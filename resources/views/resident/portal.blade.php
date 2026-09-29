<x-app-layout>
@section('page_header')
    <x-page-header title="My Profile" subtitle="Your resident record on file with the barangay office." />
@endsection

@section('content')

<div class="max-w-5xl mx-auto space-y-6">

    {{-- Overview: what this resident has asked for, what is waiting on the
         office, and what they can already collect. Same shape as the office
         dashboard, scoped to what a resident is actually allowed to do. --}}
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('resident.requests') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Certificate requests</p>
                <x-icon name="document-text" class="h-6 w-6 text-sky-600" />
            </div>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $requestTotal }}</p>
        </a>
        <a href="{{ route('resident.requests') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Awaiting review</p>
                <x-icon name="clock" class="h-6 w-6 text-sky-600" />
            </div>
            <p class="mt-2 text-3xl font-bold {{ $requestPending > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ $requestPending }}</p>
        </a>
        <a href="{{ route('resident.requests') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Ready to download</p>
                <x-icon name="arrow-down-tray" class="h-6 w-6 text-sky-600" />
            </div>
            <p class="mt-2 text-3xl font-bold {{ $requestApproved > 0 ? 'text-emerald-600' : 'text-slate-900' }}">{{ $requestApproved }}</p>
        </a>
        <a href="{{ route('resident.changes') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Corrections pending</p>
                <x-icon name="pencil-square" class="h-6 w-6 text-sky-600" />
            </div>
            <p class="mt-2 text-3xl font-bold {{ $changePending > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ $changePending }}</p>
        </a>
    </div>

    {{-- Read-only record + the one action worth surfacing beside it. --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
                <h2 class="font-semibold text-slate-900">{{ $resident->full_name }}</h2>
                <p class="text-sm text-slate-500">Resident record</p>
            </div>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 p-6 text-sm sm:grid-cols-2">
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
                Corrections to the record above can only be made by the barangay office — please visit or call.
            </p>
        </div>

        {{-- Certificate requests shortcut --}}
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
                <h2 class="font-semibold text-slate-900">Need a barangay certificate?</h2>
                <p class="text-sm text-slate-500">Clearance · Residency · Indigency</p>
            </div>
            <div class="p-6">
                <p class="text-sm text-slate-600">Request online and collect it at the office — no need to travel twice.</p>
                <a href="{{ route('resident.requests') }}" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2">
                    <x-icon name="document-text" class="h-4 w-4" />
                    Request a certificate
                </a>
            </div>
        </div>
    </div>

    {{-- Editable contact + the household they belong to. --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
                <h2 class="font-semibold text-slate-900">Contact details</h2>
                <p class="text-sm text-slate-500">Keep these current</p>
            </div>
            <form method="POST" action="{{ route('resident.contact.update') }}" class="space-y-4 p-6">
                @csrf
                @method('PUT')
                <div>
                    <label for="phone_number" class="mb-1.5 block text-sm font-medium text-slate-700">Phone number</label>
                    <input id="phone_number" type="tel" name="phone_number" value="{{ old('phone_number', $resident->phone_number) }}"
                        placeholder="09171234567" maxlength="15" inputmode="tel" data-phone="true" pattern="[0-9+()\- ]*"
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
                        <img src="{{ route('resident.photo') }}" alt="Current profile photo" class="mb-2 h-16 w-16 rounded-full object-cover">
                    @endif
                    <input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="block min-h-11 w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-sky-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-sky-700 hover:file:bg-sky-100">
                    @error('photo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="border-t border-slate-200 pt-4">
                    <h3 class="text-sm font-semibold text-slate-800">Change login email (optional)</h3>
                    <p class="mt-1 text-xs text-slate-500">Confirm your current password. The new address will be used for sign-in and emailed notifications.</p>
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
                    class="min-h-11 w-full rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2">
                    Save Changes
                </button>
            </form>
        </div>

        {{-- Household: who else is on the same record. --}}
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden self-start">
            <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
                <h2 class="font-semibold text-slate-900">Household members</h2>
                <p class="text-sm text-slate-500">
                    {{ $resident->household?->household_code ?? '—' }} · {{ $householdMembers->count() + 1 }} on record
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
