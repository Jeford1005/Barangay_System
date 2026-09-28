<x-app-layout>
@section('page_header')
    <x-page-header title="Edit Case {{ $blotter->case_number }}">
        <x-slot:actions>
            @unless (request()->boolean('fragment'))
                <a href="{{ route('blotter.print', $blotter) }}" target="_blank" class="inline-flex min-h-10 items-center rounded-lg border border-sky-200 bg-white px-3 py-2 text-sm font-medium text-sky-700 hover:bg-sky-50 focus:outline-none focus:ring-2 focus:ring-sky-600" title="Print official case sheet">
                    <x-icon name="printer" class="mr-2 h-4 w-4 text-slate-500" />
                    Print
                </a>
            @endunless
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
{{-- Form card: also the dialog fragment (fetched with ?fragment=1). --}}
<div class="bg-white {{ request()->boolean('fragment') ? '' : 'rounded-xl shadow overflow-hidden' }} {{ request()->boolean('fragment') ? 'max-w-none' : 'max-w-4xl mx-auto' }}">
    <form action="{{ route('blotter.update', $blotter->id) }}" method="POST" class="{{ request()->boolean('fragment') ? 'p-0' : 'p-6' }} space-y-8">
        @csrf
        @method('PUT')
        @include('blotter._form', ['blotter' => $blotter])

        <x-form.actions cancel-href="{{ route('blotter.index') }}" submit-label="Update Case" />
    </form>
</div>
@endsection
</x-app-layout>
