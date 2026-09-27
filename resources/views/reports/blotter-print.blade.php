<x-report-print title="Blotter Summary Report" :subtitle="'Case volume and disposition for the period' . ($from ? ' · ' . $from->format('M j, Y') . ' – ' . ($to ? $to->format('M j, Y') : now()->format('M j, Y')) : '')" :back-href="route('reports.blotter', array_merge(request()->only(['from', 'to'])))">
    <div class="summary">
        <span>Total cases: <b>{{ $statusTotals->total }}</b></span>
        <span>Open: <b>{{ $statusTotals->open }}</b></span>
        <span>Pending: <b>{{ $statusTotals->pending }}</b></span>
        <span>Resolved: <b>{{ $statusTotals->resolved }}</b></span>
        <span>Dismissed: <b>{{ $statusTotals->dismissed }}</b></span>
        <span>Arrests made: <b>{{ $statusTotals->arrests }}</b></span>
    </div>

    <section>
        <div class="sec-head"><h2>Cases by Complaint Type</h2></div>
        @if ($byType->isEmpty())
            <p class="empty">No blotter cases recorded for this period.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Complaint Type</th>
                        <th class="num" style="width: 40pt;">Total</th>
                        <th class="num" style="width: 40pt;">Open</th>
                        <th class="num" style="width: 44pt;">Pending</th>
                        <th class="num" style="width: 48pt;">Resolved</th>
                        <th class="num" style="width: 52pt;">Dismissed</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($byType as $type => $row)
                        <tr>
                            <td>{{ $type }}</td>
                            <td class="num">{{ $row->count }}</td>
                            <td class="num">{{ $row->open }}</td>
                            <td class="num">{{ $row->pending }}</td>
                            <td class="num">{{ $row->resolved }}</td>
                            <td class="num">{{ $row->dismissed }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td>Total</td>
                        <td class="num">{{ $statusTotals->total }}</td>
                        <td class="num">{{ $statusTotals->open }}</td>
                        <td class="num">{{ $statusTotals->pending }}</td>
                        <td class="num">{{ $statusTotals->resolved }}</td>
                        <td class="num">{{ $statusTotals->dismissed }}</td>
                    </tr>
                </tbody>
            </table>
        @endif
    </section>

    <section>
        <div class="sec-head"><h2>Monthly Trend</h2></div>
        <table>
            <thead>
                <tr>
                    @foreach ($months as $month => $count)
                        <th class="num">{{ $month }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    @foreach ($months as $count)
                        <td class="num">{{ $count }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </section>

    @if ($recent->isNotEmpty())
        <section>
            <div class="sec-head"><h2>Most Recent Cases</h2></div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 90pt;">Case No.</th>
                        <th>Complaint</th>
                        <th style="width: 62pt;">Date</th>
                        <th style="width: 52pt;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recent as $case)
                        <tr>
                            <td>{{ $case->case_number }}</td>
                            <td>{{ Str::limit($case->complaint_type, 60) }}</td>
                            <td>{{ $case->complaint_date->format('M j, Y') }}</td>
                            <td>{{ $case->status }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    <p class="cert">
        I certify that this blotter summary is a true and correct extract of the official
        barangay blotter records for the stated period.
    </p>
</x-report-print>
