@extends('layouts.app')

@section('title', 'Puroks')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Puroks</h1>
            <p class="mt-1 text-sm text-slate-500">
                Zones of Barangay Bidduang &mdash; and how many residents and households belong to each one.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('residents.directory') }}"
               class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                Resident directory
            </a>

            @if (auth()->user()->isAdmin())
                <button type="button"
                        data-open-modal="purok-modal"
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                    + New purok
                </button>
            @endif
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Name</th>
                    <th class="px-5 py-3">Code</th>
                    <th class="px-5 py-3">Description</th>
                    <th class="px-5 py-3 text-right">Residents</th>
                    <th class="px-5 py-3 text-right">Households</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($puroks as $purok)
                    <tr>
                        <td class="px-5 py-3.5 font-medium text-slate-900">{{ $purok->name }}</td>
                        <td class="px-5 py-3.5">
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $purok->code }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-slate-500">{{ $purok->description ?: '—' }}</td>
                        <td class="px-5 py-3.5 text-right text-slate-700">{{ $purok->residents_count }}</td>
                        <td class="px-5 py-3.5 text-right text-slate-700">{{ $purok->households_count }}</td>
                        <td class="px-5 py-3.5">
                            <div class="flex flex-wrap justify-end gap-2">
                                @if (auth()->user()->isAdmin())
                                    <a href="{{ route('puroks.edit', $purok) }}"
                                       class="rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                                        Edit
                                    </a>

                                    <form method="POST" action="{{ route('puroks.destroy', $purok) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="rounded-md border border-red-200 px-3 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50">
                                            Delete
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400">&mdash;</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">
                            No puroks have been registered yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $puroks->links() }}
    </div>

    {{-- ── New purok dialog — centered on screen ── --}}
    <div id="purok-modal"
         data-modal
         role="dialog"
         aria-modal="true"
         aria-labelledby="purok-modal-title"
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

                    <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-400">Registry</p>
                    <h2 id="purok-modal-title" class="mt-2 text-xl font-semibold text-slate-900">New purok</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-500">
                        Register a zone of the barangay. Puroks are used to group residents, households and reports.
                    </p>

                    @if ($errors->any())
                        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                            <ul class="list-inside list-disc space-y-0.5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="mt-6">
                        @include('puroks._form', [
                            'action' => route('puroks.store'),
                            'method' => 'POST',
                            'purok' => new \App\Models\Purok(),
                            'submitLabel' => 'Create purok',
                        ])
                    </div>

                    <div class="mt-5 border-t border-slate-100 pt-4 text-center">
                        <button type="button" data-close-modal class="text-sm text-slate-500 transition hover:text-slate-800">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
