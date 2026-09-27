@extends('layouts.app')

@section('title', 'New certificate')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">New certificate</h1>
            <p class="mt-1 text-sm text-slate-500">
                Pick the resident and the document, state the purpose, and the system reserves the next control number.
            </p>
        </div>

        <a href="{{ route('certificates.index') }}"
           class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
            &larr; Back to certificates
        </a>
    </div>

    <div class="max-w-3xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-7">
        <form method="POST" action="{{ route('certificates.store') }}"
              data-submit-loading data-loading-label="Issuing…"
              class="space-y-5">
            @csrf

            <div>
                <label for="resident_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Resident</label>
                <select id="resident_id"
                        name="resident_id"
                        required
                        class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('resident_id') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                    <option value="">Choose an active resident…</option>
                    @foreach ($residents as $resident)
                        <option value="{{ $resident['id'] }}"
                                @selected((string) old('resident_id', $selectedResident) === (string) $resident['id'])>
                            {{ $resident['label'] }}
                        </option>
                    @endforeach
                </select>
                @error('resident_id')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1.5 text-xs text-slate-400">Archived residents cannot be issued certificates.</p>
            </div>

            <div>
                <label for="document_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Document</label>
                <select id="document_id"
                        name="document_id"
                        required
                        class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('document_id') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                    <option value="">Choose a certificate or clearance…</option>
                    @foreach ($documents as $document)
                        <option value="{{ $document->id }}" @selected(old('document_id') == $document->id)>
                            {{ $document->code }} — {{ $document->title }} (₱{{ number_format((float) $document->fee, 2) }})
                        </option>
                    @endforeach
                </select>
                @error('document_id')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1.5 text-xs text-slate-400">Only active certificates and clearances appear here.</p>
            </div>

            <div>
                <label for="purpose" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Purpose</label>
                <input id="purpose"
                       type="text"
                       name="purpose"
                       value="{{ old('purpose') }}"
                       required
                       maxlength="255"
                       placeholder="e.g. Local employment requirement"
                       autocomplete="off"
                       class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('purpose') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                @error('purpose')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="copies" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Copies</label>
                    <select id="copies"
                            name="copies"
                            required
                            class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('copies') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                        @for ($copy = 1; $copy <= 5; $copy++)
                            <option value="{{ $copy }}" @selected((string) old('copies', '1') === (string) $copy)>
                                {{ $copy }} {{ $copy > 1 ? 'copies' : 'copy' }}
                            </option>
                        @endfor
                    </select>
                    @error('copies')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                @if (auth()->user()->isAdmin())
                    <div>
                        <label for="fee" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Fee override <span class="text-slate-400 normal-case font-normal">(admin)</span>
                        </label>
                        <input id="fee"
                               type="number"
                               name="fee"
                               value="{{ old('fee') }}"
                               min="0"
                               max="9999"
                               step="0.01"
                               placeholder="Catalog fee"
                               class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('fee') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                        @error('fee')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1.5 text-xs text-slate-400">Leave blank to charge the catalog fee. Staff always pay the catalog fee.</p>
                    </div>
                @endif
            </div>

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
                <button type="submit"
                        class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300 disabled:cursor-wait disabled:opacity-70">
                    Issue certificate
                </button>
                <a href="{{ route('certificates.index') }}" class="text-sm font-medium text-slate-500 transition hover:text-slate-800">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection
