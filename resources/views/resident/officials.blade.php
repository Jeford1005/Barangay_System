<x-app-layout>
@section('page_header')
    <x-page-header title="Barangay Officials" subtitle="The officials currently serving your barangay." />
@endsection

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <p class="max-w-2xl text-sm text-slate-600">The barangay hall can help you reach any official listed here about a request or a concern.</p>

    @if ($officials->isEmpty())
        <section class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
            No barangay officials are listed yet.
        </section>
    @else
        <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach ($officials as $official)
                <li class="flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    {{-- No photo route exists for officials yet, so the directory
                         shows initials rather than a path nothing would serve. --}}
                    <span aria-hidden="true" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-600">
                        {{ strtoupper(mb_substr($official->first_name, 0, 1) . mb_substr($official->last_name, 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-base font-semibold text-slate-900">{{ $official->full_name }}</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-700">{{ $official->position }}</p>
                        <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
                            <dt class="font-medium text-slate-500">Office</dt>
                            <dd class="text-slate-700">{{ $official->office }}</dd>
                            <dt class="font-medium text-slate-500">Term</dt>
                            <dd class="text-slate-700">{{ $official->term_start->format('M j, Y') }} – {{ $official->term_end->format('M j, Y') }}</dd>
                        </dl>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
</x-app-layout>
