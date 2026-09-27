<x-app-layout>
@section('page_header')
    <x-page-header title="Add Purok" />
@endsection

@section('content')
{{-- Form card: also the dialog fragment (fetched with ?fragment=1). --}}
<div class="bg-white {{ request()->boolean('fragment') ? '' : 'rounded-xl shadow overflow-hidden' }} {{ request()->boolean('fragment') ? 'max-w-none' : 'max-w-3xl mx-auto' }}">
    <form action="{{ route('puroks.store') }}" method="POST" class="{{ request()->boolean('fragment') ? 'p-0' : 'p-6' }} space-y-6">
        @csrf
        @include('purok._form', ['purok' => null])

        <x-form.actions cancel-href="{{ route('puroks.index') }}" submit-label="Create Purok" />
    </form>
</div>
@endsection
</x-app-layout>
