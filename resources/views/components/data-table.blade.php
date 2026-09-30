{{-- Shared scroll wrapper for every index data table (Residents, Households,
     Puroks, Blotter, Welfare, Certificates, Archive, Users, Requests,
     Corrections). One place owns the behaviour so the ten lists cannot
     drift: body-only scroll with a capped height, a sticky solid header,
     and separate borders (sticky cells do not stick under collapsed
     borders in Chromium). Print CSS in app.css resets all of it so paper
     never clips. Usage: <x-data-table> <table>…</table> </x-data-table>.
     Extra classes merge onto the wrapper (card chrome on some lists).
     `compact` tightens cell gutters on phones (resident maximize). --}}
@props(['compact' => false])

<div {{ $attributes->merge(['class' => 'table-scroll'.($compact ? ' table-compact' : '')]) }}>{{ $slot }}</div>
