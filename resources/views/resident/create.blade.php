<x-app-layout>
@section('page_header')
    <x-page-header title="Register Resident" />
@endsection

@section('content')
{{-- Form card: also the dialog fragment (fetched with ?fragment=1). --}}
<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ request()->boolean('fragment') ? 'max-w-none rounded-none border-0 shadow-none' : 'max-w-4xl mx-auto' }}">
    @unless(request()->boolean('fragment'))
        <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
            <h2 class="font-semibold text-slate-900">Resident details</h2>
            <p class="mt-1 text-sm text-slate-500">Record the resident’s identity, contact, and location information.</p>
        </div>
    @endunless
    <form action="{{ route('residents.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6 {{ request()->boolean('fragment') ? 'p-0' : 'p-6' }}">
        @csrf
        @include('resident._form', ['resident' => null])

        <x-form.actions cancel-href="{{ route('residents.index') }}" submit-label="Register Resident" />
    </form>
</div>
@endsection
</x-app-layout>
