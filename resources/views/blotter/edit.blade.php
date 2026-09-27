<x-app-layout>
@section('page_header')
    <x-page-header title="Edit Case {{ $blotter->case_number }}" />
@endsection

@section('content')
@unless (request()->boolean('fragment'))
    <div class="no-print mx-auto mb-4 flex max-w-4xl flex-wrap items-center justify-end rounded-xl border border-neutral-200 bg-white p-3 shadow-sm">
        <a href="{{ route('blotter.print', $blotter) }}" target="_blank" class="inline-flex min-h-10 items-center rounded-lg border border-blue-200 bg-white px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500" title="Print official case sheet">
            <x-icon name="printer" class="mr-2 h-4 w-4 text-neutral-500" />
            Print
        </a>
    </div>
@endunless

{{-- Form card: also the dialog fragment (fetched with ?fragment=1). --}}
<div class="bg-white {{ request()->boolean('fragment') ? '' : 'rounded-xl shadow overflow-hidden' }} {{ request()->boolean('fragment') ? 'max-w-none' : 'max-w-4xl mx-auto' }}">
    <form action="{{ route('blotter.update', $blotter->id) }}" method="POST" class="{{ request()->boolean('fragment') ? 'p-0' : 'p-6' }} space-y-8">
        @csrf
        @method('PUT')
        @include('blotter._form', ['blotter' => $blotter])

        <x-form.actions color="amber" cancel-href="{{ route('blotter.index') }}" submit-label="Update Case" />
    </form>
</div>
@endsection
</x-app-layout>
