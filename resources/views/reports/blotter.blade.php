<x-app-layout>
@section('page_header')
    <x-page-header title="Blotter Summary" subtitle="{{ $statusTotals->total }} case{{ $statusTotals->total === 1 ? '' : 's' }} · {{ $statusTotals->open }} open · {{ $statusTotals->arrests }} with arrest" />
@endsection

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="bg-white rounded-xl shadow overflow-hidden">

        <div class="p-6">
            <form method="GET" action="{{ route('reports.blotter') }}" class="no-print mb-6 flex flex-col gap-2 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="blotter-report-from" class="block text-xs font-medium text-slate-500 uppercase tracking-wide">Incident from</label>
                    <input id="blotter-report-from" type="date" name="from" value="{{ $from?->toDateString() }}" max="{{ now()->toDateString() }}" onchange="this.form.submit()"
                        class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-600">
                </div>
                <div class="flex-1">
                    <label for="blotter-report-to" class="block text-xs font-medium text-slate-500 uppercase tracking-wide">Incident to</label>
                    <input id="blotter-report-to" type="date" name="to" value="{{ $to?->toDateString() }}" max="{{ now()->toDateString() }}" onchange="this.form.submit()"
                        class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-600">
                </div>
                <noscript><button type="submit" class="btn btn-neutral">Filter</button></noscript>
                <a href="{{ route('reports.blotter') }}" class="btn btn-ghost">Reset</a>
                <a href="{{ route('reports.blotter', array_merge(request()->only(['from', 'to']), ['print' => 1])) }}"
                     class="btn btn-outline">
                     Print / PDF
                 </a>
            </form>

            <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
                @foreach ([['Open', $statusTotals->open, 'text-red-600'], ['Pending', $statusTotals->pending, 'text-amber-600'], ['Resolved', $statusTotals->resolved, 'text-emerald-600'], ['Dismissed', $statusTotals->dismissed, 'text-slate-500'], ['Arrests', $statusTotals->arrests, 'text-sky-700']] as [$label, $value, $color])
                    <div class="rounded-lg border border-slate-200 p-4">
                        <p class="text-xs font-medium text-slate-500 uppercase">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-bold {{ $color }}">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            <div class="overflow-x-auto mb-6">
                <h3 class="text-sm font-semibold text-slate-900 mb-2">Cases by Complaint Type</h3>
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">Complaint Type</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-slate-500 uppercase">Total</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-slate-500 uppercase">Open</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-slate-500 uppercase">Pending</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-slate-500 uppercase">Resolved</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-slate-500 uppercase">Dismissed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($byType as $type => $row)
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-3 font-medium text-slate-900">{{ $type }}</td>
                                <td class="px-3 py-3 text-right font-semibold">{{ $row->count }}</td>
                                <td class="px-3 py-3 text-right text-slate-600">{{ $row->open }}</td>
                                <td class="px-3 py-3 text-right text-slate-600">{{ $row->pending }}</td>
                                <td class="px-3 py-3 text-right text-slate-600">{{ $row->resolved }}</td>
                                <td class="px-3 py-3 text-right text-slate-600">{{ $row->dismissed }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">No cases in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="overflow-x-auto mb-6">
                <h3 class="text-sm font-semibold text-slate-900 mb-2">Monthly Trend</h3>
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            @foreach ($months as $month => $count)
                                <th class="px-3 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ $month }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            @foreach ($months as $count)
                                <td class="px-3 py-3 text-right font-semibold {{ $count > 0 ? 'text-slate-900' : 'text-slate-300' }}">{{ $count }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>

            @if ($recent->isNotEmpty())
                <div class="overflow-x-auto">
                    <h3 class="text-sm font-semibold text-slate-900 mb-2">Most Recent Cases</h3>
                    <ul class="divide-y divide-slate-200 text-sm">
                        @foreach ($recent as $case)
                            <li class="py-2 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                <span class="font-medium text-slate-900">{{ $case->case_number }} — {{ $case->complaint_type }}</span>
                                <span class="text-slate-500 text-xs">{{ $case->complaint_date?->format('M j, Y') ?? '—' }} · {{ $case->status }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
</x-app-layout>
