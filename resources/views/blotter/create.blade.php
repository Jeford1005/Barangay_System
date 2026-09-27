<x-app-layout>
@section('page_header')
    <x-page-header title="Record Blotter Case" subtitle="A case number (BLTR-{{ date('Y') }}-####) is assigned automatically on save." />
@endsection

@section('content')
{{-- Form card: also the dialog fragment (fetched with ?fragment=1). --}}
<div class="bg-white {{ request()->boolean('fragment') ? '' : 'rounded-xl shadow overflow-hidden' }} {{ request()->boolean('fragment') ? 'max-w-none' : 'max-w-4xl mx-auto' }}">
    <form action="{{ route('blotter.store') }}" method="POST" class="{{ request()->boolean('fragment') ? 'p-0' : 'p-6' }} space-y-8">
        @csrf
        @include('blotter._form', ['blotter' => null])

        <x-form.actions cancel-href="{{ route('blotter.index') }}" submit-label="Record Case" />
    </form>
</div>
@endsection
</x-app-layout>
