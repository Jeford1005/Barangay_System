<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Cleanup drive sheet — Barangay Management System</title>
    <style>
        /* Self-contained guest page: no app bundle, no auth chrome, so a
           phone scanning the venue QR sees the drive sheet immediately. */
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
            font-size: 15px;
            letter-spacing: .08em;
            padding: 8px 18px;
            border-radius: 999px;
            margin: 6px 0 14px;
        }
        .badge.Scheduled { background: #e0f2fe; color: #075985; border: 2px solid #0284c7; }
        .badge.Ongoing { background: #fef3c7; color: #92400e; border: 2px solid #d97706; }
        .badge.Completed { background: #dcfce7; color: #166534; border: 2px solid #16a34a; }
        .badge.Cancelled { background: #f1f5f9; color: #475569; border: 2px solid #94a3b8; }
        .center { text-align: center; }
        .title { font-size: 19px; font-weight: bold; }
        dl.facts { margin: 12px 0; border-top: 1px solid #e2e8f0; }
        dl.facts div { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
        dl.facts dt { color: #475569; }
        dl.facts dd { font-weight: bold; text-align: right; word-break: break-word; }
        .desc { font-size: 14px; color: #334155; margin: 4px 0 8px; white-space: pre-wrap; }
        .note { font-size: 13px; color: #475569; margin-top: 12px; }
        .foot { text-align: center; font-size: 12px; color: #94a3b8; margin-top: 16px; }
        #maintenance-notice, #maintenance-active {
            max-width: 560px; margin: 0 auto 12px; padding: 10px 14px;
            border-radius: 10px; font-size: 13px;
        }
        #maintenance-notice { background: #fffbeb; border: 1px solid #f59e0b; color: #92400e; }
        #maintenance-active { background: #fef2f2; border: 1px solid #dc2626; color: #991b1b; }
        #maintenance-notice strong, #maintenance-active strong { display: block; }
    </style>
</head>
<body>
    {{-- View-only maintenance banner: the public sheet sits outside
         the app layout, so the banner is mounted here explicitly. --}}
    <x-maintenance-banner />
    <div class="card">
        <p class="office">
            Republic of the Philippines
            <strong>Barangay Bidduang</strong>
            Office of the Punong Barangay — cleanup drive sheet
        </p>

        <p class="center title">{{ $drive->title }}</p>
        <p class="center"><span class="badge {{ $drive->status }}">{{ $drive->status }}</span></p>

        @if (filled($drive->description))
            <p class="desc">{{ $drive->description }}</p>
        @endif

        <dl class="facts">
            <div><dt>Venue purok</dt><dd>{{ $drive->purok?->name ?? 'Barangay-wide' }}</dd></div>
            <div><dt>Scheduled</dt><dd>{{ $drive->scheduled_at?->format('M j, Y g:i A') ?? '—' }}</dd></div>
            {{-- Aggregates only: like the certificate verify page, this
                 public sheet never names individual volunteers, so a shared
                 venue link cannot be used to harvest resident PII. --}}
            <div><dt>Signed up</dt><dd>{{ $signupCount ?? $drive->participants_count ?? 0 }}</dd></div>
            <div><dt>Attended</dt><dd>{{ $attendedCount ?? '—' }}</dd></div>
            <div><dt>Volunteer hours</dt><dd>{{ isset($totalHours) ? number_format((float) $totalHours, 1) : '—' }}</dd></div>
        </dl>
        <p class="note">This is a read-only public sheet for a barangay cleanup drive. To join or for questions, visit the barangay hall.</p>

        <p class="foot">Barangay Management System · public drive sheet</p>
    </div>
</body>
</html>
