<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Case Sheet {{ $blotter->case_number }} — Barangay Management System</title>
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

        /* ---------- Case meta strip ---------- */
        .case-meta {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            border: 1pt solid #000;
            border-left: 4pt solid #000;
            padding: 7pt 10pt;
            font-size: 10.5pt;
            margin-bottom: 14pt;
        }
        .case-meta b { font-size: 12pt; letter-spacing: .05em; }

        /* ---------- Sections ---------- */
        section { margin-bottom: 12pt; break-inside: avoid; }
        h2 {
            font-size: 10pt;
            text-transform: uppercase;
            letter-spacing: .1em;
            border-bottom: 0.75pt solid #000;
            padding-bottom: 2pt;
            margin-bottom: 7pt;
        }

        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5pt 18pt; }
        .grid-3 { grid-template-columns: 1fr 1fr 1fr; }
        .field { border-bottom: 0.5pt dotted #555; padding: 1pt 2pt 2pt; min-height: 15pt; }
        .field .lbl {
            display: block;
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #444;
            font-family: Arial, Helvetica, sans-serif;
        }
        .empty { color: #777; font-style: italic; font-size: 10pt; }

        .narrative {
            border: 0.75pt solid #000;
            padding: 8pt 10pt;
            min-height: 70pt;
            text-align: justify;
            white-space: pre-wrap;
        }

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

        .cert {
            margin-top: 18pt;
            font-size: 9.5pt;
            font-style: italic;
            color: #222;
        }

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
            section, .case-meta, .signatures { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" class="primary" onclick="window.print()">Print this sheet</button>
        <a href="{{ route('blotter.edit', $blotter) }}">Back to case</a>
    </div>

    <div class="sheet">
        <header class="letterhead">
            <img src="{{ asset('images/bidduang-seal.png') }}" alt="Barangay Bidduang official seal">
            <div class="lines">
                <div class="rep">Republic of the Philippines</div>
                <div class="rep">Province of&nbsp;&nbsp;&nbsp;—&nbsp;&nbsp;&nbsp;&nbsp;· Municipality/City of&nbsp;&nbsp;&nbsp;—</div>
                <div class="brgy">Barangay Bidduang</div>
                <div class="office">Office of the Barangay — Blotter &amp; Records Section</div>
            </div>
        </header>

        <h1 class="doc-title">Blotter Case Sheet</h1>
        <p class="doc-sub">Official record of an incident entered into the barangay blotter</p>

        <div class="case-meta">
            <span>Case No. <b>{{ $blotter->case_number }}</b></span>
            <span>Status: <b>{{ $blotter->status }}</b></span>
            <span>Recorded: <b>{{ $blotter->created_at?->format('M j, Y') ?? '—' }}</b></span>
        </div>

        <section>
            <h2>I. Complainant</h2>
            <div class="grid">
                <span class="field"><span class="lbl">Full Name</span>{{ $blotter->complainant_name }}</span>
                <span class="field"><span class="lbl">Registered Resident</span>
                    @if ($blotter->complainant)
                        {{ $blotter->complainant->full_name }}
                    @else
                        <span class="empty">Walk-in / not registered</span>
                    @endif
                </span>
                <span class="field"><span class="lbl">Address</span>@if (filled($blotter->complainant_address)){{ $blotter->complainant_address }}@else<span class="empty">Not provided</span>@endif</span>
                <span class="field"><span class="lbl">Contact No.</span>@if (filled($blotter->complainant_phone)){{ $blotter->complainant_phone }}@else<span class="empty">Not provided</span>@endif</span>
            </div>
        </section>

        <section>
            <h2>II. Respondent (Accused)</h2>
            <div class="grid">
                <span class="field"><span class="lbl">Full Name</span>{{ $blotter->accused_name }}</span>
                <span class="field"><span class="lbl">Registered Resident</span>
                    @if ($blotter->accused)
                        {{ $blotter->accused->full_name }}
                    @else
                        <span class="empty">Walk-in / not registered</span>
                    @endif
                </span>
                <span class="field"><span class="lbl">Address</span>@if (filled($blotter->accused_address)){{ $blotter->accused_address }}@else<span class="empty">Not provided</span>@endif</span>
                <span class="field"><span class="lbl">Contact No.</span>@if (filled($blotter->accused_phone)){{ $blotter->accused_phone }}@else<span class="empty">Not provided</span>@endif</span>
            </div>
        </section>

        <section>
            <h2>III. Incident Details</h2>
            <div class="grid grid-3">
                <span class="field"><span class="lbl">Complaint Type</span>{{ $blotter->complaint_type }}@if (filled($blotter->complaint_subtype)) — {{ $blotter->complaint_subtype }}@endif</span>
                <span class="field"><span class="lbl">Date of Incident</span>{{ $blotter->complaint_date->format('F j, Y') }}</span>
                <span class="field"><span class="lbl">Time</span>@if ($blotter->complaint_time){{ $blotter->complaint_time->format('g:i A') }}@else<span class="empty">Not recorded</span>@endif</span>
            </div>
            <div class="grid" style="margin-top: 5pt;">
                <span class="field"><span class="lbl">Arrest Made</span>{{ $blotter->arrest_made }}</span>
                <span class="field"><span class="lbl">Investigator</span>@if (filled($blotter->investigator)){{ $blotter->investigator }}@else<span class="empty">None assigned</span>@endif</span>
            </div>
        </section>

        <section>
            <h2>IV. Alleged Offense / Narrative of the Incident</h2>
            <div class="narrative">{{ $blotter->alleged_offense }}</div>
        </section>

        <section>
            <h2>V. Disposition / Action Taken</h2>
            @if (filled($blotter->disposition))
                <div class="narrative" style="min-height: 50pt;">{{ $blotter->disposition }}@if ($blotter->disposition_date)&nbsp;&nbsp;—&nbsp;<i>dated {{ $blotter->disposition_date->format('F j, Y') }}</i>@endif</div>
            @else
                <div class="narrative" style="min-height: 50pt;"><span class="empty">Pending — no disposition recorded as of this printing.</span></div>
            @endif
        </section>

        <section>
            <h2>VI. Handling</h2>
            <div class="grid">
                <span class="field"><span class="lbl">Handling Officer</span>
                    @if ($blotter->officer)
                        {{ $blotter->officer->full_name }}@if ($blotter->officer->position) — {{ $blotter->officer->position }}@endif
                    @else
                        <span class="empty">None assigned</span>
                    @endif
                </span>
                <span class="field"><span class="lbl">Encoded By</span>{{ $blotter->creator?->email ?? 'Unknown' }}</span>
            </div>
            @if (filled($blotter->remarks))
                <div class="grid" style="margin-top: 5pt;">
                    <span class="field"><span class="lbl">Remarks</span>{{ $blotter->remarks }}</span>
                </div>
            @endif
        </section>

        <p class="cert">
            I certify that the foregoing is a true and correct extract of the entries
            appearing in the official barangay blotter for Case No. {{ $blotter->case_number }}.
        </p>

        <div class="signatures">
            <div class="sig">
                <div class="line"></div>
                @if ($blotter->officer)
                    <div class="name">{{ $blotter->officer->full_name }}</div>
                    <div class="role">Handling Officer</div>
                @else
                    <div class="name">&nbsp;</div>
                    <div class="role">Prepared by (name &amp; signature)</div>
                @endif
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
