<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 &middot; Something went wrong &middot; Barangay Bidduang</title>
    <link rel="icon" type="image/png" href="{{ asset('images/bidduang-seal.png') }}">
    <link rel="stylesheet" href="{{ asset('css/errors.css') }}">
</head>
<body>
    <main class="card">
        <img class="seal" src="{{ asset('images/bidduang-seal.png') }}" alt="Official seal of Barangay Bidduang">
        <p class="barangay">Barangay Bidduang</p>
        <p class="municipality">Pamplona &middot; Cagayan</p>

        <p class="code">500</p>
        <div class="rule"></div>
        <h1 class="title">Something went wrong</h1>
        <p class="message">
            The system could not complete your request. Please try again,
            or report this to the barangay office if it keeps happening.
        </p>

        <div class="actions">
            <a class="btn btn-primary" href="{{ url('/') }}">Try again</a>
            <a class="btn btn-ghost" href="{{ url('/login') }}">Sign in</a>
        </div>
    </main>
</body>
</html>
