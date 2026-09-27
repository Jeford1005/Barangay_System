<x-app-layout>
@section('page_header')
    <x-page-header title="Edit User Account" subtitle="{{ $user->email }}" />
@endsection

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="no-print mb-4 flex flex-wrap items-center justify-end rounded-xl border border-neutral-200 bg-white p-3 shadow-sm">
        <a href="{{ route('admin.users.show', $user) }}" class="inline-flex min-h-10 items-center rounded-lg px-2 text-sm font-medium text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500">← Back to account</a>
    </div>
    <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6">
    <h2 class="text-lg font-semibold text-neutral-900">Account details</h2>
    <p class="mt-1 text-sm text-neutral-500">Update the identity fields used for this account. Role and access changes are managed below on the account page.</p>

    <div class="mt-6">
        @include('admin.users._form', ['user' => $user])
    </div>
</div>
@endsection
</x-app-layout>
