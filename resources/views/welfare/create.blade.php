<x-app-layout>
@section('page_header')
    <x-page-header title="Record Assistance Request" />
@endsection

@section('content')
{{-- Form card: also the dialog fragment (fetched with ?fragment=1). --}}
<div class="bg-white {{ request()->boolean('fragment') ? '' : 'rounded-xl shadow overflow-hidden' }} {{ request()->boolean('fragment') ? 'max-w-none' : 'max-w-4xl mx-auto' }}">
    <form action="{{ route('welfare.store') }}" method="POST" class="{{ request()->boolean('fragment') ? 'p-0' : 'p-6' }} space-y-8">
        @csrf
        @include('welfare._form', ['welfare' => null])

        <x-form.actions cancel-href="{{ route('welfare.index') }}" submit-label="Record Request" />
    </form>
</div>
@endsection
</x-app-layout>
