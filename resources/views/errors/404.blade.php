<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 &middot; Page not found &middot; Barangay Bidduang</title>
    <link rel="icon" type="image/png" href="{{ asset('images/bidduang-seal.png') }}">
    <link rel="stylesheet" href="{{ asset('css/errors.css') }}">
</head>
<body>
    <main class="card">
        <img class="seal" src="{{ asset('images/bidduang-seal.png') }}" alt="Official seal of Barangay Bidduang">
        <p class="barangay">Barangay Bidduang</p>
        <p class="municipality">Pamplona &middot; Cagayan</p>

        <p class="code">404</p>
        <div class="rule"></div>
        <h1 class="title">Page not found</h1>
        <p class="message">
            The page you are looking for does not exist or may have been moved.
        </p>

        <div class="actions">
            <a class="btn btn-primary" href="{{ url('/') }}">Back to home</a>
            <a class="btn btn-ghost" href="{{ url('/login') }}">Sign in</a>
        </div>
    </main>
</body>
</html>
