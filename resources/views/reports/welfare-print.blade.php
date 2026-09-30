<x-report-print title="Welfare Beneficiaries Report" :subtitle="'Assistance provided through the barangay welfare program' . ($from ? ' · ' . $from->format('M j, Y') . ' – ' . ($to ? $to->format('M j, Y') : now()->format('M j, Y')) : '')" :back-href="route('reports.welfare', array_merge(request()->only(['from', 'to'])))">
    <div class="summary">
        <span>Total requests: <b>{{ $statusTotals->total }}</b></span>
        <span>Approved: <b>{{ $statusTotals->approved }}</b></span>
        <span>Released: <b>{{ $statusTotals->released }}</b></span>
        <span>Amount approved: <b>₱{{ number_format($amounts->approved, 2) }}</b></span>
        <span>Amount released: <b>₱{{ number_format($amounts->released, 2) }}</b></span>
    </div>

    <section>
        <div class="sec-head"><h2>Breakdown by Assistance Type</h2></div>
        @if ($byType->isEmpty())
            <p class="empty">No welfare records for this period.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Assistance Type</th>
                        <th class="num" style="width: 50pt;">Beneficiaries</th>
                        <th class="num" style="width: 80pt;">Approved (₱)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($byType as $type => $row)
                        <tr>
                            <td>{{ $type }}</td>
                            <td class="num">{{ $row->count }}</td>
                            <td class="num">{{ number_format($row->amount, 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td>Total</td>
                        <td class="num">{{ $statusTotals->total }}</td>
                        <td class="num">{{ number_format($amounts->approved, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        @endif
    </section>

    <section>
        <div class="sec-head"><h2>Breakdown by Program</h2></div>
        @if ($byProgram->isEmpty())
            <p class="empty">No welfare records for this period.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Program</th>
                        <th class="num" style="width: 50pt;">Beneficiaries</th>
                        <th class="num" style="width: 80pt;">Approved (₱)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($byProgram as $program => $row)
                        <tr>
                            <td>{{ $program }}</td>
                            <td class="num">{{ $row->count }}</td>
                            <td class="num">{{ number_format($row->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section>
        <div class="sec-head"><h2>Beneficiary List</h2></div>
        @if ($records->isEmpty())
            <p class="empty">No welfare records for this period.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Beneficiary</th>
                        <th style="width: 56pt;">Type</th>
                        <th>Program</th>
                        <th class="num" style="width: 62pt;">Approved (₱)</th>
                        <th style="width: 52pt;">Status</th>
                        <th style="width: 56pt;">Requested</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($records as $record)
                        <tr>
                            <td>{{ $record->beneficiary?->full_name ?? $record->beneficiary_name }}</td>
                            <td>{{ $record->assistance_type }}</td>
                            <td>{{ $record->program_name }}</td>
                            <td class="num">{{ number_format((float) $record->approved_amount, 2) }}</td>
                            <td>{{ $record->status }}</td>
                            <td>{{ $record->request_date->format('M j, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <p class="cert">
        I certify that this welfare beneficiaries report is a true and correct summary of the
        assistance records of Barangay Bidduang for the stated period.
    </p>
</x-report-print>
