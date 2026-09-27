@props([
    'title',
    'subtitle' => null,
    'backHref' => null,
    'backLabel' => 'Back to reports',
])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — Barangay Management System</title>
    <style>
        /* Self-contained print stylesheet shared by all reports:
           independent of app styling. Mirrors the directory/certificate
           official-form pattern. */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Times New Roman', Georgia, serif;
            font-size: 10.5pt;
            line-height: 1.4;
            color: #000;
            background: #f0f0f0;
            padding: 16px;
        }

        .sheet {
            max-width: 186mm;
            margin: 0 auto;
            background: #fff;
            padding: 16mm 14mm;
            box-shadow: 0 1px 4px rgba(0,0,0,.25);
        }

        /* ---------- Letterhead ---------- */
        .letterhead {
            display: flex;
            align-items: center;
            gap: 14px;
            border-bottom: 2.5pt double #000;
            padding-bottom: 10pt;
        }
        .letterhead img { width: 64px; height: 64px; object-fit: contain; }
        .letterhead .lines { flex: 1; text-align: center; line-height: 1.3; }
        .letterhead .rep  { font-size: 9.5pt; letter-spacing: .08em; }
        .letterhead .brgy { font-size: 15pt; font-weight: bold; letter-spacing: .14em; text-transform: uppercase; }
        .letterhead .office { font-size: 10pt; font-style: italic; }

        .doc-title {
            text-align: center;
            margin: 13pt 0 3pt;
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: .2em;
            text-transform: uppercase;
        }
        .doc-sub {
            text-align: center;
            font-size: 9.5pt;
            color: #333;
            margin-bottom: 10pt;
        }

        /* ---------- Summary strip ---------- */
        .summary {
            display: flex;
            flex-wrap: wrap;
            gap: 6pt 18pt;
            border: 1pt solid #000;
            border-left: 4pt solid #000;
            padding: 7pt 10pt;
            font-size: 10pt;
            margin-bottom: 14pt;
        }
        .summary b { font-size: 11pt; }

        section { margin-bottom: 13pt; }
        .sec-head {
            border-bottom: 0.75pt solid #000;
            padding-bottom: 2pt;
            margin-bottom: 6pt;
        }
        .sec-head h2 {
            font-size: 11pt;
            text-transform: uppercase;
            letter-spacing: .09em;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
        }
        th, td {
            border: 0.5pt solid #444;
            padding: 3.5pt 6pt;
            text-align: left;
            vertical-align: top;
        }
        th {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: .07em;
            background: #eee;
        }
        td.num, th.num { text-align: right; }
        td.num { width: 40pt; color: #222; }
        tr.total td { font-weight: bold; background: #f5f5f5; }
        .empty { color: #777; font-style: italic; }

        .note {
            margin-top: 10pt;
            font-size: 9.5pt;
            font-style: italic;
            color: #222;
        }

        .cert {
            margin-top: 20pt;
            font-size: 9.5pt;
            font-style: italic;
            color: #222;
        }
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 26pt 40pt;
            margin-top: 26pt;
            break-inside: avoid;
        }
        .sig { text-align: center; font-size: 10pt; }
        .sig .line { border-top: 0.75pt solid #000; margin-bottom: 3pt; }
        .sig .name { font-weight: bold; min-height: 14pt; }
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

        @page { size: A4 portrait; margin: 12mm; }

        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; padding: 0; max-width: none; }
            .toolbar { display: none !important; }
            .printed-note .pagenum::after { content: 'Page ' counter(page); }
            section, .signatures { break-inside: auto; }
            tr { break-inside: avoid; page-break-inside: avoid; }
            thead { display: table-header-group; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" class="primary" onclick="window.print()">Print this report</button>
        @if ($backHref)
            <a href="{{ $backHref }}">{{ $backLabel }}</a>
        @endif
    </div>

    <div class="sheet">
        <header class="letterhead">
            <img src="{{ asset('images/bidduang-seal.png') }}" alt="Barangay Bidduang official seal">
            <div class="lines">
                <div class="rep">Republic of the Philippines</div>
                <div class="rep">Province of&nbsp;&nbsp;&nbsp;—&nbsp;&nbsp;&nbsp;&nbsp;· Municipality/City of&nbsp;&nbsp;&nbsp;—</div>
                <div class="brgy">Barangay Bidduang</div>
                <div class="office">Office of the Barangay — Records Section</div>
            </div>
        </header>

        <h1 class="doc-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="doc-sub">{{ $subtitle }}</p>
        @endif

        {{ $slot }}

        <div class="signatures">
            <div class="sig">
                <div class="line"></div>
                <div class="name">&nbsp;</div>
                <div class="role">Prepared by (name &amp; signature)</div>
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
