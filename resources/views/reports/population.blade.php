<x-app-layout>
@section('page_header')
    <x-page-header title="Population Report" subtitle="{{ $totals->total }} active residents · {{ $voters }} registered voter{{ $voters === 1 ? '' : 's' }}" />
@endsection

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="bg-white rounded-xl shadow overflow-hidden">

        <div class="p-6">
            <form method="GET" action="{{ route('reports.population') }}" class="no-print mb-6 flex flex-col gap-2 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="population-from" class="block text-xs font-medium text-slate-500 uppercase tracking-wide">Registered from</label>
                    <input id="population-from" type="date" name="from" value="{{ $from?->toDateString() }}" max="{{ now()->toDateString() }}" onchange="this.form.submit()"
                        class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-600">
                </div>
                <div class="flex-1">
                    <label for="population-to" class="block text-xs font-medium text-slate-500 uppercase tracking-wide">Registered to</label>
                    <input id="population-to" type="date" name="to" value="{{ $to?->toDateString() }}" max="{{ now()->toDateString() }}" onchange="this.form.submit()"
                        class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-600">
                </div>
                <noscript><button type="submit" class="btn btn-neutral">Filter</button></noscript>
                <a href="{{ route('reports.population') }}" class="btn btn-ghost">Reset</a>
                <a href="{{ route('reports.population', array_merge(request()->only(['from', 'to']), ['print' => 1])) }}"
                     class="btn btn-outline">
                     Print / PDF
                 </a>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">Purok</th>
                            @foreach ($brackets as $bracket)
                                <th class="px-3 py-3 text-right text-xs font-medium text-slate-500 uppercase hidden print:table-cell md:table-cell">{{ $bracket['label'] }}</th>
                            @endforeach
                            <th class="px-3 py-3 text-right text-xs font-medium text-slate-500 uppercase">Male</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-slate-500 uppercase">Female</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-slate-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($rows as $row)
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-3 font-medium text-slate-900">{{ $row->label }}</td>
                                @foreach ($brackets as $bracket)
                                    <td class="px-3 py-3 text-right text-slate-600 hidden print:table-cell md:table-cell">{{ $row->brackets[$bracket['label']] }}</td>
                                @endforeach
                                <td class="px-3 py-3 text-right text-slate-600">{{ $row->male }}</td>
                                <td class="px-3 py-3 text-right text-slate-600">{{ $row->female }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-slate-900">{{ $row->total }}</td>
                            </tr>
                        @endforeach
                        <tr class="bg-slate-50 font-semibold">
                            <td class="px-3 py-3">Total</td>
                            @foreach ($brackets as $bracket)
                                <td class="px-3 py-3 text-right hidden print:table-cell md:table-cell">{{ $totals->brackets[$bracket['label']] }}</td>
                            @endforeach
                            <td class="px-3 py-3 text-right">{{ $totals->male }}</td>
                            <td class="px-3 py-3 text-right">{{ $totals->female }}</td>
                            <td class="px-3 py-3 text-right">{{ $totals->total }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @if ($from || $to)
                <p class="mt-3 text-xs text-slate-500">Filtering by registration date: {{ $from?->format('M j, Y') ?? 'the beginning' }} → {{ $to?->format('M j, Y') ?? 'today' }}.</p>
            @endif
        </div>
    </div>
</div>
@endsection
</x-app-layout>
