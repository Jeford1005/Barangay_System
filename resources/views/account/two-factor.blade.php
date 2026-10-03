<x-app-layout>
@section('page_header')
    <x-page-header title="Two-factor authentication" subtitle="Extra sign-in protection for your office account." />
@endsection

@section('content')
<div class="mx-auto w-full max-w-2xl space-y-4">
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
            {{ session('status') }}
        </div>
    @endif

    {{-- Recovery codes are flashed exactly once, right after app enrollment. They
         are never persisted in plaintext, so this render is the only copy. --}}
    @if (! empty($recoveryCodes))
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-5">
            <h2 class="text-sm font-semibold text-amber-900">Save your recovery codes</h2>
            <p class="mt-1 text-sm text-amber-800">Each code signs you in once if your authenticator app is unavailable. They will not be shown again.</p>
            <ul class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach ($recoveryCodes as $code)
                    <li class="rounded-lg border border-amber-200 bg-white px-3 py-2 text-center font-mono text-sm font-semibold tracking-wider text-slate-900">{{ $code }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($enabled)
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">
                Status: enabled{{ ($isPaper ?? false) ? ' · printed login card' : ' · authenticator app' }}
            </h2>
            @if ($isPaper ?? false)
                <p class="mt-1 text-sm text-slate-500">Sign-in asks for the next unused code from your printed login card after your password. Each code works once.</p>
                <p class="mt-2 text-sm font-medium {{ ($lowCodes ?? false) ? 'text-amber-700' : 'text-slate-700' }}">
                    {{ $remainingCodes }} of 8 login codes left on this card.
                </p>
                @if ($lowCodes ?? false)
                    <div class="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
                        <p class="font-semibold">Running low — re-enroll soon.</p>
                        <p class="mt-1">When the card is empty you cannot sign in without the email backup an administrator enables. Disable below with a remaining code, then enroll again to print a fresh card.</p>
                    </div>
                @endif
            @else
                <p class="mt-1 text-sm text-slate-500">Sign-in asks for a 6-digit code from your authenticator app after your password.</p>
            @endif
            @if ($emailFallback ?? false)
                <p class="mt-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">Email backup sign-in is enabled for this account by an administrator: the login step sends a one-time code to your email instead.</p>
            @endif

            <form method="POST" action="{{ route('two-factor.destroy') }}" class="mt-4 space-y-3"
                data-confirm="Disable two-factor authentication on this account?"
                data-confirm-title="Disable two-factor authentication"
                data-confirm-accept="Disable" data-confirm-dismiss="Keep" data-confirm-icon="shield-exclamation">
                @csrf
                @method('DELETE')
                <div>
                    <label for="two-factor-password" class="mb-1.5 block text-sm font-medium text-slate-700">Current password</label>
                    <input id="two-factor-password" type="password" name="password" required autocomplete="current-password"
                        class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('password') border-red-500! ring-1 ring-red-500/40 @enderror">
                    @error('password')
                        <p class="mt-1.5 text-[13px] text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="two-factor-disable-code" class="mb-1.5 block text-sm font-medium text-slate-700">{{ ($isPaper ?? false) ? 'Next unused login-card code' : 'Authenticator code or recovery code' }}</label>
                    <input id="two-factor-disable-code" type="text" name="code" required inputmode="text" autocomplete="one-time-code" maxlength="64" placeholder="{{ ($isPaper ?? false) ? 'XXXXX-XXXXX' : '123456' }}"
                        value="{{ old('code') }}"
                        class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('code') border-red-500! ring-1 ring-red-500/40 @enderror">
                    @error('code')
                        <p class="mt-1.5 text-[13px] text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="btn btn-danger">Disable two-factor authentication</button>
            </form>
        </div>
    @elseif (! empty($pendingPaperCodes) || ! empty($pendingSecret))
        {{-- Paper-first enrollment: the printable card is the primary path. The
             pending codes live server-side in the session until confirmed. --}}
        @if (! empty($pendingPaperCodes))
            <style>
                @media print {
                    body * { visibility: hidden; }
                    #login-card, #login-card * { visibility: visible; }
                    #login-card { position: absolute; left: 0; top: 0; width: 100%; border: none !important; box-shadow: none !important; }
                    .no-print { display: none !important; }
                }
            </style>
            <div id="login-card" class="rounded-xl border-2 border-slate-900 bg-white p-6 shadow-sm">
                <h2 class="text-base font-bold text-slate-900">Login card · {{ config('app.name', 'Barangay Management System') }}</h2>
                <p class="mt-1 text-sm text-slate-700">Cardholder: <span class="font-semibold">{{ auth()->user()->name }}</span> · {{ auth()->user()->email }}</p>
                <p class="mt-1 text-sm text-slate-700">Enter the <span class="font-semibold">next unused code</span> from this card at each sign-in, after your password. Each code works once — cross used codes out with a pen.</p>
                <ol class="mt-4 space-y-2">
                    @foreach ($pendingPaperCodes as $index => $code)
                        <li class="flex items-baseline gap-3 rounded-lg border border-slate-300 px-4 py-2">
                            <span class="w-6 shrink-0 text-sm font-semibold text-slate-500">{{ $index + 1 }}.</span>
                            <span class="font-mono text-2xl font-bold tracking-[0.2em] text-slate-900 sm:text-3xl">{{ $code }}</span>
                        </li>
                    @endforeach
                </ol>
                <p class="mt-3 text-xs text-slate-500">Issued {{ now()->format('M j, Y g:i A') }} · Keep this card where only you can reach it. If it is lost, ask an administrator for help before the codes run out.</p>
            </div>

            <div class="no-print rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <button type="button" onclick="window.print()" class="btn btn-outline w-full sm:w-auto">Print this card</button>
                <form method="POST" action="{{ route('two-factor.paper-confirm') }}" class="mt-4 space-y-3">
                    @csrf
                    <div class="flex items-start gap-2">
                        <input id="two-factor-printed" type="checkbox" name="printed" value="1" required class="mt-1 h-4 w-4 rounded border-slate-300">
                        <label for="two-factor-printed" class="text-sm font-medium text-slate-700">The card is printed and stored safely.</label>
                    </div>
                    @error('printed')
                        <p class="text-[13px] text-red-600">{{ $message }}</p>
                    @enderror
                    <div>
                        <label for="two-factor-witness" class="mb-1.5 block text-sm font-medium text-slate-700">Staff witness name</label>
                        <input id="two-factor-witness" type="text" name="witness" required maxlength="100" placeholder="Staff member who saw the card printed"
                            value="{{ old('witness') }}"
                            class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('witness') border-red-500! ring-1 ring-red-500/40 @enderror">
                        @error('witness')
                            <p class="mt-1.5 text-[13px] text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">Finish enabling with printed card</button>
                </form>
            </div>
        @endif

        {{-- Opt-in authenticator path: unchanged QR/manual-key + TOTP confirm,
             offered as the secondary choice for staff with a smartphone. --}}
        @if (! empty($pendingSecret))
            <details class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <summary class="cursor-pointer text-sm font-semibold text-sky-700 hover:underline">Prefer an authenticator app?</summary>
                <div class="mt-4">
                    <h2 class="text-sm font-semibold text-slate-900">Step 2 of 2 · link your authenticator app</h2>
                    <p class="mt-1 text-sm text-slate-500">Add this account to your authenticator app, then enter the 6-digit code it shows to finish.</p>

                    <div class="mt-4">
                        <p class="text-sm font-medium text-slate-700">Setup address (paste into your app)</p>
                        <p class="mt-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs break-all text-slate-800">{{ $provisioningUri }}</p>
                    </div>

                    <div class="mt-3">
                        <p class="text-sm font-medium text-slate-700">Manual key</p>
                        <p class="mt-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm font-semibold tracking-widest text-slate-900">{{ $pendingSecret }}</p>
                    </div>

                    <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-4 space-y-3">
                        @csrf
                        <div>
                            <label for="two-factor-code" class="mb-1.5 block text-sm font-medium text-slate-700">6-digit code</label>
                            <input id="two-factor-code" type="text" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="123456"
                                value="{{ old('code') }}"
                                class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('code') border-red-500! ring-1 ring-red-500/40 @enderror">
                            @error('code')
                                <p class="mt-1.5 text-[13px] text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary">Confirm and enable</button>
                    </form>
                </div>
            </details>
        @endif
    @else
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">Status: disabled</h2>
            <p class="mt-1 text-sm text-slate-500">Recommended: a printed login card — no smartphone needed. After your password, sign-in asks for the next code from the card. Prefer an authenticator app instead? That option is offered after you start.</p>
            <form method="POST" action="{{ route('two-factor.enroll') }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label for="two-factor-enroll-password" class="mb-1.5 block text-sm font-medium text-slate-700">Current password</label>
                    <input id="two-factor-enroll-password" type="password" name="password" required autocomplete="current-password"
                        class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('password') border-red-500! ring-1 ring-red-500/40 @enderror">
                    @error('password')
                        <p class="mt-1.5 text-[13px] text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary">Enable two-factor authentication</button>
            </form>
        </div>
    @endif
</div>
@endsection
</x-app-layout>
