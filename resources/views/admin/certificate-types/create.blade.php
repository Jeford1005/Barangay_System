<x-app-layout>
@section('page_header')
    <x-page-header title="New document type" subtitle="Add a governed certificate or clearance definition for future requests." />
@endsection
@section('content')
<div class="mx-auto max-w-3xl"><div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">@include('admin.certificate-types._form')</div></div>
@endsection
</x-app-layout>
