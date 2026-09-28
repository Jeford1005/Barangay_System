<x-app-layout>
@section('page_header')
    <x-page-header title="Issue Certificate" subtitle="A control number (e.g. CLR-{{ date('Y') }}-0001) is assigned automatically on save, then the certificate opens ready to print." />
@endsection

@section('content')
{{-- Form card: also the dialog fragment (fetched with ?fragment=1). --}}
<div class="bg-white {{ request()->boolean('fragment') ? '' : 'rounded-xl shadow overflow-hidden' }} {{ request()->boolean('fragment') ? 'max-w-none' : 'max-w-4xl mx-auto' }}">
    <form action="{{ route('certificates.store') }}" method="POST" class="{{ request()->boolean('fragment') ? 'p-0' : 'p-6' }} space-y-8">
        @csrf

        <fieldset>
            <legend class="text-sm font-semibold text-slate-900 mb-4">Certificate</legend>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="document_id" class="mb-1.5 block text-sm font-medium text-slate-700">Certificate Type <span class="text-red-500">*</span></label>
                    <select name="document_id" id="document_id" required
                        class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                        <option value="">Choose a certificate…</option>
                        @foreach ($documents as $document)
                            <option value="{{ $document->id }}"
                                data-fee="{{ $document->fee }}"
                                data-requirements="{{ e($document->requirements) }}"
                                @selected(old('document_id') == $document->id)>
                                {{ $document->code }} — {{ $document->title }} (₱{{ number_format((float) $document->fee, 2) }})
                            </option>
                        @endforeach
                    </select>
                    @error('document_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <p id="requirements-hint" class="mt-1 hidden text-sm text-slate-500"></p>
                </div>

                <div class="sm:col-span-2">
                    <label for="resident_id" class="mb-1.5 block text-sm font-medium text-slate-700">Resident <span class="text-red-500">*</span></label>
                    <select name="resident_id" id="resident_id" required
                        class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                        <option value="">Choose a resident…</option>
                        @foreach ($residents as $resident)
                            <option value="{{ $resident->id }}" @selected(old('resident_id') == $resident->id)>
                                {{ $resident->full_name }}@if($resident->purok) — {{ $resident->purok->name }}@endif
                            </option>
                        @endforeach
                    </select>
                    @error('resident_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="purpose" class="mb-1.5 block text-sm font-medium text-slate-700">Purpose <span class="text-red-500">*</span></label>
                    <input type="text" name="purpose" id="purpose" required maxlength="255"
                        value="{{ old('purpose') }}" placeholder="e.g. Local employment, school requirement, medical assistance"
                        class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600" />
                    @error('purpose') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="copies" class="mb-1.5 block text-sm font-medium text-slate-700">Copies <span class="text-red-500">*</span></label>
                    <input type="number" name="copies" id="copies" required min="1" max="5" step="1" inputmode="numeric" value="{{ old('copies', 1) }}"
                        class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600" />
                    @error('copies') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="fee" class="mb-1.5 block text-sm font-medium text-slate-700">Fee (₱)</label>
                    @if (auth()->user()?->isAdmin())
                     <input type="number" name="fee" id="fee" min="0" max="9999" step="0.01" inputmode="decimal"
                        value="{{ old('fee') }}" placeholder="Standard fee applies"
                        class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600" />
                    <p class="mt-1 text-xs text-slate-500">Defaults to the posted fee. Leave blank to charge the standard price, or set 0 to waive.</p>
                    @error('fee') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                     @else
                         <p class="mt-1 text-xs text-slate-500">The posted catalog fee applies. Fee waivers and overrides require an administrator.</p>
                     @endif
                </div>

                <div class="sm:col-span-2">
                    <label for="remarks" class="mb-1.5 block text-sm font-medium text-slate-700">Remarks</label>
                    <textarea name="remarks" id="remarks" rows="2" maxlength="1000"
                        class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">{{ old('remarks') }}</textarea>
                    @error('remarks') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </fieldset>

        <x-form.actions cancel-href="{{ route('certificates.index') }}" submit-label="Issue & Print" />
    </form>
</div>

<script>
    // Live fee default + requirements hint while the clerk picks a type.
    (function () {
        var select = document.getElementById('document_id');
        var fee = document.getElementById('fee');
        var hint = document.getElementById('requirements-hint');
        if (!select) return;

        function sync() {
            var opt = select.options[select.selectedIndex];
            if (!opt || !opt.value) {
                if (fee) fee.value = '';
                hint.classList.add('hidden');
                return;
            }
            if (fee) fee.placeholder = parseFloat(opt.dataset.fee || 0).toFixed(2);
            if (opt.dataset.requirements) {
                hint.textContent = 'Requirements: ' + opt.dataset.requirements.split('\n').join(', ');
                hint.classList.remove('hidden');
            } else {
                hint.classList.add('hidden');
            }
        }
        select.addEventListener('change', sync);
        sync();
    })();
</script>
@endsection
</x-app-layout>
