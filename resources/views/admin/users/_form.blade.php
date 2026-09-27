<form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-4">
    @csrf
    @method('PATCH')

    <div>
        <label for="account-name" class="mb-1 block text-sm font-medium text-neutral-700">Name</label>
        <input id="account-name" name="name" type="text" value="{{ old('name', $user->name) }}" required maxlength="255" class="min-h-11 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="account-email" class="mb-1 block text-sm font-medium text-neutral-700">Email address</label>
        <input id="account-email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="150" autocomplete="email" class="min-h-11 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
        <p class="mt-1 text-xs text-neutral-500">Changing the email signs the account out and invalidates reset codes.</p>
        @error('email')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex justify-end gap-2">
        <a href="{{ route('admin.users.show', $user) }}" class="inline-flex min-h-11 items-center rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-blue-500">Cancel</a>
        <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">Save changes</button>
    </div>
</form>
