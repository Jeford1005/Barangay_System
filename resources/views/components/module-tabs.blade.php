{{-- Shared module tab strip. Folds related pages (queue, catalog, analytics)
     under their parent sidebar row so the admin sidebar stays short: the tab
     is a plain link to the real URL, so deep links, tests and no-JS keep
     working. Badges carry the pending counts the old sidebar rows showed. --}}
@props(['type' => null])

@php
    $tabs = match ($type) {
        'certificates' => [
            ['label' => 'Issued', 'href' => route('certificates.index'), 'match' => 'certificates.index'],
            ['label' => 'Requests', 'href' => route('admin.certificate-requests.index'), 'match' => 'admin.certificate-requests.*', 'badge' => ($moduleTabBadges['certRequests'] ?? null) ?? App\Models\CertificateRequest::pending()->count()],
            ['label' => 'Catalog', 'href' => route('admin.certificate-types.index'), 'match' => 'admin.certificate-types.*', 'visible' => auth()->user()?->isAdmin() ?? false],
        ],
        'residents' => [
            ['label' => 'Residents', 'href' => route('residents.index'), 'match' => 'residents.index'],
            ['label' => 'Corrections', 'href' => route('admin.resident-changes.index'), 'match' => 'admin.resident-changes.*', 'badge' => ($moduleTabBadges['residentChanges'] ?? null) ?? App\Models\ResidentRecordChange::where('status', 'Pending')->count()],
        ],
        'reports' => [
            ['label' => 'Reports', 'href' => route('reports.index'), 'match' => 'reports.*'],
            ['label' => 'Analytics', 'href' => route('analytics.index'), 'match' => 'analytics.*'],
        ],
        default => [],
    };
@endphp

@if ($tabs !== [])
<nav class="flex items-stretch gap-1 overflow-x-auto border-b border-slate-200 pb-px" aria-label="Module sections" {{ $attributes->merge() }}>
    @foreach ($tabs as $tab)
        @if (($tab['visible'] ?? true) === false)
            @continue
        @endif
        @php($active = request()->routeIs($tab['match']))
        <a href="{{ $tab['href'] }}"
            @if ($active) aria-current="page" @endif
            class="-mb-px inline-flex min-h-11 items-center gap-2 whitespace-nowrap border-b-2 px-3 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-sky-600 {{ $active ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
            {{ $tab['label'] }}
            @if (($tab['badge'] ?? 0) > 0)
                <span class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-sky-100 px-1.5 text-[11px] font-semibold tabular-nums text-sky-700">{{ $tab['badge'] }}</span>
            @endif
        </a>
    @endforeach
</nav>
@endif
