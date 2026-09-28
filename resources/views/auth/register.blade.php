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
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    {{-- The seal, oversized and held at a whisper — the same backdrop the
         sign-in page opens on, so every unauthenticated route reads as one
         system. Fixed, so it stays put when a long form scrolls. --}}
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 z-0 flex items-center justify-center overflow-hidden">
        <img src="{{ asset('images/bidduang-seal-circle.png') }}" alt=""
            class="h-auto w-[min(150vmin,1150px)] max-w-none select-none opacity-[0.055]">
    </div>

    <main class="relative z-10 flex min-h-screen items-center justify-center px-4 py-10 sm:px-6">
        <div class="mx-auto w-full max-w-2xl">
            <div class="rounded-xl border border-slate-200 bg-white shadow-xl shadow-slate-900/5">
                <div class="flex items-center gap-3 border-b border-slate-200 px-6 py-5">
                    <img src="{{ asset('images/bidduang-seal-circle.png') }}" alt="" class="h-10 w-10 shrink-0 rounded-full">
                    <div>
                        <h1 class="text-base font-semibold tracking-tight text-slate-900">Create your resident account</h1>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Applications are reviewed by the barangay office — you'll get an email once approved.
                        </p>
                    </div>
                </div>

                <div class="px-6 py-6">
                    @if (session('status'))
                        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register') }}" class="space-y-4">
                        @csrf

                        {{-- Name row --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="first_name" class="mb-1.5 block text-sm font-medium text-slate-700">First name *</label>
                                <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required maxlength="100"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('first_name') border-red-500! @enderror">
                                @error('first_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="last_name" class="mb-1.5 block text-sm font-medium text-slate-700">Last name *</label>
                                <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required maxlength="100"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('last_name') border-red-500! @enderror">
                                @error('last_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="middle_name" class="mb-1.5 block text-sm font-medium text-slate-700">Middle name</label>
                                <input id="middle_name" type="text" name="middle_name" value="{{ old('middle_name') }}" maxlength="100"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                            </div>
                            <div>
                                <label for="suffix" class="mb-1.5 block text-sm font-medium text-slate-700">Suffix</label>
                                <input id="suffix" type="text" name="suffix" value="{{ old('suffix') }}" maxlength="10" placeholder="Jr., III"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                            </div>
                        </div>

                        {{-- Personal row --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <label for="birth_date" class="mb-1.5 block text-sm font-medium text-slate-700">Birth date *</label>
                                <input id="birth_date" type="date" name="birth_date" value="{{ old('birth_date') }}" required min="1900-01-01" max="{{ now()->toDateString() }}"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('birth_date') border-red-500! @enderror">
                                @error('birth_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="sex" class="mb-1.5 block text-sm font-medium text-slate-700">Sex *</label>
                                <select id="sex" name="sex" required
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('sex') border-red-500! @enderror">
                                    <option value="">Select…</option>
                                    @foreach (['Male', 'Female', 'Other'] as $option)
                                        <option value="{{ $option }}" @selected(old('sex') === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                @error('sex') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="civil_status" class="mb-1.5 block text-sm font-medium text-slate-700">Civil status *</label>
                                <select id="civil_status" name="civil_status" required
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('civil_status') border-red-500! @enderror">
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
                                <label for="phone_number" class="mb-1.5 block text-sm font-medium text-slate-700">Phone number</label>
                                <input id="phone_number" type="tel" name="phone_number" value="{{ old('phone_number') }}" placeholder="09171234567" maxlength="15" inputmode="tel" data-phone="true" pattern="[0-9+()\- ]*"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                                @error('phone_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email address *</label>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="150" autocomplete="username"
                                    placeholder="you@example.com"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('email') border-red-500! @enderror">
                                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- Location row --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="purok_id" class="mb-1.5 block text-sm font-medium text-slate-700">Purok</label>
                                <select id="purok_id" name="purok_id"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                                    <option value="">Select purok…</option>
                                    @foreach ($puroks as $purok)
                                        <option value="{{ $purok->id }}" @selected(old('purok_id') == $purok->id)>{{ $purok->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="household_id" class="mb-1.5 block text-sm font-medium text-slate-700">Household</label>
                                <select id="household_id" name="household_id"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                                    <option value="">Select household…</option>
                                    @foreach ($households as $household)
                                        <option value="{{ $household->id }}" @selected(old('household_id') == $household->id)>
                                            {{ $household->household_code }}@if($household->street) — {{ $household->street }}@endif
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-slate-500">Leave blank if your household isn't listed yet.</p>
                            </div>
                        </div>

                        <div>
                            <label for="address" class="mb-1.5 block text-sm font-medium text-slate-700">Home address *</label>
                            <input id="address" type="text" name="address" value="{{ old('address') }}" required maxlength="255"
                                placeholder="House no., street, sitio"
                                class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('address') border-red-500! @enderror">
                            @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Password row --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Password *</label>
                                <div class="relative">
                                    <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password"
                                        class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 pr-10 text-sm placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('password') border-red-500! @enderror">
                                    <button type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false"
                                        class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded text-slate-500 transition-colors hover:text-slate-700 focus:outline-none">
                                        <svg class="icon-eye h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                        <svg class="icon-eye-off hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                                    </button>
                                </div>
                                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-700">Confirm password *</label>
                                <div class="relative">
                                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                                        class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 pr-10 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                                    <button type="button" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false"
                                        class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded text-slate-500 transition-colors hover:text-slate-700 focus:outline-none">
                                        <svg class="icon-eye h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                        <svg class="icon-eye-off hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button type="submit"
                            class="w-full rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2">
                            Submit Application
                        </button>
                    </form>

                    <p class="mt-5 border-t border-slate-200 pt-4 text-center text-sm text-slate-500">
                        Already approved?
                        <a href="{{ route('login') }}" class="font-medium text-sky-700 hover:text-sky-800">Sign in</a>
                    </p>
                </div>
            </div>

            <p class="mt-6 text-center text-xs text-slate-500">
                &copy; {{ date('Y') }} {{ config('app.name', 'Barangay Management System') }}
            </p>
        </div>
    </main>

    @include('auth.partials.password-toggle')
</body>
</html>
