<x-app-layout>
@section('page_header')
    <x-page-header title="Community cleanups" subtitle="Join an upcoming cleanup drive and track your volunteer hours." />
@endsection

@section('content')
<div class="mx-auto max-w-4xl space-y-6">

    @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Volunteer hours: one aggregate over attended sign-ups only. --}}
    <section class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        @if ($hoursTotal > 0)
            <p class="text-sm text-slate-500">You have logged <span class="font-semibold text-slate-900">{{ rtrim(rtrim(number_format($hoursTotal, 1), '0'), '.') }} volunteer {{ $hoursTotal == 1 ? 'hour' : 'hours' }}</span> across cleanup drives. Thank you for volunteering.</p>
        @else
            <p class="text-sm text-slate-500">No volunteer hours yet. Join a drive below — hours are logged once the office confirms your attendance.</p>
        @endif
    </section>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Upcoming cleanup drives</h2>
            <p class="mt-0.5 text-sm text-slate-500">Scheduled and ongoing drives you can join.</p>
        </div>
        @if ($drives->isEmpty())
            <p class="p-6 text-sm text-slate-500">No upcoming cleanup drives right now. Check back soon.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($drives as $drive)
                    <li class="p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900">{{ $drive->title }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $drive->purok?->name ?? 'All puroks' }} · {{ $drive->scheduled_at?->format('M j, Y g:i A') ?? '—' }}</p>
                                @if ($drive->description)
                                    <p class="mt-2 text-sm text-slate-700">{{ $drive->description }}</p>
                                @endif
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ ['Scheduled' => 'bg-sky-100 text-sky-800', 'Ongoing' => 'bg-emerald-100 text-emerald-800'][$drive->status] ?? 'bg-slate-100 text-slate-700' }}">{{ $drive->status }}</span>
                                @if (in_array($drive->id, $joinedIds, true))
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Signed up</span>
                                @else
                                    <form method="POST" action="{{ route('resident.cleanups.join', $drive) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-row">Join</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="px-5 pb-4">{{ $drives->withQueryString()->links() }}</div>
        @endif
    </section>
</div>
@endsection
</x-app-layout>
