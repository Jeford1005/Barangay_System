<x-app-layout>
@section('page_header')
    <x-page-header title="Add account" subtitle="Create a sign-in account with any access tier">
        <x-slot:actions>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline">All accounts</a>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
<x-settings-shell>
<div class="space-y-4">
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4 p-6">
            @csrf

            <x-form.field name="name" label="Full name" required maxlength="100" :value="old('name')" autocomplete="name" />
            <x-form.field name="email" label="Email address" type="email" required maxlength="150" :value="old('email')" autocomplete="username" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-form.field name="password" label="Password" type="password" required :optionalHint="false" autocomplete="new-password" />
                <x-form.field name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />
            </div>
            <p class="text-xs text-slate-500">At least 12 characters with letters and numbers. Share it securely — the new holder should change it after first sign-in.</p>

            <x-form.field name="user_type" label="Access tier" type="select" required :value="old('user_type', 'staff')" :options="['admin' => 'Administrator', 'staff' => 'Staff', 'official' => 'Official', 'resident' => 'Resident']" />
            <p class="text-xs text-slate-500">Administrators can manage everything, including other administrators.</p>

            <x-form.actions :cancelHref="route('admin.users.index')" submitLabel="Create account" />
        </form>
    </div>
</div>
</x-settings-shell>
@endsection
</x-app-layout>
