<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate verification — Barangay Management System</title>
    <style>
        /* Self-contained guest page: no app bundle, no auth chrome, so a
           phone scanning the printed QR sees a result immediately. */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 15px;
            line-height: 1.5;
            color: #111827;
            background: #f1f5f9;
            padding: 20px 12px;
        }
        .card {
            max-width: 560px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 24px 22px;
            box-shadow: 0 1px 4px rgba(0,0,0,.12);
        }
        .office { text-align: center; font-size: 12px; color: #475569; margin-bottom: 12px; }
        .office strong { display: block; font-size: 15px; color: #111827; letter-spacing: .08em; text-transform: uppercase; }
        .badge {
            display: inline-block;
            font-weight: bold;
            font-size: 17px;
            letter-spacing: .1em;
            padding: 8px 18px;
            border-radius: 999px;
            margin: 6px 0 14px;
        }
        .badge.valid { background: #dcfce7; color: #166534; border: 2px solid #16a34a; }
        .badge.void { background: #fee2e2; color: #991b1b; border: 2px solid #dc2626; }
        .badge.unknown, .badge.invalid { background: #f1f5f9; color: #475569; border: 2px solid #94a3b8; }
        .center { text-align: center; }
        dl.facts { margin: 12px 0; border-top: 1px solid #e2e8f0; }
        dl.facts div { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
        dl.facts dt { color: #475569; }
        dl.facts dd { font-weight: bold; text-align: right; word-break: break-all; }
        .note { font-size: 13px; color: #475569; margin-top: 12px; }
        .foot { text-align: center; font-size: 12px; color: #94a3b8; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <p class="office">
            Republic of the Philippines
            <strong>Barangay Bidduang</strong>
            Office of the Punong Barangay — certificate verification
        </p>

        @if ($status === 'valid')
            <p class="center"><span class="badge valid">VALID CERTIFICATE</span></p>
            <dl class="facts">
                <div><dt>Control number</dt><dd>{{ $controlNumber }}</dd></div>
                <div><dt>Certificate type</dt><dd>{{ $issuance->document->title ?? '—' }}</dd></div>
                <div><dt>Date issued</dt><dd>{{ $issuance->created_at?->format('M j, Y') ?? '—' }}</dd></div>
                <div><dt>Status</dt><dd>Issued</dd></div>
            </dl>
            <p class="note">This control number matches an issued certificate in the barangay records. For questions, visit the barangay hall and present the printed certificate.</p>
        @elseif ($status === 'void')
            <p class="center"><span class="badge void">VOID CERTIFICATE</span></p>
            <dl class="facts">
                <div><dt>Control number</dt><dd>{{ $controlNumber }}</dd></div>
                <div><dt>Certificate type</dt><dd>{{ $issuance->document->title ?? '—' }}</dd></div>
                <div><dt>Status</dt><dd>Voided — no longer an official document</dd></div>
            </dl>
            <p class="note">This certificate was voided by the barangay office. Please visit the barangay hall for assistance.</p>
        @elseif ($status === 'unknown')
            <p class="center"><span class="badge unknown">CERTIFICATE NOT FOUND</span></p>
            <dl class="facts">
                <div><dt>Control number</dt><dd>{{ $controlNumber }}</dd></div>
            </dl>
            <p class="note">No certificate with this control number exists in the barangay records. Check the printed control number and try again, or visit the barangay hall.</p>
        @else
            <p class="center"><span class="badge invalid">INVALID VERIFICATION CODE</span></p>
            <dl class="facts">
                <div><dt>Control number</dt><dd>{{ $controlNumber }}</dd></div>
            </dl>
            <p class="note">This verification link is not authentic. Please scan the QR code on the printed certificate again instead of typing the address by hand.</p>
        @endif

        <p class="foot">Barangay Management System · public verification</p>
    </div>
</body>
</html>
