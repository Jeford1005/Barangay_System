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

    {{-- Recovery codes are flashed exactly once, right after enrollment. They
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
            <h2 class="text-sm font-semibold text-slate-900">Status: enabled</h2>
            <p class="mt-1 text-sm text-slate-500">Sign-in asks for a 6-digit code from your authenticator app after your password.</p>

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
                <button type="submit" class="btn btn-danger">Disable two-factor authentication</button>
            </form>
        </div>
    @elseif (! empty($pendingSecret))
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
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
    @else
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">Status: disabled</h2>
            <p class="mt-1 text-sm text-slate-500">When enabled, sign-in asks for a 6-digit code from your authenticator app after your password.</p>
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
