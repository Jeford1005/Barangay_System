<x-report-print title="Population Report" :subtitle="'Active residents by purok, sex, and age bracket' . ($to ? ' · as of ' . $to->format('F j, Y') : '')" :back-href="route('reports.population', array_merge(request()->only(['from', 'to'])))">
    <div class="summary">
        <span>Total active residents: <b>{{ $totals->total }}</b></span>
        <span>Male: <b>{{ $totals->male }}</b></span>
        <span>Female: <b>{{ $totals->female }}</b></span>
        <span>Registered voters: <b>{{ $voters }}</b></span>
        <span>Period: <b>{{ $from?->format('M j, Y') ?? 'the beginning' }} → {{ $to?->format('M j, Y') ?? now()->format('M j, Y') }}</b></span>
    </div>

    <section>
        <div class="sec-head"><h2>Population by Purok and Age Bracket</h2></div>
        <table>
            <thead>
                <tr>
                    <th>Purok</th>
                    @foreach ($brackets as $bracket)
                        <th class="num" style="width: 52pt;">{{ $bracket['label'] }}</th>
                    @endforeach
                    <th class="num" style="width: 40pt;">Male</th>
                    <th class="num" style="width: 44pt;">Female</th>
                    <th class="num" style="width: 40pt;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row->label }}</td>
                        @foreach ($brackets as $bracket)
                            <td class="num">{{ $row->brackets[$bracket['label']] }}</td>
                        @endforeach
                        <td class="num">{{ $row->male }}</td>
                        <td class="num">{{ $row->female }}</td>
                        <td class="num">{{ $row->total }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td>Total</td>
                    @foreach ($brackets as $bracket)
                        <td class="num">{{ $totals->brackets[$bracket['label']] }}</td>
                    @endforeach
                    <td class="num">{{ $totals->male }}</td>
                    <td class="num">{{ $totals->female }}</td>
                    <td class="num">{{ $totals->total }}</td>
                </tr>
            </tbody>
        </table>
        <p class="note">Age brackets follow the standard barangay planning groups: child (0–6), minor (7–17), youth (18–30), adult (31–45), middle-aged (46–59), senior citizen (60+).</p>
    </section>

    <p class="cert">
        I certify that this population report is a true and correct summary of the active resident
        records of Barangay Bidduang as recorded in the Barangay Management System
        @if ($to)
            as of {{ $to->format('F j, Y') }}
        @endif
        .
    </p>
</x-report-print>
