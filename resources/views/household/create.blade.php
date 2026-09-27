<x-app-layout>
@section('page_header')
    <x-page-header title="Add Household" />
@endsection

@section('content')
{{-- Form card: also the dialog fragment (fetched with ?fragment=1). --}}
<div class="bg-white {{ request()->boolean('fragment') ? '' : 'rounded-xl shadow overflow-hidden' }} {{ request()->boolean('fragment') ? 'max-w-none' : 'max-w-4xl mx-auto' }}">
    <form action="{{ route('households.store') }}" method="POST" class="{{ request()->boolean('fragment') ? 'p-0' : 'p-6' }} space-y-8">
        @csrf
        @include('household._form', ['household' => null])

        <x-form.actions cancel-href="{{ route('households.index') }}" submit-label="Create Household" />
    </form>
</div>
@endsection
</x-app-layout>
