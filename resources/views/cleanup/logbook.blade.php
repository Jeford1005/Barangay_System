<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Logbook — {{ $drive->title }} — Barangay Management System</title>
    <style>
        /* Self-contained print stylesheet: this page never loads the app
           bundle, so the official form is immune to app styling changes. */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Times New Roman', Georgia, serif;
            font-size: 11.5pt;
            line-height: 1.45;
            color: #000;
            background: #f0f0f0;
            padding: 16px;
        }

        .sheet {
            max-width: 186mm; /* A4 width minus 2×12mm margins */
            margin: 0 auto;
            background: #fff;
            padding: 18mm 16mm;
            box-shadow: 0 1px 4px rgba(0,0,0,.25);
            /* Fixed pt widths are tuned for A4 and cannot shrink below their
               content. Scroll inside the sheet rather than letting the page
               scroll sideways - NFR7 asks for no page-level horizontal
               overflow at 375-614px. */
            overflow-x: auto;
        }

        /* A 16mm margin is a third of a 375px screen, so lay the sheet almost
           edge-to-edge on a phone. Larger screens and print are untouched. */
        @media screen and (max-width: 640px) {
            body { padding: 8px; }
            .sheet { padding: 10px; }
        }

        /* ---------- Letterhead ---------- */
        .letterhead {
            display: flex;
            align-items: center;
            gap: 14px;
            border-bottom: 2.5pt double #000;
            padding-bottom: 10pt;
        }
        .letterhead img { width: 68px; height: 68px; object-fit: contain; }
        .letterhead .lines { flex: 1; text-align: center; line-height: 1.3; }
        .letterhead .rep  { font-size: 9.5pt; letter-spacing: .08em; }
        .letterhead .brgy { font-size: 15pt; font-weight: bold; letter-spacing: .14em; text-transform: uppercase; }
        .letterhead .office { font-size: 10pt; font-style: italic; }

        .doc-title {
            text-align: center;
            margin: 14pt 0 4pt;
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: .22em;
            text-transform: uppercase;
        }
        .doc-sub {
            text-align: center;
            font-size: 9.5pt;
            color: #333;
            margin-bottom: 12pt;
        }

        /* ---------- Drive meta strip ---------- */
        .drive-meta {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px 12px;
            border: 1pt solid #000;
            border-left: 4pt solid #000;
            padding: 7pt 10pt;
            font-size: 10.5pt;
            margin-bottom: 14pt;
        }
        .drive-meta b { font-size: 11.5pt; }

        /* ---------- Roster ---------- */
        h2 {
            font-size: 10pt;
            text-transform: uppercase;
            letter-spacing: .1em;
            border-bottom: 0.75pt solid #000;
            padding-bottom: 2pt;
            margin-bottom: 7pt;
        }
        section { margin-bottom: 12pt; }

        table.roster {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
        }
        table.roster th, table.roster td {
            border: 0.5pt solid #555;
            padding: 4pt 6pt;
            text-align: left;
        }
        table.roster thead th {
            background: #eee;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-family: Arial, Helvetica, sans-serif;
        }
        table.roster td.num, table.roster th.num { text-align: center; width: 28pt; }
        table.roster td.hours, table.roster th.hours { text-align: right; width: 44pt; }
        .empty { color: #777; font-style: italic; font-size: 10pt; }

        .capped-note {
            margin-top: 6pt;
            font-size: 9pt;
            font-style: italic;
            color: #333;
            font-family: Arial, Helvetica, sans-serif;
        }

        /* ---------- QR block ----------
           Fixed 96px QR + text column; flex row fits well inside the
           186mm sheet and never forces A4 sideways. Renders client-side
           from the vendored offline script (public/js/qrcode.js); when
           JS is unavailable the bordered placeholder box and the printed
           URL below still let anyone reach the public sheet. */
        .verify {
            display: flex;
            gap: 12pt;
            align-items: center;
            border: 0.75pt solid #000;
            padding: 8pt 10pt;
            margin-top: 12pt;
            break-inside: avoid;
        }
        .qr {
            width: 96px;
            height: 96px;
            min-width: 96px;
            border: 0.5pt solid #999;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7pt;
            color: #555;
        }
        .qr img, .qr canvas, .qr table { width: 96px !important; height: 96px !important; }
        .verify-text {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            line-height: 1.5;
            color: #222;
            word-break: break-all;
        }
        .verify-text strong { font-size: 9pt; }

        /* ---------- Signatures ---------- */
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 26pt 40pt;
            margin-top: 26pt;
            break-inside: avoid;
        }
        .sig { text-align: center; font-size: 10pt; }
        .sig .line { border-top: 0.75pt solid #000; margin-bottom: 3pt; }
        .sig .name { font-weight: bold; }
        .sig .role { font-size: 8.5pt; text-transform: uppercase; letter-spacing: .06em; color: #333; }

        .printed-note {
            margin-top: 14pt;
            font-size: 8pt;
            color: #555;
            display: flex;
            justify-content: space-between;
            border-top: 0.5pt solid #999;
            padding-top: 4pt;
            font-family: Arial, Helvetica, sans-serif;
        }

        /* ---------- Toolbar (screen only) ---------- */
        .toolbar {
            max-width: 186mm;
            margin: 0 auto 12px;
            display: flex;
            gap: 8px;
        }
        .toolbar button, .toolbar a {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            padding: 9px 16px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #1d4ed8;
            text-decoration: none;
            cursor: pointer;
            min-height: 44px;
            display: inline-flex;
            align-items: center;
        }
        .toolbar .primary { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }

        /* ---------- Print ---------- */
        @page { size: A4 portrait; margin: 12mm; }

        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; padding: 0; max-width: none; overflow: visible; }
            .toolbar { display: none !important; }
            .printed-note .pagenum::after { content: 'Page ' counter(page); }
            section, .drive-meta, .signatures, .verify { break-inside: avoid; }
            table.roster tr { break-inside: avoid; }
        }
    </style>
</head>
<body>
    @php
        // Defensive defaults: the controller passes a capped $roster plus the
        // full $totalParticipants, but the sheet still renders with just $drive.
        $roster = isset($roster) ? $roster : ($drive->participants ?? collect());
        $total = isset($totalParticipants) ? (int) $totalParticipants : (is_countable($roster) ? count($roster) : 0);
        $shown = is_countable($roster) ? count($roster) : 0;
    @endphp
    <div class="toolbar no-print">
        <button type="button" class="primary" onclick="window.print()">Print this logbook</button>
        <a href="{{ route('cleanup.index') }}">Back to cleanup drives</a>
    </div>

    @if (isset($residents))
        <div class="walkin no-print">
            <form method="POST" action="{{ route('cleanup.join', $drive) }}">
                @csrf
                <label for="walkin-resident">Sign up walk-in volunteer</label>
                <select id="walkin-resident" name="resident_id" required>
                    <option value="">Select resident…</option>
                    @foreach ($residents as $r)
                        <option value="{{ $r->id }}">{{ $r->full_name }}</option>
                    @endforeach
                </select>
                @if (! empty($residentsCapped))
                    <p>Showing the first 1000 residents — narrow via Residents directory if missing.</p>
                @endif
                <button type="submit" class="primary">Sign up walk-in</button>
            </form>
            @error('resident_id')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>
    @endif

    <div class="sheet">
        <header class="letterhead">
            <img src="{{ asset('images/bidduang-seal.png') }}" alt="Barangay Bidduang official seal">
            <div class="lines">
                <div class="rep">Republic of the Philippines</div>
                <div class="rep">Province of ____________________ · Municipality/City of ____________________</div>
                <div class="brgy">Barangay Bidduang</div>
                <div class="office">Office of the Punong Barangay — Cleanup Drive Logbook</div>
            </div>
        </header>

        <h1 class="doc-title">Cleanup Logbook</h1>
        <p class="doc-sub">Official attendance record of a barangay cleanup drive</p>

        <div class="drive-meta">
            <span>Drive: <b>{{ $drive->title }}</b></span>
            <span>Purok: <b>{{ $drive->purok?->name ?? 'Barangay-wide' }}</b></span>
            <span>Scheduled: <b>{{ $drive->scheduled_at?->format('M j, Y g:i A') ?? '—' }}</b></span>
            <span>Status: <b>{{ $drive->status }}</b></span>
        </div>

        <section>
            <h2>Participant Roster</h2>
            @if ($shown === 0)
                <p class="empty">No sign-ups recorded for this drive as of this printing.</p>
            @else
                <table class="roster">
                    <thead>
                        <tr>
                            <th scope="col" class="num">#</th>
                            <th scope="col">Name</th>
                            <th scope="col">Purok</th>
                            <th scope="col">Attended</th>
                            <th scope="col" class="hours">Hours</th>
                            <th scope="col">Checked-in</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roster as $row)
                            <tr>
                                <td class="num">{{ $loop->iteration }}</td>
                                <td>{{ $row->resident?->full_name ?? '—' }}</td>
                                <td>{{ $row->resident?->purok?->name ?? '—' }}</td>
                                <td>{{ $row->attended ? 'Yes' : 'No' }}</td>
                                <td class="hours">{{ $row->hours !== null ? number_format((float) $row->hours, 1) : '—' }}</td>
                                <td>{{ $row->checked_in_at?->format('M j, Y g:i A') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($total > $shown)
                    <p class="capped-note">Showing {{ $shown }} of {{ $total }} participants — the full roster is on file at the barangay office.</p>
                @endif
            @endif
            @if (filled($drive->description))
                <p class="capped-note">Drive notes: {{ $drive->description }}</p>
            @endif
        </section>

        @isset($sheetUrl)
            <div class="verify">
                <div class="qr" id="drive-qr" data-sheet-url="{{ $sheetUrl }}" role="img" aria-label="QR code linking to the public sheet for {{ $drive->title }}">
                    <span>Scan for public sheet</span>
                </div>
                <div class="verify-text">
                    <strong>Public drive sheet</strong><br>
                    Scan the QR code or visit:<br>
                    {{ $sheetUrl }}<br>
                    This link is signed and expires — it shows the drive details only, never volunteer contact details.
                </div>
            </div>
            <script src="{{ asset('js/qrcode.js') }}"></script>
            <script>
                (function () {
                    var el = document.getElementById('drive-qr');
                    if (!el || typeof QRCode === 'undefined') return;
                    var url = el.getAttribute('data-sheet-url');
                    el.innerHTML = '';
                    new QRCode(el, { text: url, width: 96, height: 96, correctLevel: QRCode.CorrectLevel.M });
                })();
            </script>
        @endisset

        <div class="signatures">
            <div class="sig">
                <div class="line"></div>
                <div class="name">&nbsp;</div>
                <div class="role">Team leader (signature over printed name)</div>
            </div>
            <div class="sig">
                <div class="line"></div>
                <div class="name">&nbsp;</div>
                <div class="role">Punong Barangay</div>
            </div>
        </div>

        <footer class="printed-note">
            <span>Printed {{ now()->format('M j, Y g:i A') }} · Barangay Management System</span>
            <span class="pagenum"></span>
        </footer>
    </div>
</body>
</html>
