<x-app-layout>
@section('page_header')
    <x-page-header title="Edit Drive — {{ $drive->title }}" />
@endsection

@section('content')
{{-- Form card: also the dialog fragment (fetched with ?fragment=1). --}}
<div class="bg-white {{ request()->boolean('fragment') ? '' : 'rounded-xl shadow overflow-hidden' }} {{ request()->boolean('fragment') ? 'max-w-none' : 'max-w-4xl mx-auto' }}">
    <form action="{{ route('cleanup.update', $drive->id) }}" method="POST" class="{{ request()->boolean('fragment') ? 'p-0' : 'p-6' }} space-y-8">
        @csrf
        @method('PUT')
        @include('cleanup._form', ['drive' => $drive])

        <x-form.actions cancel-href="{{ route('cleanup.index') }}" submit-label="Update Drive" />
    </form>
</div>
@endsection
</x-app-layout>
