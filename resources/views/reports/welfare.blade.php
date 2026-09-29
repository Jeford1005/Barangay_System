<x-app-layout>
@section('page_header')
    <x-page-header title="Welfare Beneficiaries" subtitle="{{ $statusTotals->total }} request{{ $statusTotals->total === 1 ? '' : 's' }} · ₱{{ number_format($amounts->approved, 2) }} approved · ₱{{ number_format($amounts->released, 2) }} released" />
@endsection

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="bg-white rounded-xl shadow overflow-hidden">

        <div class="p-6">
            <form method="GET" action="{{ route('reports.welfare') }}" class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="welfare-report-from" class="block text-xs font-medium text-slate-500 uppercase tracking-wide">Requested from</label>
                    <input id="welfare-report-from" type="date" name="from" value="{{ $from?->toDateString() }}" max="{{ now()->toDateString() }}"
                        class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-600">
                </div>
                <div class="flex-1">
                    <label for="welfare-report-to" class="block text-xs font-medium text-slate-500 uppercase tracking-wide">Requested to</label>
                    <input id="welfare-report-to" type="date" name="to" value="{{ $to?->toDateString() }}" max="{{ now()->toDateString() }}"
                        class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-600">
                </div>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Filter</button>
                <a href="{{ route('reports.welfare') }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-transparent px-3 py-2 text-sm text-slate-500 hover:text-slate-800">Reset</a>
                <a href="{{ route('reports.welfare', array_merge(request()->only(['from', 'to']), ['print' => 1])) }}"
                     class="inline-flex min-h-11 items-center justify-center rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-sm font-semibold text-sky-700 hover:bg-sky-100 focus:outline-none focus:ring-2 focus:ring-sky-600">
                     Print / PDF
                 </a>
            </form>

            <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
                @foreach ([['Requested', $statusTotals->requested], ['Under Review', $statusTotals->review], ['Approved', $statusTotals->approved], ['Released', $statusTotals->released], ['Denied', $statusTotals->denied]] as [$label, $value])
                    <div class="rounded-lg border border-slate-200 p-4">
                        <p class="text-xs font-medium text-slate-500 uppercase">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="overflow-x-auto">
                    <h3 class="text-sm font-semibold text-slate-900 mb-2">By Assistance Type</h3>
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">Type</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-slate-500 uppercase">Count</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-slate-500 uppercase">Approved (₱)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse ($byType as $type => $row)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-3 font-medium text-slate-900">{{ $type }}</td>
                                    <td class="px-3 py-3 text-right">{{ $row->count }}</td>
                                    <td class="px-3 py-3 text-right">{{ number_format($row->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-3 py-6 text-center text-slate-500">No records in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="overflow-x-auto">
                    <h3 class="text-sm font-semibold text-slate-900 mb-2">By Program</h3>
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">Program</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-slate-500 uppercase">Count</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-slate-500 uppercase">Approved (₱)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse ($byProgram as $program => $row)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-3 font-medium text-slate-900">{{ $program }}</td>
                                    <td class="px-3 py-3 text-right">{{ $row->count }}</td>
                                    <td class="px-3 py-3 text-right">{{ number_format($row->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-3 py-6 text-center text-slate-500">No records in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="overflow-x-auto">
                <h3 class="text-sm font-semibold text-slate-900 mb-2">Beneficiary List</h3>
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">Beneficiary</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">Assistance</th>
                            <th class="hidden md:table-cell px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">Program</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-slate-500 uppercase">Approved (₱)</th>
                            <th class="hidden md:table-cell px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">Requested</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($records as $record)
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-3">
                                    <span class="font-medium text-slate-900">{{ $record->beneficiary?->full_name ?? $record->beneficiary_name }}</span>
                                    @if ($record->beneficiary?->purok)
                                        <span class="block text-xs text-slate-500">{{ $record->beneficiary->purok->name }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-slate-600">{{ $record->assistance_type }}</td>
                                <td class="hidden md:table-cell px-3 py-3 text-slate-600">{{ $record->program_name }}</td>
                                <td class="px-3 py-3 text-right font-medium">{{ number_format((float) $record->approved_amount, 2) }}</td>
                                <td class="hidden md:table-cell px-3 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ ['Released' => 'bg-emerald-100 text-emerald-800', 'Approved' => 'bg-sky-100 text-sky-800', 'Denied' => 'bg-red-100 text-red-800'][$record->status] ?? 'bg-slate-100 text-slate-600' }}">{{ $record->status }}</span>
                                </td>
                                <td class="px-3 py-3 text-slate-600">{{ $record->request_date->format('M j, Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">No welfare requests in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
</x-app-layout>
