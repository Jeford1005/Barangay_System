{{-- --}}
{{-- Standalone printable certificate — deliberately self-contained:          --}}
{{-- no layout, no Vite, one inline <style> block plus one print script.      --}}
{{-- The controller hands this view nothing but $issuance.                    --}}
{{-- --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $issuance->control_number }} &middot; Barangay Bidduang</title>

    <style>
        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
        }

        body {
            background: #e8edf3;
            color: #111;
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.6;
        }

        /* ---------------------------------------------------------- toolbar */
        .toolbar {
            max-width: 186mm;
            margin: 0 auto;
            padding: 18px 0 0;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
        }

        .toolbar-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .btn {
            display: inline-block;
            border-radius: 8px;
            padding: 9px 16px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: background-color .15s ease, color .15s ease;
        }

        .btn-print {
            background: #0284c7;
            color: #fff;
            border-color: #0284c7;
        }

        .btn-print:hover {
            background: #0369a1;
        }

        .btn-ghost {
            background: #fff;
            color: #334155;
            border-color: #cbd5e1;
        }

        .btn-ghost:hover {
            background: #f1f5f9;
        }

        .toolbar-control {
            font-size: 13px;
            color: #64748b;
        }

        /* ----------------------------------------------------------- sheet */
        .sheet {
            position: relative;
            max-width: 186mm;
            margin: 18px auto 44px;
            padding: 18mm 16mm;
            background: #fff;
            font-family: 'Times New Roman', Times, serif;
            box-shadow: 0 14px 36px rgba(15, 23, 42, .16);
        }

        /* ------------------------------------------------------ letterhead */
        .letterhead {
            text-align: center;
        }

        .seal {
            display: block;
            width: 20mm;
            height: 20mm;
            object-fit: contain;
            margin: 0 auto 4mm;
        }

        .letterhead p {
            margin: 0;
        }

        .republic {
            font-size: 11pt;
            letter-spacing: .05em;
        }

        .province {
            font-size: 10.5pt;
        }

        .barangay {
            margin-top: 2mm !important;
            font-size: 23pt;
            font-weight: 700;
            letter-spacing: .04em;
            line-height: 1.2;
        }

        .office {
            font-size: 10.5pt;
            font-weight: 600;
            letter-spacing: .18em;
            text-transform: uppercase;
        }

        .rule {
            border: 0;
            border-top: 2px solid #111;
            margin: 4mm 0 0;
        }

        .rule-thin {
            border: 0;
            border-top: 1px solid #111;
            margin: 1.5mm 0 0;
        }

        /* --------------------------------------------------- title + strip */
        .doc-title {
            margin: 10mm 0 0;
            font-size: 15pt;
            font-weight: 700;
            letter-spacing: .16em;
            text-align: center;
            text-transform: uppercase;
        }

        .control-strip {
            margin-top: 3mm;
            text-align: right;
            font-size: 10pt;
            line-height: 1.45;
        }

        .control-strip span {
            display: inline-block;
            min-width: 16mm;
            font-weight: 700;
        }

        /* ------------------------------------------------------------ body */
        .body p {
            margin: 0 0 5mm;
            text-align: justify;
        }

        .salutation {
            font-weight: 700;
            letter-spacing: .06em;
        }

        .recipient-name {
            text-transform: uppercase;
            font-weight: 700;
        }

        .purpose-box {
            border: 1px solid #333;
            padding: 3mm 4mm;
            margin: 6mm 0 0;
            background: #fafafa;
        }

        .purpose-box .purpose-label {
            display: inline-block;
            font-weight: 700;
            letter-spacing: .04em;
        }

        .validity {
            margin: 5mm 0 0;
            font-size: 10.5pt;
        }

        /* ------------------------------------------------------ signatures */
        .signatures {
            display: flex;
            justify-content: space-between;
            gap: 10mm;
            margin-top: 18mm;
        }

        .signatory {
            width: 74mm;
            text-align: center;
        }

        .sig-line {
            border-bottom: 1px solid #111;
            height: 15mm;
        }

        .sig-name {
            margin: 1.5mm 0 0;
            font-weight: 700;
            font-size: 11.5pt;
        }

        .sig-role {
            margin: 0;
            font-size: 10.5pt;
        }

        .requested-label {
            margin: 0;
            font-size: 10.5pt;
            font-weight: 700;
            text-align: left;
        }

        .signatory.requested {
            text-align: left;
        }

        /* ----------------------------------------------------------- foot */
        .footer {
            margin-top: 14mm;
            padding-top: 2.5mm;
            border-top: 1px solid #9ca3af;
            text-align: center;
            font-size: 9pt;
            color: #6b7280;
        }

        /* ------------------------------------------------------ void stamp */
        .void-stamp {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-18deg);
            padding: 3mm 9mm;
            font-size: 64pt;
            font-weight: 700;
            letter-spacing: .06em;
            line-height: 1.1;
            color: rgba(220, 38, 38, .55);
            border: 4px solid rgba(220, 38, 38, .55);
            pointer-events: none;
            z-index: 3;
        }

        /* --------------------------------------------------------- printing */
        @media print {
            body {
                background: #fff;
            }

            .no-print {
                display: none !important;
            }

            .sheet {
                margin: 0 auto;
                box-shadow: none;
            }
        }
    </style>
</head>

<body>
@php
    $document = $issuance->document;
    $snapshot = is_array($issuance->recipient_snapshot) ? $issuance->recipient_snapshot : [];
    $live = $issuance->resident;

    $filled = function (string $key) use ($snapshot, $live): string {
        $value = $snapshot[$key] ?? null;

        if ($value !== null && trim((string) $value) !== '') {
            return (string) $value;
        }

        return '';
    };

    $fullName = $filled('full_name') !== '' ? $filled('full_name') : (string) ($live?->full_name ?? '');
    $age = $filled('age') !== '' ? $filled('age') : ($live?->age ?? null);
    $civilStatus = $filled('civil_status') !== '' ? $filled('civil_status') : (string) ($live?->civil_status ?? '');
    $address = $filled('address') !== '' ? $filled('address') : (string) ($live?->resolvedAddress() ?? '');
    $purok = $filled('purok_name') !== '' ? $filled('purok_name') : (string) ($live?->purok?->name ?? '');

    $qualifiers = collect([
        $age !== null && $age !== '' ? $age.' years old' : '',
        $civilStatus !== '' ? strtolower($civilStatus) : '',
    ])->filter()->implode(', ');

    $place = collect([$address, $purok])
        ->filter(fn ($part): bool => trim((string) $part) !== '')
        ->implode(', ');

    $locality = ($place !== '' ? $place.', ' : '').'Barangay Bidduang';

    $code = trim((string) ($document?->code ?? ''));
    $title = trim((string) ($document?->title ?? '')) !== '' ? (string) $document->title : 'Barangay Certificate';
    $punongBarangay = \App\Models\Official::punongBarangay();
    $fee = (float) $issuance->fee;
@endphp

    {{-- ── screen-only toolbar ── --}}
    <div class="no-print toolbar">
        <div class="toolbar-group">
            <button type="button" data-print class="btn btn-print">🖨 Print this certificate</button>
            <a href="{{ route('certificates.index') }}" class="btn btn-ghost">&larr; Back to certificates</a>
        </div>
        <div class="toolbar-group">
            <span class="toolbar-control">{{ $issuance->control_number }}</span>
        </div>
    </div>

    <div class="sheet">
        @if ($issuance->status === 'Voided')
            <div class="void-stamp" aria-hidden="true">VOID</div>
        @endif

        {{-- ── letterhead ── --}}
        <header class="letterhead">
            <img class="seal"
                 src="{{ asset('images/bidduang-seal.png') }}"
                 alt="Official seal of Barangay Bidduang">
            <p class="republic">Republic of the Philippines</p>
            <p class="province">Province of Cagayan &middot; Municipality of Pamplona</p>
            <p class="barangay">Barangay Bidduang</p>
            <p class="office">Office of the Punong Barangay</p>
            <hr class="rule">
            <hr class="rule-thin">
        </header>

        <h1 class="doc-title">{{ $title }}</h1>

        <div class="control-strip">
            <div><span>Control No.</span> {{ $issuance->control_number }}</div>
            <div><span>Issued:</span> {{ $issuance->issued_at->format('M j, Y') }}</div>
        </div>

        {{-- ── body ── --}}
        <div class="body">
            <p class="salutation">TO WHOM IT CONCERN:</p>

            <p>
                This is to certify that
                <span class="recipient-name">{{ $fullName !== '' ? $fullName : '________________________' }}</span>@if ($qualifiers !== ''), {{ $qualifiers }}@endif,
                residing at {{ $locality }}, Municipality of Pamplona, Province of Cagayan,
                @if ($code === 'CLR')
                    is known to be of good moral character and has no pending criminal case on file with this Office or any court of competent jurisdiction as of the date of issuance.
                @elseif ($code === 'COR')
                    is a bona fide resident of Barangay Bidduang, as shown by the records kept by this Office.
                @else
                    belongs to a low income and indigent family of Barangay Bidduang and is a resident of good standing in this community.
                @endif
            </p>

            <p>
                This certification is issued upon the request of the above-named person for
                whatever lawful purpose it may serve.
            </p>

            <div class="purpose-box">
                <span class="purpose-label">Purpose:</span>
                {{ $issuance->purpose }}
            </div>

            <p class="validity">
                This certificate is valid for six (6) months from the date of issuance.
                @if ($fee > 0)
                    &middot; Issued upon payment of ₱{{ number_format($fee, 2) }}{{ $issuance->copies > 1 ? ' for '.$issuance->copies.' copies' : '' }}.
                @else
                    &middot; Issued free of charge.
                @endif
            </p>
        </div>

        {{-- ── signatures ── --}}
        <div class="signatures">
            <div class="signatory">
                <div class="sig-line"></div>
                <p class="sig-name">{{ $punongBarangay?->full_name ?? '______________________' }}</p>
                <p class="sig-role">Punong Barangay</p>
            </div>

            <div class="signatory requested">
                <p class="requested-label">Requested by:</p>
                <div class="sig-line"></div>
                <p class="sig-name">{{ $fullName !== '' ? $fullName : '________________________' }}</p>
            </div>
        </div>

        <p class="footer">
            {{ $issuance->control_number }} &middot; Printed {{ now()->format('M j, Y g:i A') }} &middot; Barangay Management System
        </p>
    </div>

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
