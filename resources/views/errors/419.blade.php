<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>419 &middot; Session expired &middot; Barangay Bidduang</title>
    <link rel="icon" type="image/png" href="{{ asset('images/bidduang-seal.png') }}">
    <link rel="stylesheet" href="{{ asset('css/errors.css') }}">
</head>
<body>
    <main class="card">
        <img class="seal" src="{{ asset('images/bidduang-seal.png') }}" alt="Official seal of Barangay Bidduang">
        <p class="barangay">Barangay Bidduang</p>
        <p class="municipality">Pamplona &middot; Cagayan</p>

        <p class="code">419</p>
        <div class="rule"></div>
        <h1 class="title">Session expired</h1>
        <p class="message">
            Your session expired because the page was open for too long.
            Please go back, refresh the page, and try again.
        </p>

        <div class="actions">
            <a class="btn btn-primary" href="{{ url()->previous() }}">Go back</a>
            <a class="btn btn-ghost" href="{{ url('/login') }}">Sign in</a>
        </div>
    </main>
</body>
</html>
