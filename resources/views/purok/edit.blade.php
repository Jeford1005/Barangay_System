<x-app-layout>
@section('page_header')
    <x-page-header title="Edit Purok" />
@endsection

@section('content')
{{-- Form card: also the dialog fragment (fetched with ?fragment=1). --}}
<div class="bg-white {{ request()->boolean('fragment') ? '' : 'rounded-xl shadow overflow-hidden' }} {{ request()->boolean('fragment') ? 'max-w-none' : 'max-w-3xl mx-auto' }}">
    <form action="{{ route('puroks.update', $purok->id) }}" method="POST" class="{{ request()->boolean('fragment') ? 'p-0' : 'p-6' }} space-y-6">
        @csrf
        @method('PUT')
        @include('purok._form', ['purok' => $purok])

        <x-form.actions cancel-href="{{ route('puroks.index') }}" submit-label="Update Purok" />
    </form>
</div>
@endsection
</x-app-layout>
