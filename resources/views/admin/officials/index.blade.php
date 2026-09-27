@extends('layouts.app')

@section('title', 'Officials')

@section('content')
    @php($activeStatus = \App\Models\Official::STATUS_ACTIVE)

    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Officials</h1>
            <p class="mt-1 text-sm text-slate-500">
                The Sangguniang Barangay roster &mdash; positions, terms of office and contact numbers.
            </p>
        </div>

        <button type="button"
                data-open-modal="official-modal"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
            + New official
        </button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Full name</th>
                    <th class="px-5 py-3">Position</th>
                    <th class="px-5 py-3">Term</th>
                    <th class="px-5 py-3">Contact</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($officials as $official)
                    <tr>
                        <td class="px-5 py-3.5 font-medium text-slate-900">{{ $official->full_name }}</td>
                        <td class="px-5 py-3.5 text-slate-600">{{ $official->position }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-slate-600">
                            {{ $official->term_start?->format('M j, Y') ?? '—' }} &ndash; {{ $official->term_end?->format('M j, Y') ?? '—' }}
                        </td>
                        <td class="px-5 py-3.5 text-slate-600">{{ $official->contact !== null && $official->contact !== '' ? $official->contact : '—' }}</td>
                        <td class="px-5 py-3.5">
                            @if ($official->status === $activeStatus)
                                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Active</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">{{ $official->status }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex flex-wrap justify-end gap-2">
                                <button type="button"
                                        data-open-modal="official-modal-{{ $official->id }}"
                                        class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                                    Edit
                                </button>
                                <button type="button"
                                        data-open-modal="official-delete-modal-{{ $official->id }}"
                                        class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">
                            No officials on record yet. Use &ldquo;+ New official&rdquo; to add the Punong Barangay and the council.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $officials->links() }}
    </div>

    {{-- Position suggestions shared by every form on this page --}}
    <datalist id="official-position-options">
        @foreach ($positions as $position)
            <option value="{{ $position }}"></option>
        @endforeach
    </datalist>

    {{-- ── New official dialog ── --}}
    <div id="official-modal"
         data-modal
         role="dialog"
         aria-modal="true"
         aria-labelledby="official-modal-title"
         aria-hidden="true"
         class="fixed inset-0 z-40 hidden">
        <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" data-close-modal></div>

        <div class="absolute inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
                <div class="relative w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-7">

                    <button type="button"
                            data-close-modal
                            aria-label="Close dialog"
                            class="absolute right-4 top-4 rounded-md p-1.5 text-slate-400 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" width="18" height="18">
                            <path d="M18 6 6 18M6 6l12 12"/>
                        </svg>
                    </button>

                    <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-400">Barangay officials</p>
                    <h2 id="official-modal-title" class="mt-2 text-xl font-semibold text-slate-900">New official</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-500">
                        Add a member of the Sangguniang Barangay. The Punong Barangay on record signs every issued certificate.
                    </p>

                    <form method="POST" action="{{ route('admin.officials.store') }}"
                          data-submit-loading data-loading-label="Saving…"
                          class="mt-6 space-y-4">
                        @csrf

                        <div>
                            <label for="official_full_name" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Full name</label>
                            <input id="official_full_name"
                                   type="text"
                                   name="full_name"
                                   value="{{ old('full_name') }}"
                                   required
                                   maxlength="150"
                                   autocomplete="off"
                                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('full_name') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                            @error('full_name')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="official_position" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Position</label>
                            <input id="official_position"
                                   type="text"
                                   name="position"
                                   list="official-position-options"
                                   value="{{ old('position') }}"
                                   required
                                   maxlength="100"
                                   autocomplete="off"
                                   placeholder="Start typing or pick a suggestion"
                                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('position') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                            @error('position')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="official_term_start" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Term start</label>
                                <input id="official_term_start"
                                       type="date"
                                       name="term_start"
                                       value="{{ old('term_start') }}"
                                       required
                                       class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('term_start') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                                @error('term_start')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="official_term_end" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Term end</label>
                                <input id="official_term_end"
                                       type="date"
                                       name="term_end"
                                       value="{{ old('term_end') }}"
                                       required
                                       class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('term_end') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                                @error('term_end')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="official_contact" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Contact number</label>
                                <input id="official_contact"
                                       type="text"
                                       name="contact"
                                       value="{{ old('contact') }}"
                                       maxlength="30"
                                       autocomplete="off"
                                       placeholder="Optional"
                                       class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('contact') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                                @error('contact')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="official_status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                                <select id="official_status"
                                        name="status"
                                        required
                                        class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('status') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                                    <option value="Active" @selected(old('status', 'Active') === 'Active')>Active</option>
                                    <option value="Inactive" @selected(old('status') === 'Inactive')>Inactive</option>
                                </select>
                                @error('status')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <button type="submit"
                                class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300 disabled:cursor-wait disabled:opacity-70">
                            Save official
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

    {{-- ── Per-row dialogs: edit + delete confirmation ── --}}
    @foreach ($officials as $official)
        <div id="official-modal-{{ $official->id }}"
             data-modal
             role="dialog"
             aria-modal="true"
             aria-labelledby="official-modal-title-{{ $official->id }}"
             aria-hidden="true"
             class="fixed inset-0 z-40 hidden">
            <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" data-close-modal></div>

            <div class="absolute inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
                    <div class="relative w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-7">

                        <button type="button"
                                data-close-modal
                                aria-label="Close dialog"
                                class="absolute right-4 top-4 rounded-md p-1.5 text-slate-400 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" width="18" height="18">
                                <path d="M18 6 6 18M6 6l12 12"/>
                            </svg>
                        </button>

                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-400">Barangay officials</p>
                        <h2 id="official-modal-title-{{ $official->id }}" class="mt-2 text-xl font-semibold text-slate-900">Edit official</h2>
                        <p class="mt-2 text-sm leading-relaxed text-slate-500">
                            Update the record for {{ $official->full_name }}.
                        </p>

                        <form method="POST" action="{{ route('admin.officials.update', $official) }}"
                              data-submit-loading data-loading-label="Saving…"
                              class="mt-6 space-y-4">
                            @csrf
                            @method('PUT')

                            <div>
                                <label for="official_full_name_{{ $official->id }}" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Full name</label>
                                <input id="official_full_name_{{ $official->id }}"
                                       type="text"
                                       name="full_name"
                                       value="{{ old('full_name', $official->full_name) }}"
                                       required
                                       maxlength="150"
                                       autocomplete="off"
                                       class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('full_name') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                                @error('full_name')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="official_position_{{ $official->id }}" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Position</label>
                                <input id="official_position_{{ $official->id }}"
                                       type="text"
                                       name="position"
                                       list="official-position-options"
                                       value="{{ old('position', $official->position) }}"
                                       required
                                       maxlength="100"
                                       autocomplete="off"
                                       class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('position') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                                @error('position')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="official_term_start_{{ $official->id }}" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Term start</label>
                                    <input id="official_term_start_{{ $official->id }}"
                                           type="date"
                                           name="term_start"
                                           value="{{ old('term_start', $official->term_start?->format('Y-m-d')) }}"
                                           required
                                           class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('term_start') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                                    @error('term_start')
                                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="official_term_end_{{ $official->id }}" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Term end</label>
                                    <input id="official_term_end_{{ $official->id }}"
                                           type="date"
                                           name="term_end"
                                           value="{{ old('term_end', $official->term_end?->format('Y-m-d')) }}"
                                           required
                                           class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('term_end') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                                    @error('term_end')
                                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="official_contact_{{ $official->id }}" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Contact number</label>
                                    <input id="official_contact_{{ $official->id }}"
                                           type="text"
                                           name="contact"
                                           value="{{ old('contact', $official->contact) }}"
                                           maxlength="30"
                                           autocomplete="off"
                                           placeholder="Optional"
                                           class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('contact') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                                    @error('contact')
                                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="official_status_{{ $official->id }}" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                                    <select id="official_status_{{ $official->id }}"
                                            name="status"
                                            required
                                            class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('status') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                                        <option value="Active" @selected(old('status', $official->status) === 'Active')>Active</option>
                                        <option value="Inactive" @selected(old('status', $official->status) === 'Inactive')>Inactive</option>
                                    </select>
                                    @error('status')
                                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
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

        <div id="official-delete-modal-{{ $official->id }}"
             data-modal
             role="dialog"
             aria-modal="true"
             aria-labelledby="official-delete-title-{{ $official->id }}"
             aria-hidden="true"
             class="fixed inset-0 z-40 hidden">
            <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" data-close-modal></div>

            <div class="absolute inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
                    <div class="relative w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">

                        <button type="button"
                                data-close-modal
                                aria-label="Close dialog"
                                class="absolute right-4 top-4 rounded-md p-1.5 text-slate-400 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" width="18" height="18">
                                <path d="M18 6 6 18M6 6l12 12"/>
                            </svg>
                        </button>

                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-red-400">Confirmation</p>
                        <h2 id="official-delete-title-{{ $official->id }}" class="mt-2 text-xl font-semibold text-slate-900">
                            Delete this official?
                        </h2>
                        <p class="mt-2 text-sm leading-relaxed text-slate-500">
                            {{ $official->full_name }} ({{ $official->position }}) will be removed from the list of barangay officials. This cannot be undone.
                        </p>

                        <form method="POST" action="{{ route('admin.officials.destroy', $official) }}"
                              data-submit-loading data-loading-label="Deleting…"
                              class="mt-6">
                            @csrf
                            @method('DELETE')

                            <button type="submit"
                                    class="w-full rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-200 disabled:cursor-wait disabled:opacity-70">
                                Delete official
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
    @endforeach
@endsection
