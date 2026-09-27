<x-app-layout>
@section('page_header')
    <x-page-header title="Edit Resident — {{ $resident->first_name }} {{ $resident->last_name }}" />
@endsection

@section('content')
{{-- Form card: also the dialog fragment (fetched with ?fragment=1). --}}
<div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm {{ request()->boolean('fragment') ? 'max-w-none rounded-none border-0 shadow-none' : 'max-w-4xl mx-auto' }}">
    @unless(request()->boolean('fragment'))
        <div class="border-b border-neutral-200 bg-neutral-50 px-6 py-4">
            <h2 class="font-semibold text-neutral-900">Resident details</h2>
            <p class="mt-1 text-sm text-neutral-500">Update this resident’s record and classification.</p>
        </div>
    @endunless
    <form action="{{ route('residents.update', $resident->id) }}" method="POST" class="space-y-6 {{ request()->boolean('fragment') ? 'p-0' : 'p-6' }}">
        @csrf
        @method('PUT')
        @include('resident._form', ['resident' => $resident])

        <x-form.actions color="amber" cancel-href="{{ route('residents.index') }}" submit-label="Update Resident" />
    </form>
</div>
@endsection
</x-app-layout>
