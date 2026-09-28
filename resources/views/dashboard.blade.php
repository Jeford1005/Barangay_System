<x-app-layout>
@section('page_header')
    <x-page-header title="Dashboard" subtitle="Welcome back, {{ auth()->user()?->name }}. Here is an overview of barangay records." />
@endsection

@section('content')
<div class="max-w-7xl mx-auto">

    {{-- Scale: what this barangay holds. Every card is a link - a number you
         cannot go anywhere from is a dead end, not a statistic. --}}
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('residents.index') }}" class="bg-white rounded-xl shadow p-6 hover:shadow-md transition-shadow focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Residents</p>
                <x-icon name="residents" class="h-6 w-6 text-sky-600" />
            </div>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $residentCount }}</p>
        </a>
        <a href="{{ route('households.index') }}" class="bg-white rounded-xl shadow p-6 hover:shadow-md transition-shadow focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Households</p>
                <x-icon name="households" class="h-6 w-6 text-sky-600" />
            </div>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $householdCount }}</p>
        </a>
        <a href="{{ route('puroks.index') }}" class="bg-white rounded-xl shadow p-6 hover:shadow-md transition-shadow focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Puroks</p>
                <x-icon name="puroks" class="h-6 w-6 text-sky-600" />
            </div>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $purokCount }}</p>
        </a>
        <a href="{{ route('certificates.index') }}" class="bg-white rounded-xl shadow p-6 hover:shadow-md transition-shadow focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Certificates issued</p>
                <x-icon name="document-text" class="h-6 w-6 text-sky-600" />
            </div>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $issuedTotal }}</p>
        </a>
    </div>

    {{-- Work and flow: what is waiting, and what has moved this month. --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="lg:col-span-2 bg-white rounded-xl shadow overflow-hidden">
            <div class="flex items-center justify-between bg-slate-50 px-6 py-4">
                <div class="flex items-center gap-2">
                    <x-icon name="inbox" class="h-4 w-4 text-slate-500" />
                    <h3 class="text-sm font-semibold text-slate-900">Needs your attention</h3>
                </div>
                @if ($attentionTotal > 0)
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">
                        {{ $attentionTotal }} waiting
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">
                        All clear
                    </span>
                @endif
            </div>

            @if ($attentionTotal > 0)
                <ul class="divide-y divide-slate-200">
                    @foreach ($queues as $queue)
                        @continue($queue['count'] === 0)
                        <li>
                            <a href="{{ $queue['href'] }}" class="flex items-center gap-3 px-6 py-3.5 transition-colors hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-sky-600">
                                <span class="h-2 w-2 shrink-0 rounded-full {{ $queue['critical'] ? 'bg-red-500' : 'bg-amber-500' }}"></span>
                                <span class="flex-1 text-sm font-medium text-slate-900">{{ $queue['label'] }}</span>
                                <span class="text-sm font-semibold text-slate-900">{{ $queue['count'] }}</span>
                                <x-icon name="chevron-down" class="h-4 w-4 -rotate-90 text-slate-400" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                {{-- With an empty queue the work is the absence of work, so the
                     state reads as healthy rather than as a missing widget. --}}
                <div class="px-6 py-9 text-center">
                    <x-icon name="check-circle" class="mx-auto h-8 w-8 text-emerald-500" />
                    <p class="mt-3 text-sm font-medium text-slate-900">All clear — nothing waiting</p>
                    <p class="mt-1 text-sm text-slate-500">New requests, corrections and cases appear here as they arrive.</p>
                </div>
            @endif
        </section>

        <section class="bg-white rounded-xl shadow overflow-hidden">
            <div class="bg-slate-50 px-6 py-4">
                <h3 class="text-sm font-semibold text-slate-900">This month</h3>
            </div>
            <ul class="divide-y divide-slate-200">
                <li class="flex items-center justify-between px-6 py-3.5">
                    <span class="text-sm text-slate-600">Certificates issued</span>
                    <span class="text-sm font-semibold text-slate-900">{{ $issuedMonth }}</span>
                </li>
                <li class="flex items-center justify-between px-6 py-3.5">
                    <span class="text-sm text-slate-600">Residents registered</span>
                    <span class="text-sm font-semibold text-slate-900">{{ $newResidentsMonth }}</span>
                </li>
                <li class="flex items-center justify-between px-6 py-3.5">
                    <span class="text-sm text-slate-600">Households added</span>
                    <span class="text-sm font-semibold text-slate-900">{{ $newHouseholdsMonth }}</span>
                </li>
            </ul>
            <div class="border-t border-slate-200 px-6 py-3">
                <a href="{{ route('analytics.index') }}" class="inline-flex items-center gap-1.5 rounded text-sm font-medium text-sky-700 hover:text-sky-800 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2">
                    <x-icon name="chart-bar" class="h-4 w-4" />
                    View full analytics
                </a>
            </div>
        </section>
    </div>

    {{-- Shortcuts come last: they are what you reach for after you have looked. --}}
    <div class="mt-6 bg-white rounded-xl shadow overflow-hidden">
        <div class="bg-slate-50 px-6 py-4">
            <h3 class="text-sm font-semibold text-slate-900">Quick Actions</h3>
        </div>
        <div class="p-6 grid grid-cols-1 gap-4 {{ auth()->user()?->isAdmin() ? 'sm:grid-cols-2 xl:grid-cols-4' : 'sm:grid-cols-3' }}">
            <a href="{{ route('residents.index') }}?open=resident" class="inline-flex items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm font-medium text-sky-700 hover:bg-sky-100 focus:outline-none focus:ring-2 focus:ring-sky-600">
                <x-icon name="user-plus" class="h-4 w-4 text-sky-600" />
                Register a resident
            </a>
            <a href="{{ route('households.index') }}?open=household" class="inline-flex items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm font-medium text-sky-700 hover:bg-sky-100 focus:outline-none focus:ring-2 focus:ring-sky-600">
                <x-icon name="households" class="h-4 w-4 text-sky-600" />
                Add a household
            </a>
            <a href="{{ route('certificates.index') }}?open=certificate" class="inline-flex items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm font-medium text-sky-700 hover:bg-sky-100 focus:outline-none focus:ring-2 focus:ring-sky-600">
                <x-icon name="document-text" class="h-4 w-4 text-sky-600" />
                Issue a certificate
            </a>
            @if (auth()->user()?->isAdmin())
                <a href="{{ route('puroks.index') }}?open=purok" class="inline-flex items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm font-medium text-sky-700 hover:bg-sky-100 focus:outline-none focus:ring-2 focus:ring-sky-600">
                    <x-icon name="plus" class="h-4 w-4 text-sky-600" />
                    Add a purok
                </a>
            @endif
        </div>
    </div>
</div>
@endsection
</x-app-layout>
