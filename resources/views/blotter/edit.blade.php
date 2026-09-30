<x-app-layout>
@section('page_header')
    <x-page-header title="Edit Case {{ $blotter->case_number }}">
        <x-slot:actions>
            @unless (request()->boolean('fragment'))
                <a href="{{ route('blotter.print', $blotter) }}" target="_blank" class="btn btn-outline" title="Print official case sheet">
                    <x-icon name="printer" class="h-4 w-4" />
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
