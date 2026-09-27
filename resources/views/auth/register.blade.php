<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Create Account - {{ config('app.name', 'Barangay Management System') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/bidduang-mark.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="bg-neutral-100 text-neutral-900 antialiased">
    <main class="relative min-h-screen overflow-hidden bg-gradient-to-br from-neutral-900 via-neutral-900 to-blue-950 px-4 py-10 sm:px-6">
        <div class="pointer-events-none absolute -top-32 -right-32 h-96 w-96 rounded-full bg-blue-600/20 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-blue-600/10 blur-3xl" aria-hidden="true"></div>

        <div class="relative mx-auto w-full max-w-2xl">
            {{-- Brand lockup --}}
            <div class="mb-8 flex items-center justify-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-white p-1 shadow-lg shadow-blue-950/50">
                    <img src="{{ asset('images/bidduang-seal-circle.png') }}" alt="Barangay official seal" class="h-full w-full object-contain">
                </div>
                <div>
                    <p class="text-sm font-semibold tracking-tight text-white">Barangay Management System</p>
                    <p class="text-xs text-neutral-400">Office of the Barangay</p>
                </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white shadow-2xl">
                <div class="border-b border-neutral-100 px-6 py-5">
                    <h1 class="text-base font-semibold tracking-tight">Create your resident account</h1>
                    <p class="mt-0.5 text-xs text-neutral-400">
                        Applications are reviewed by the barangay office — you'll get an email once approved.
                    </p>
                </div>

                <div class="px-6 py-6">
                    @if (session('status'))
                        <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register') }}" class="space-y-4">
                        @csrf

                        {{-- Name row --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="first_name" class="mb-1.5 block text-sm font-medium text-neutral-700">First name *</label>
                                <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required maxlength="100"
                                    class="w-full rounded-lg border border-neutral-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500 @error('first_name') border-red-400 @enderror">
                                @error('first_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="last_name" class="mb-1.5 block text-sm font-medium text-neutral-700">Last name *</label>
                                <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required maxlength="100"
                                    class="w-full rounded-lg border border-neutral-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500 @error('last_name') border-red-400 @enderror">
                                @error('last_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="middle_name" class="mb-1.5 block text-sm font-medium text-neutral-700">Middle name</label>
                                <input id="middle_name" type="text" name="middle_name" value="{{ old('middle_name') }}" maxlength="100"
                                    class="w-full rounded-lg border border-neutral-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="suffix" class="mb-1.5 block text-sm font-medium text-neutral-700">Suffix</label>
                                <input id="suffix" type="text" name="suffix" value="{{ old('suffix') }}" maxlength="10" placeholder="Jr., III"
                                    class="w-full rounded-lg border border-neutral-300 px-3.5 py-2.5 text-sm placeholder-neutral-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>

                        {{-- Personal row --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <label for="birth_date" class="mb-1.5 block text-sm font-medium text-neutral-700">Birth date *</label>
                                <input id="birth_date" type="date" name="birth_date" value="{{ old('birth_date') }}" required min="1900-01-01" max="{{ now()->toDateString() }}"
                                    class="w-full rounded-lg border border-neutral-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500 @error('birth_date') border-red-400 @enderror">
                                @error('birth_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="sex" class="mb-1.5 block text-sm font-medium text-neutral-700">Sex *</label>
                                <select id="sex" name="sex" required
                                    class="w-full rounded-lg border border-neutral-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500 @error('sex') border-red-400 @enderror">
                                    <option value="">Select…</option>
                                    @foreach (['Male', 'Female', 'Other'] as $option)
                                        <option value="{{ $option }}" @selected(old('sex') === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                @error('sex') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="civil_status" class="mb-1.5 block text-sm font-medium text-neutral-700">Civil status *</label>
                                <select id="civil_status" name="civil_status" required
                                    class="w-full rounded-lg border border-neutral-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500 @error('civil_status') border-red-400 @enderror">
                                    <option value="">Select…</option>
                                    @foreach (['Single', 'Married', 'Divorced', 'Widowed', 'Separated'] as $option)
                                        <option value="{{ $option }}" @selected(old('civil_status') === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                @error('civil_status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- Contact row --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="phone_number" class="mb-1.5 block text-sm font-medium text-neutral-700">Phone number</label>
                                <input id="phone_number" type="tel" name="phone_number" value="{{ old('phone_number') }}" placeholder="09171234567" maxlength="15" inputmode="tel" data-phone="true" pattern="[0-9+()\- ]*"
                                    class="w-full rounded-lg border border-neutral-300 px-3.5 py-2.5 text-sm placeholder-neutral-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @error('phone_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="email" class="mb-1.5 block text-sm font-medium text-neutral-700">Email address *</label>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="150" autocomplete="username"
                                    placeholder="you@example.com"
                                    class="w-full rounded-lg border border-neutral-300 px-3.5 py-2.5 text-sm placeholder-neutral-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500 @error('email') border-red-400 @enderror">
                                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- Location row --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="purok_id" class="mb-1.5 block text-sm font-medium text-neutral-700">Purok</label>
                                <select id="purok_id" name="purok_id"
                                    class="w-full rounded-lg border border-neutral-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select purok…</option>
                                    @foreach ($puroks as $purok)
                                        <option value="{{ $purok->id }}" @selected(old('purok_id') == $purok->id)>{{ $purok->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="household_id" class="mb-1.5 block text-sm font-medium text-neutral-700">Household</label>
                                <select id="household_id" name="household_id"
                                    class="w-full rounded-lg border border-neutral-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select household…</option>
                                    @foreach ($households as $household)
                                        <option value="{{ $household->id }}" @selected(old('household_id') == $household->id)>
                                            {{ $household->household_code }}@if($household->street) — {{ $household->street }}@endif
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-neutral-400">Leave blank if your household isn't listed yet.</p>
                            </div>
                        </div>

                        <div>
                            <label for="address" class="mb-1.5 block text-sm font-medium text-neutral-700">Home address *</label>
                            <input id="address" type="text" name="address" value="{{ old('address') }}" required maxlength="255"
                                placeholder="House no., street, sitio"
                                class="w-full rounded-lg border border-neutral-300 px-3.5 py-2.5 text-sm placeholder-neutral-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500 @error('address') border-red-400 @enderror">
                            @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Password row --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="password" class="mb-1.5 block text-sm font-medium text-neutral-700">Password *</label>
                                <div class="relative">
                                    <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password"
                                        class="w-full rounded-lg border border-neutral-300 px-3.5 py-2.5 pr-10 text-sm placeholder-neutral-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500 @error('password') border-red-400 @enderror">
                                    <button type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false"
                                        class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded text-neutral-400 transition-colors hover:text-neutral-700 focus:outline-none">
                                        <svg class="icon-eye h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                        <svg class="icon-eye-off hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                                    </button>
                                </div>
                                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-neutral-700">Confirm password *</label>
                                <div class="relative">
                                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                                        class="w-full rounded-lg border border-neutral-300 px-3.5 py-2.5 pr-10 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <button type="button" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false"
                                        class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded text-neutral-400 transition-colors hover:text-neutral-700 focus:outline-none">
                                        <svg class="icon-eye h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                        <svg class="icon-eye-off hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button type="submit"
                            class="w-full rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                            Submit Application
                        </button>
                    </form>

                    <p class="mt-5 border-t border-neutral-100 pt-4 text-center text-sm text-neutral-500">
                        Already approved?
                        <a href="{{ route('login') }}" class="font-medium text-blue-600 hover:text-blue-700">Sign in</a>
                    </p>
                </div>
            </div>

            <p class="mt-6 text-center text-xs text-neutral-500">
                &copy; {{ date('Y') }} {{ config('app.name', 'Barangay Management System') }}
            </p>
        </div>
            </div>
    </main>

    @include('auth.partials.password-toggle')
</body>
</html>
