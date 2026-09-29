<x-app-layout>
@section('page_header')
    <x-page-header title="Edit User Account" subtitle="{{ $user->email }}">
        <x-slot:actions>
            <a href="{{ route('admin.users.show', $user) }}" class="inline-flex min-h-11 items-center rounded-lg px-2 text-sm font-medium text-sky-700 hover:bg-sky-50 focus:outline-none focus:ring-2 focus:ring-sky-600">← Back to account</a>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <h2 class="text-lg font-semibold text-slate-900">Account details</h2>
    <p class="mt-1 text-sm text-slate-500">Update the identity fields used for this account. Role and access changes are managed below on the account page.</p>

    <div class="mt-6">
        @include('admin.users._form', ['user' => $user])
    </div>
</div>
@endsection
</x-app-layout>
