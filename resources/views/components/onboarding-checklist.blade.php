{{-- First-run checklist for a fresh or half-set-up barangay. Shown by
    dashboard.blade.php INSTEAD OF the stat cards until purok + household
    + resident ALL exist — never alongside them.

    Each step reads its done/todo state from the live counts passed in as
    props (no queries run here). Permission gates mirror the dashboard Quick
    Actions below: purok setup is admin-only, household/resident steps need
    their manage permission, and analytics needs analytics.view. A step the
    signed-in user may not perform renders as muted guidance with no link,
    so nobody is offered a shortcut that 403s. The review step links to
    analytics for permitted users and to nothing otherwise — never back to
    the dashboard itself, which would be a self-link loop. --}}
@props([
    'purokCount' => 0,
    'householdCount' => 0,
    'residentCount' => 0,
])

@php
    $purokDone = ($purokCount ?? 0) > 0;
    $householdDone = ($householdCount ?? 0) > 0;
    $residentDone = ($residentCount ?? 0) > 0;
    $reviewDone = $purokDone && $householdDone && $residentDone;

    $canPurok = auth()->user()?->isAdmin() ?? false;
    $canHousehold = auth()->user()?->hasPermission('households.manage') ?? false;
    $canResident = auth()->user()?->hasPermission('residents.manage') ?? false;
    $canAnalytics = auth()->user()?->hasPermission('analytics.view') ?? false;

    $steps = [
        [
            'number' => 1,
            'title' => 'Add a purok',
            'hint' => 'Puroks organize every household and resident that follows.',
            'done' => $purokDone,
            'allowed' => $canPurok,
            'href' => $canPurok ? route('puroks.index').'?open=purok' : null,
            'action' => 'Add purok',
            'gate' => 'Admin only',
        ],
        [
            'number' => 2,
            'title' => 'Add a household',
            'hint' => 'Households group residents under one roof and one purok.',
            'done' => $householdDone,
            'allowed' => $canHousehold,
            'href' => $canHousehold ? route('households.index').'?open=household' : null,
            'action' => 'Add household',
            'gate' => 'Needs households.manage',
        ],
        [
            'number' => 3,
            'title' => 'Add a resident',
            'hint' => 'Register residents into their household and purok.',
            'done' => $residentDone,
            'allowed' => $canResident,
            'href' => $canResident ? route('residents.index').'?open=resident' : null,
            'action' => 'Add resident',
            'gate' => 'Needs residents.manage',
        ],
        [
            'number' => 4,
            'title' => 'Review your dashboard',
            'hint' => 'Once records exist, the overview, queues and analytics come alive.',
            'done' => $reviewDone,
            'allowed' => true,
            // No analytics permission means no off-page target: linking the
            // dashboard to itself would be a self-link loop, so drop the
            // link and leave the step as guidance.
            'href' => $canAnalytics ? route('analytics.index') : null,
            'action' => $canAnalytics ? 'View analytics' : 'Review dashboard',
            'gate' => null,
        ],
    ];
@endphp

<section id="onboarding-checklist" aria-label="Getting started checklist" class="bg-white rounded-xl shadow overflow-hidden">
    <div class="bg-slate-50 px-6 py-4">
        <h3 class="text-sm font-semibold text-slate-900">Getting started</h3>
        <p class="mt-0.5 text-sm text-slate-500">Your barangay is empty. Work through these steps in order — each one unlocks the next.</p>
    </div>
    <ol class="divide-y divide-slate-200">
        @foreach ($steps as $step)
            <li class="flex items-center gap-4 px-6 py-4">
                @if ($step['done'])
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100" aria-hidden="true">
                        <svg class="h-5 w-5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                @else
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-600" aria-hidden="true">{{ $step['number'] }}</span>
                @endif
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-slate-900">
                        {{ $step['title'] }}
                        <span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $step['done'] ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                            {{ $step['done'] ? 'Done' : 'To do' }}
                        </span>
                    </p>
                    <p class="mt-0.5 text-sm text-slate-500">{{ $step['hint'] }}</p>
                    @if (! $step['allowed'])
                        <p class="mt-0.5 text-xs font-medium text-slate-400">{{ $step['gate'] }} — ask an administrator.</p>
                    @endif
                </div>
                @if (! $step['done'] && $step['href'])
                    <a href="{{ $step['href'] }}" class="btn btn-outline shrink-0">{{ $step['action'] }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</section>
