<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 &middot; Access denied &middot; Barangay Bidduang</title>
    <link rel="icon" type="image/png" href="{{ asset('images/bidduang-seal.png') }}">
    <link rel="stylesheet" href="{{ asset('css/errors.css') }}">
</head>
<body>
    <main class="card">
        <img class="seal" src="{{ asset('images/bidduang-seal.png') }}" alt="Official seal of Barangay Bidduang">
        <p class="barangay">Barangay Bidduang</p>
        <p class="municipality">Pamplona &middot; Cagayan</p>

        <p class="code">403</p>
        <div class="rule"></div>
        <h1 class="title">Access denied</h1>
        <p class="message">
            Your account does not have permission to open this page.
            If you believe this is a mistake, please contact the barangay office.
        </p>

        <div class="actions">
            <a class="btn btn-primary" href="{{ url('/') }}">Go to my dashboard</a>
            <a class="btn btn-ghost" href="{{ url('/login') }}">Sign in</a>
        </div>
    </main>
</body>
</html>
