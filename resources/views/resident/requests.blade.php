<x-app-layout>
@section('page_header')
    <x-page-header title="Request" subtitle="Request barangay certificates online — we'll email you when they're ready." />
@endsection

@section('content')

<div class="max-w-4xl mx-auto">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Request form --}}
        <div class="lg:col-span-1 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden h-fit">
            <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
                <h2 class="font-semibold text-slate-900">New request</h2>
            </div>
            <form method="POST" action="{{ route('resident.requests.store') }}" class="space-y-4 p-6">
                @csrf
                <div>
                    <label for="document_id" class="mb-1.5 block text-sm font-medium text-slate-700">Certificate type *</label>
                    <select id="document_id" name="document_id" required
                        class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                        <option value="">Choose…</option>
                        @foreach ($documents as $document)
                            <option value="{{ $document->id }}" data-requirements="{{ $document->requirements }}" @selected(old('document_id') == $document->id)>
                                {{ $document->title }} (@if ((float) $document->fee > 0)₱{{ number_format((float) $document->fee, 2) }}@else free @endif)
                            </option>
                        @endforeach
                    </select>
                    @error('document_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <p id="document-requirements" class="mt-2 hidden rounded-lg bg-sky-50 px-3 py-2 text-xs text-sky-900"></p>
                </div>
                <div>
                    <label for="purpose" class="mb-1.5 block text-sm font-medium text-slate-700">Purpose *</label>
                    <input id="purpose" type="text" name="purpose" required maxlength="255"
                        value="{{ old('purpose') }}" placeholder="e.g. Local employment"
                        class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                    @error('purpose') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="copies" class="mb-1.5 block text-sm font-medium text-slate-700">Copies *</label>
                    <select id="copies" name="copies" required
                        class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                        @foreach ([1, 2, 3, 4, 5] as $n)
                            <option value="{{ $n }}" @selected(old('copies', 1) == $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                    @error('copies') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit"
                    class="btn btn-primary w-full">
                    Submit request
                </button>
                <p class="text-xs text-slate-500">One pending request per certificate type. The fee, if any, is paid when you claim the certificate at the barangay hall.</p>
            </form>
            <script>
                (function () {
                    const select = document.getElementById('document_id');
                    const requirements = document.getElementById('document-requirements');
                    if (!select || !requirements) return;
                    const update = () => {
                        const option = select.options[select.selectedIndex];
                        const text = option?.dataset.requirements?.trim();
                        requirements.textContent = text ? 'Bring: ' + text.replace(/\n/g, ' · ') : '';
                        requirements.classList.toggle('hidden', !text);
                    };
                    select.addEventListener('change', update);
                    update();
                })();
            </script>
        </div>

        {{-- Requests list --}}
        <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
                <h2 class="font-semibold text-slate-900">Certificate requests</h2>
            </div>

            @if ($requests->isEmpty())
                <div class="p-8 text-center text-slate-500">
                    <p class="text-sm">You haven't requested any certificates yet.</p>
                    <p class="mt-1 text-xs text-slate-500">Use the form to submit your first request.</p>
                </div>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($requests as $req)
                        <li class="p-6">
                            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $req->document->title }}</p>
                                    <p class="mt-0.5 text-sm text-slate-500">Purpose: {{ $req->purpose }} · {{ $req->copies }} cop{{ $req->copies === 1 ? 'y' : 'ies' }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">Submitted {{ $req->created_at->format('M j, Y') }}</p>

                                    @if ($req->status === 'Approved' && $req->issuance)
                                        <p class="mt-2 text-sm text-emerald-700">Control No. <strong>{{ $req->issuance->control_number }}</strong> — ready for claim at the barangay hall.</p>
                                         <a href="{{ route('resident.requests.certificate', $req) }}" target="_blank" class="mt-1 inline-flex text-xs font-semibold text-sky-700 underline hover:text-sky-900 focus:outline-none focus:ring-2 focus:ring-sky-600">View my certificate</a>
                                    @elseif ($req->status === 'Rejected')
                                        <p class="mt-2 text-sm text-red-700">Not approved: {{ $req->rejection_reason ?: 'contact the barangay office.' }}</p>
                                    @endif
                                </div>
                                <div class="flex flex-col items-start sm:items-end gap-2">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                        {{ ['Pending' => 'bg-amber-100 text-amber-800', 'Approved' => 'bg-emerald-100 text-emerald-800', 'Rejected' => 'bg-red-100 text-red-800', 'Cancelled' => 'bg-slate-100 text-slate-600'][$req->status] }}">
                                        {{ $req->status }}
                                    </span>
                                    @if ($req->status === 'Pending')
                                        <form method="POST" action="{{ route('resident.requests.cancel', $req) }}"
                                            data-confirm="Cancel this request?"
                                            data-confirm-title="Cancel request"
                                            data-confirm-accept="Cancel"
                                            data-confirm-dismiss="Keep"
                                            data-confirm-icon="x-mark">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-danger">Cancel</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <div class="px-6 pb-4">{{ $requests->withQueryString()->links() }}</div>
            @endif
        </div>
    </div>
</div>

@endsection
</x-app-layout>
