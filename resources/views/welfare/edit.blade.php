<x-app-layout>
@section('page_header')
    <x-page-header title="Edit Assistance — {{ $welfare->beneficiary_name }}" />
@endsection

@section('content')
{{-- Form card: also the dialog fragment (fetched with ?fragment=1). --}}
<div class="bg-white {{ request()->boolean('fragment') ? '' : 'rounded-xl shadow overflow-hidden' }} {{ request()->boolean('fragment') ? 'max-w-none' : 'max-w-4xl mx-auto' }}">
    <form action="{{ route('welfare.update', $welfare->id) }}" method="POST" class="{{ request()->boolean('fragment') ? 'p-0' : 'p-6' }} space-y-8">
        @csrf
        @method('PUT')
        @include('welfare._form', ['welfare' => $welfare])

        <x-form.actions cancel-href="{{ route('welfare.index') }}" submit-label="Update Request" />
    </form>
</div>
@endsection
</x-app-layout>
