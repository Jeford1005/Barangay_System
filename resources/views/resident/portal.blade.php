@extends('layouts.app')

@section('title', 'My barangay')

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-slate-900">My barangay</h1>
        <p class="mt-1 text-sm text-slate-500">
            Your resident profile, contact details and certificate requests in one place.
        </p>
    </div>

    @if ($resident === null)
        {{-- ── Account not matched to a resident record yet ── --}}
        <div class="mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 ring-1 ring-slate-200">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="h-7 w-7 text-slate-400">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>

            <h2 class="mt-4 text-lg font-semibold text-slate-900">No resident profile linked yet</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-500">
                We could not match this account to a resident record yet. Visit the barangay office
                (or make sure your resident record uses this same email) so we can link your profile.
            </p>
        </div>

    @elseif ($resident->isArchived())
        {{-- ── Archived record: notice only, no profile data ── --}}
        <div class="mx-auto max-w-2xl rounded-2xl border border-amber-200 bg-amber-50 p-8 text-center shadow-sm">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 ring-1 ring-amber-200">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="h-7 w-7 text-amber-600">
                    <path d="M12 9v4"/>
                    <path d="M12 17h.01"/>
                    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
                </svg>
            </div>

            <h2 class="mt-4 text-lg font-semibold text-amber-900">Record archived</h2>
            <p class="mt-2 text-sm leading-relaxed text-amber-800">
                Your resident record is archived. Please contact the barangay office.
            </p>
        </div>

    @else
        @php
            $initials = mb_strtoupper(
                mb_substr((string) $resident->first_name, 0, 1).mb_substr((string) $resident->last_name, 0, 1)
            );
        @endphp

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- ── Photo + quick actions ── --}}
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                @if ($resident->photo_path)
                    <img src="{{ route('resident.photo') }}"
                         alt="Photo of {{ $resident->full_name }}"
                         width="144" height="144"
                         class="h-36 w-36 rounded-full object-cover ring-1 ring-slate-200">
                @else
                    <div class="flex h-36 w-36 items-center justify-center rounded-full bg-slate-200 text-3xl font-semibold uppercase tracking-wide text-slate-500 ring-1 ring-slate-200"
                         aria-hidden="true">
                        {{ $initials }}
                    </div>
                @endif

                <p class="mt-4 text-base font-semibold text-slate-900">{{ $resident->full_name }}</p>
                <p class="mt-0.5 text-sm text-slate-500">
                    {{ $resident->purok?->label() ?? 'No purok assigned' }}
                </p>

                <div class="mt-5 space-y-2">
                    <a href="{{ route('resident.requests') }}"
                       class="block rounded-lg bg-slate-900 px-4 py-2 text-center text-sm font-medium text-white transition hover:bg-slate-800">
                        My requests
                    </a>
                    <button type="button"
                            data-open-modal="contact-modal"
                            class="block w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        Update contact details
                    </button>
                </div>
            </div>

            {{-- ── Profile details ── --}}
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-slate-900">Profile</h2>
                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                        {{ $resident->status }}
                    </span>
                </div>

                <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Full name</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ $resident->full_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sex &amp; age</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">
                            {{ $resident->sex ?? '—' }} &middot; {{ $resident->age ?? '—' }} {{ $resident->age !== null ? 'yrs old' : '' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Civil status</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ $resident->civil_status ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Birth date</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">
                            {{ $resident->birth_date?->format('F j, Y') ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Occupation</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ $resident->occupation ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Household number</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">
                            {{ $resident->household?->household_number ?? '—' }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Address</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">
                            {{ $resident->resolvedAddress() ?: '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Purok</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">
                            {{ $resident->purok?->label() ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Member since</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">
                            {{ $resident->created_at->format('M j, Y') }}
                        </dd>
                    </div>
                </dl>

                <div class="mt-6 border-t border-slate-100 pt-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-base font-semibold text-slate-900">Contact details</h3>
                        <button type="button"
                                data-open-modal="contact-modal"
                                class="rounded-lg border border-slate-300 px-3.5 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Edit
                        </button>
                    </div>

                    <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Phone</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-900">{{ $resident->phone ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-900">{{ $resident->email ?: '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        {{-- ── Contact update dialog — centered on screen ── --}}
        <div id="contact-modal"
             data-modal
             role="dialog"
             aria-modal="true"
             aria-labelledby="contact-modal-title"
             aria-hidden="true"
             class="fixed inset-0 z-40 hidden"
             @if ($errors->any()) data-open-on-load @endif>
            <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" data-close-modal></div>

            <div class="absolute inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
                    <div class="relative w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-7">

                        <button type="button"
                                data-close-modal
                                aria-label="Close dialog"
                                class="absolute right-4 top-4 rounded-md p-1.5 text-slate-400 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" width="18" height="18">
                                <path d="M18 6 6 18M6 6l12 12"/>
                            </svg>
                        </button>

                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-400">My barangay</p>
                        <h2 id="contact-modal-title" class="mt-2 text-xl font-semibold text-slate-900">Update contact details</h2>
                        <p class="mt-2 text-sm leading-relaxed text-slate-500">
                            Keep your phone number, address and email up to date so the barangay office
                            can reach you. Your name and civil status are updated at the office.
                        </p>

                        <form method="POST" action="{{ route('resident.contact.update') }}"
                              data-submit-loading data-loading-label="Saving…"
                              class="mt-6 space-y-4">
                            @csrf
                            @method('PUT')

                            <div>
                                <label for="contact_phone" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Phone</label>
                                <input id="contact_phone"
                                       type="tel"
                                       name="phone"
                                       value="{{ old('phone', $resident->phone) }}"
                                       maxlength="30"
                                       autocomplete="tel"
                                       placeholder="09XX XXX XXXX"
                                       class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('phone') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                                @error('phone')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="contact_address" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Address</label>
                                <textarea id="contact_address"
                                          name="address"
                                          rows="2"
                                          maxlength="255"
                                          placeholder="House no., street, purok"
                                          class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('address') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">{{ old('address', $resident->address) }}</textarea>
                                @error('address')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="contact_email" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Email address</label>
                                <input id="contact_email"
                                       type="email"
                                       name="email"
                                       value="{{ old('email', $resident->email) }}"
                                       maxlength="150"
                                       autocomplete="email"
                                       placeholder="you@example.com"
                                       class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('email') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                                <p class="mt-1.5 text-xs text-slate-400">
                                    This is also the email you sign in with &mdash; changing it here changes both.
                                </p>
                                @error('email')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <button type="submit"
                                    class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300 disabled:cursor-wait disabled:opacity-70">
                                Save changes
                            </button>
                        </form>

                        <div class="mt-5 border-t border-slate-100 pt-4 text-center">
                            <button type="button" data-close-modal class="text-sm text-slate-500 transition hover:text-slate-800">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
