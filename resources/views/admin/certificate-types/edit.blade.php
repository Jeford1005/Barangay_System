<x-app-layout>
@section('page_header')
    <x-page-header title="Edit document type" subtitle="Changes affect future requests and issuances; historical snapshots remain unchanged." />
@endsection
@section('content')
<div class="mx-auto max-w-3xl">
    <x-module-tabs type="certificates" class="mb-4" />
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        @include('admin.certificate-types._form', ['document' => $document])
    </div>
</div>
@endsection
</x-app-layout>
