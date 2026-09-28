<x-app-layout>
@section('page_header')
    <x-page-header title="Edit Household — {{ $household->household_code }}" />
@endsection

@section('content')
{{-- Form card: also the dialog fragment (fetched with ?fragment=1). --}}
<div class="bg-white {{ request()->boolean('fragment') ? '' : 'rounded-xl shadow overflow-hidden' }} {{ request()->boolean('fragment') ? 'max-w-none' : 'max-w-4xl mx-auto' }}">
    <form action="{{ route('households.update', $household->id) }}" method="POST" class="{{ request()->boolean('fragment') ? 'p-0' : 'p-6' }} space-y-8">
        @csrf
        @method('PUT')
        @include('household._form', ['household' => $household])

        <x-form.actions cancel-href="{{ route('households.index') }}" submit-label="Update Household" />
    </form>
</div>
@endsection
</x-app-layout>
