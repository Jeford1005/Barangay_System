<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $issuance->document->title }} {{ $issuance->control_number }} — Barangay Management System</title>
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

        /* ---------- Letterhead (mirrors blotter case sheet) ---------- */
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
            margin: 16pt 0 4pt;
            font-size: 15pt;
            font-weight: bold;
            letter-spacing: .2em;
            text-transform: uppercase;
        }
        .doc-sub {
            text-align: center;
            font-size: 9.5pt;
            color: #333;
            margin-bottom: 16pt;
        }

        /* ---------- Control strip ---------- */
        .ctrl {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            border: 1pt solid #000;
            border-left: 4pt solid #000;
            padding: 7pt 10pt;
            font-size: 10.5pt;
            margin-bottom: 16pt;
        }
        .ctrl b { font-size: 12pt; letter-spacing: .05em; }

        /* ---------- Body text ---------- */
        .body { text-align: justify; text-indent: 12mm; margin-bottom: 10pt; }
        .body b.name { text-transform: uppercase; letter-spacing: .03em; }
        .body .fill { border-bottom: 0.75pt dotted #000; padding: 0 6pt; white-space: pre-wrap; }
        .purpose { border: 0.75pt solid #000; padding: 7pt 10pt; text-align: center; font-weight: bold; margin: 12pt 0 4pt; }

        .validity {
            margin-top: 12pt;
            font-size: 9.5pt;
            font-style: italic;
            color: #222;
        }

        /* ---------- Signatures ---------- */
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 26pt 40pt;
            margin-top: 30pt;
            break-inside: avoid;
        }
        .sig { text-align: center; font-size: 10pt; }
        .sig .line { border-top: 0.75pt solid #000; margin-bottom: 3pt; }
        .sig .name { font-weight: bold; }
        .sig .role { font-size: 8.5pt; text-transform: uppercase; letter-spacing: .06em; color: #333; }
        .sig .cell { min-height: 30pt; }

        .fee-note {
            margin-top: 14pt;
            font-size: 8.5pt;
            color: #555;
            font-family: Arial, Helvetica, sans-serif;
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

        .void-stamp {
            position: absolute;
            top: 42%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-18deg);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 64pt;
            font-weight: bold;
            color: rgba(220, 38, 38, .55);
            border: 6pt solid rgba(220, 38, 38, .55);
            padding: 4pt 22pt;
            letter-spacing: .2em;
            pointer-events: none;
        }
        .sheet { position: relative; }

        /* ---------- Toolbar (screen only) ---------- */
        .toolbar {
            max-width: 186mm;
            margin: 0 auto 12px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
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
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" class="primary" onclick="window.print()">Print this certificate</button>
        <a href="{{ route('certificates.index') }}">Back to certificates</a>
    </div>

    <div class="sheet">
        @if ($issuance->status === 'Voided')
            <div class="void-stamp">VOID</div>
        @endif

        <header class="letterhead">
            <img src="{{ asset('images/bidduang-seal.png') }}" alt="Barangay Bidduang official seal">
            <div class="lines">
                <div class="rep">Republic of the Philippines</div>
                <div class="rep">Province of ____________________ · Municipality/City of ____________________</div>
                <div class="brgy">Barangay Bidduang</div>
                <div class="office">Office of the Punong Barangay</div>
            </div>
        </header>

        @php
            $snapshot = $issuance->recipient_snapshot ?? [];
            $currentResident = $issuance->resident;
            $r = (object) [
                'full_name' => $snapshot['full_name'] ?? $currentResident?->full_name,
                'age' => $snapshot['age'] ?? $currentResident?->age,
                'civil_status' => $snapshot['civil_status'] ?? $currentResident?->civil_status,
                'address' => $snapshot['address'] ?? $currentResident?->address,
                'purok' => (($snapshot['purok_name'] ?? $currentResident?->purok?->name) ? (object) ['name' => $snapshot['purok_name'] ?? $currentResident?->purok?->name] : null),
            ];
        @endphp

        @if ($issuance->document->code === 'CLR')
            <h1 class="doc-title">Barangay Clearance</h1>
            <p class="doc-sub">Certification of good standing · No pending case at the barangay level</p>

            <div class="ctrl">
                <span>Control No. <b>{{ $issuance->control_number }}</b></span>
                <span>Issued: <b>{{ $issuance->created_at->format('M j, Y') }}</b></span>
            </div>

            <p class="body">This is to certify that <b class="name">{{ $r->full_name }}</b>,
                {{ $r->age }} years of age,
                {{ $r->civil_status ? strtolower($r->civil_status) : '—' }},
                and a bona fide resident of Barangay Bidduang
                @if ($r->purok)
                    ({{ e($r->purok->name) }})@endif
                @if ($r->address)
                    , with address at {{ e($r->address) }}@endif
                , is known to be of <b>good moral character</b> and a law-abiding citizen of the community.</p>

            <p class="body">This certification further attests that the above-named person has <b>no pending case</b> —
                criminal, civil, or administrative — filed against him/her before this Office and is not included in any
                watch list maintained by this barangay, as of the date of issuance.</p>

        @elseif ($issuance->document->code === 'COR')
            <h1 class="doc-title">Certificate of Residency</h1>
            <p class="doc-sub">Certification of bona fide residency in the barangay</p>

            <div class="ctrl">
                <span>Control No. <b>{{ $issuance->control_number }}</b></span>
                <span>Issued: <b>{{ $issuance->created_at->format('M j, Y') }}</b></span>
            </div>

            <p class="body">This is to certify that <b class="name">{{ $r->full_name }}</b>,
                {{ $r->age }} years of age,
                {{ $r->civil_status ? strtolower($r->civil_status) : '—' }},
                is a <b>bona fide resident</b> of Barangay Bidduang
                @if ($r->purok)
                    , {{ e($r->purok->name) }}@endif
                @if ($r->address)
                    , with address at {{ e($r->address) }}@endif
                , according to the records of this Office.</p>

            <p class="body">This certification is issued upon the request of the interested party for
                <span class="fill">{{ $issuance->purpose }}</span> and for whatever legitimate purpose it may serve.</p>

        @else
            <h1 class="doc-title">Certificate of Indigency</h1>
            <p class="doc-sub">Certification for medical, educational, or legal assistance</p>

            <div class="ctrl">
                <span>Control No. <b>{{ $issuance->control_number }}</b></span>
                <span>Issued: <b>{{ $issuance->created_at->format('M j, Y') }}</b></span>
            </div>

            <p class="body">This is to certify that <b class="name">{{ $r->full_name }}</b>,
                {{ $r->age }} years of age,
                {{ $r->civil_status ? strtolower($r->civil_status) : '—' }},
                residing in Barangay Bidduang
                @if ($r->purok)
                    , {{ e($r->purok->name) }}@endif
                @if ($r->address)
                    , with address at {{ e($r->address) }}@endif
                , belongs to an <b>indigent family</b> as assessed by this Office, and as such may be granted
                assistance from government agencies and private institutions.</p>

            <p class="body">This certification is issued upon the request of the interested party for
                <span class="fill">{{ $issuance->purpose }}</span>.</p>
        @endif

        <p class="purpose">Purpose: {{ $issuance->purpose }}</p>

        @php
            $feeNote = (float) $issuance->fee > 0
                ? '· Issued upon payment of ₱'.number_format((float) $issuance->fee, 2)
                    .((int) $issuance->copies > 1 ? ' for '.$issuance->copies.' copies' : '')
                : '· Issued free of charge';
        @endphp
        <p class="validity">This certificate is valid for six (6) months from the date of issuance
            {{ $feeNote }}.</p>

        @php
            $issuerName = $punongBarangay?->full_name;
        @endphp

        <div class="signatures">
            <div class="sig">
                <div class="cell"></div>
                <div class="line"></div>
                <div class="name">{{ $issuerName ?? '_____________________' }}</div>
                <div class="role">Punong Barangay</div>
            </div>
            <div class="sig">
                <div class="cell"></div>
                <div class="line"></div>
                <div class="name">&nbsp;</div>
                <div class="role">Requested by (signature over printed name)</div>
            </div>
        </div>

        <footer class="printed-note">
            <span>{{ $issuance->control_number }} · Printed {{ now()->format('M j, Y g:i A') }} · Barangay Management System</span>
            <span class="pagenum"></span>
        </footer>
    </div>
</body>
</html>
