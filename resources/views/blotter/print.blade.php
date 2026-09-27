<!DOCTYPE html>
{{--
    Standalone POLICE/BLOTTER CASE SHEET.

    Deliberately outside the app layout: a self-contained A4 sheet with its
    own <style> block and a single window.print() — the only page in the
    system allowed inline CSS/JS (it must print cleanly on its own).
--}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Case Sheet {{ $blotter->case_number }} &middot; Barangay Bidduang</title>
    <link rel="icon" type="image/png" href="{{ asset('images/bidduang-seal.png') }}">

    <style>
        * { box-sizing: border-box; }

        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        body {
            margin: 0;
            background: #e2e8f0;
            color: #0f172a;
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 13px;
            line-height: 1.5;
        }

        /* ── screen-only toolbar ─────────────────────────────────────── */
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            max-width: 8.5in;
            margin: 24px auto 0;
            padding: 0 4px;
            font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
        }

        .toolbar a {
            color: #0369a1;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .toolbar a:hover { text-decoration: underline; }

        .btn {
            border: 0;
            border-radius: 8px;
            background: #0f172a;
            color: #fff;
            cursor: pointer;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            padding: 9px 18px;
        }

        .btn:hover { background: #1e293b; }

        /* ── the sheet ───────────────────────────────────────────────── */
        .sheet {
            width: 100%;
            max-width: 8.5in;
            margin: 16px auto 40px;
            background: #fff;
            box-shadow: 0 12px 32px rgba(15, 23, 42, .14);
            padding: 0.55in 0.6in;
        }

        .letterhead {
            display: flex;
            align-items: center;
            gap: 18px;
            text-align: center;
        }

        .letterhead .seal { width: 74px; height: 74px; flex: 0 0 auto; }

        .lh-text { flex: 1; }

        .letterhead p { margin: 0; }

        .lh-republic {
            font-size: 13px;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .lh-province { font-size: 12px; margin-top: 3px !important; color: #334155; }

        .lh-barangay {
            font-size: 21px;
            font-weight: 700;
            margin-top: 6px !important;
            letter-spacing: .04em;
        }

        .lh-office { font-size: 13px; font-style: italic; color: #334155; }

        .rule { border: 0; border-top: 2px solid #0f172a; margin: 14px 0 4px; }

        .rule-thin { border: 0; border-top: 1px solid #94a3b8; margin: 4px 0 18px; }

        .doc-title {
            margin: 14px 0 2px;
            text-align: center;
            font-size: 17px;
            font-weight: 700;
            letter-spacing: .18em;
            text-transform: uppercase;
        }

        .doc-sub {
            margin: 0 0 18px;
            text-align: center;
            font-size: 13px;
            color: #334155;
        }

        /* ── details table ───────────────────────────────────────────── */
        table.details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        table.details th,
        table.details td {
            border: 1px solid #94a3b8;
            padding: 7px 10px;
            text-align: left;
            vertical-align: top;
        }

        table.details th {
            width: 17%;
            background: #f1f5f9;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        table.details td { width: 33%; }

        .badge {
            display: inline-block;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 10px;
        }

        .badge-amber { background: #fef3c7; color: #92400e; }
        .badge-sky { background: #e0f2fe; color: #075985; }
        .badge-emerald { background: #d1fae5; color: #065f46; }
        .badge-slate { background: #e2e8f0; color: #334155; }

        /* ── party blocks ────────────────────────────────────────────── */
        .parties {
            display: flex;
            gap: 16px;
            margin-bottom: 18px;
        }

        .party {
            flex: 1;
            border: 1px solid #94a3b8;
            padding: 10px 12px;
        }

        .party h2 {
            margin: 0 0 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #334155;
        }

        .party-name { margin: 0; font-size: 15px; font-weight: 700; }

        .party-line { margin: 4px 0 0; font-size: 12px; color: #334155; }

        .party-line span {
            display: inline-block;
            min-width: 62px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #64748b;
        }

        /* ── narrative / resolution boxes ────────────────────────────── */
        .box {
            border: 1px solid #94a3b8;
            padding: 10px 12px;
            margin-bottom: 18px;
        }

        .box h2 {
            margin: 0 0 8px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #334155;
        }

        .box p {
            margin: 0;
            white-space: pre-wrap;
            text-align: justify;
        }

        /* ── signatures ──────────────────────────────────────────────── */
        .signatures {
            display: flex;
            gap: 26px;
            margin-top: 34px;
        }

        .sign { flex: 1; text-align: center; }

        .sign-title { margin: 0 0 34px; font-size: 12px; font-weight: 700; }

        .sign-line { border-top: 1px solid #0f172a; padding-top: 5px; }

        .sign-name { margin: 0; font-size: 13px; font-weight: 700; }

        .sign-role { margin: 1px 0 0; font-size: 11px; color: #475569; }

        /* ── footer ──────────────────────────────────────────────────── */
        .doc-footer {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            border-top: 1px solid #cbd5e1;
            margin-top: 26px;
            padding-top: 8px;
            font-size: 10.5px;
            color: #64748b;
        }

        @page { size: A4; margin: 14mm; }

        @media print {
            body { background: #fff; font-size: 12.5px; }

            .no-print { display: none !important; }

            .sheet {
                margin: 0;
                max-width: none;
                width: auto;
                padding: 0;
                box-shadow: none;
            }

            .box, .party, table.details th, table.details td { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <a href="{{ route('blotter.index') }}">&larr; Back to blotter</a>
        <button type="button" data-print class="btn">Print case sheet</button>
    </div>

    <article class="sheet">
        <header class="letterhead">
            <img class="seal"
                 src="{{ asset('images/bidduang-seal.png') }}"
                 alt="Official seal of Barangay Bidduang"
                 width="74" height="74">
            <div class="lh-text">
                <p class="lh-republic">Republic of the Philippines</p>
                <p class="lh-province">Province of — &middot; Municipality/City of —</p>
                <p class="lh-barangay">Barangay Bidduang</p>
                <p class="lh-office">Office of the Punong Barangay</p>
            </div>
        </header>

        <hr class="rule">
        <hr class="rule-thin">

        <h1 class="doc-title">Police/Blotter Case Sheet</h1>
        <p class="doc-sub">Case No. {{ $blotter->case_number }}</p>

        <table class="details">
            <tbody>
                <tr>
                    <th>Case No.</th>
                    <td>{{ $blotter->case_number }}</td>
                    <th>Incident date</th>
                    <td>{{ $blotter->incident_date?->format('F j, Y') }}</td>
                </tr>
                <tr>
                    <th>Incident time</th>
                    <td>{{ $blotter->incident_time?->format('H:i') ?: 'Not recorded' }}</td>
                    <th>Incident type</th>
                    <td>{{ $blotter->incident_type }}</td>
                </tr>
                <tr>
                    <th>Location</th>
                    <td>{{ $blotter->location }}</td>
                    <th>Purok</th>
                    <td>{{ $blotter->purok?->label() ?? 'Not assigned' }}</td>
                </tr>
                <tr>
                    <th>Arrest made</th>
                    <td>{{ $blotter->arrest_made }}</td>
                    <th>Status</th>
                    <td>
                        @php($badgeClass = match ($blotter->status) {
                            'Open' => 'badge-amber',
                            'Pending' => 'badge-sky',
                            'Resolved' => 'badge-emerald',
                            default => 'badge-slate',
                        })
                        <span class="badge {{ $badgeClass }}">{{ $blotter->status }}</span>
                    </td>
                </tr>
                <tr>
                    <th>Handling officer</th>
                    <td colspan="3">{{ $blotter->handling_officer ?: 'Not yet assigned' }}</td>
                </tr>
                <tr>
                    <th>Recorded by</th>
                    <td colspan="3">
                        {{ $blotter->recorder?->name ?? 'Barangay office' }}
                        on {{ $blotter->created_at->format('F j, Y \a\t H:i') }}
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="parties">
            <section class="party">
                <h2>Complainant</h2>
                <p class="party-name">{{ $blotter->complainant_name }}</p>
                <p class="party-line"><span>Contact</span> {{ $blotter->complainant_contact ?: '—' }}</p>
            </section>

            <section class="party">
                <h2>Respondent</h2>
                <p class="party-name">{{ $blotter->respondent_name ?: 'Unknown / not recorded' }}</p>
                <p class="party-line"><span>Contact</span> {{ $blotter->respondent_contact ?: '—' }}</p>
            </section>
        </div>

        <section class="box">
            <h2>Narrative</h2>
            <p>{{ $blotter->narrative }}</p>
        </section>

        @if (trim((string) $blotter->resolution_notes) !== '')
            <section class="box">
                <h2>Resolution notes</h2>
                <p>{{ $blotter->resolution_notes }}</p>
            </section>
        @endif

        <div class="signatures">
            <div class="sign">
                <p class="sign-title">Received by</p>
                <div class="sign-line">
                    <p class="sign-name">{{ $punong?->full_name ?? '—' }}</p>
                    <p class="sign-role">Punong Barangay</p>
                </div>
            </div>

            <div class="sign">
                <p class="sign-title">Handling officer</p>
                <div class="sign-line">
                    <p class="sign-name">{{ $blotter->handling_officer ?: '—' }}</p>
                    <p class="sign-role">Case handler</p>
                </div>
            </div>

            <div class="sign">
                <p class="sign-title">Complainant</p>
                <div class="sign-line">
                    <p class="sign-name">{{ $blotter->complainant_name }}</p>
                    <p class="sign-role">In person / on record</p>
                </div>
            </div>
        </div>

        <footer class="doc-footer">
            <span>{{ $blotter->case_number }}</span>
            <span>Printed {{ $printedAt->format('M j, Y \a\t H:i') }}</span>
            <span>Barangay Management System</span>
        </footer>
    </article>

    <script>
        document.addEventListener('click', function (event) {
            var trigger = event.target instanceof Element ? event.target.closest('[data-print]') : null;

            if (trigger) {
                event.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>
