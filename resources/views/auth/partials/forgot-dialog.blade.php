    {{-- ============================================================ --}}
    {{-- Forgot-password dialog: step 1 email → step 2 code+password --}}
    {{-- Reuses the same routes as the standalone pages; the server   --}}
    {{-- returns JSON because the requests send Accept: application/json. --}}
    {{-- ============================================================ --}}
    <div id="forgot-modal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="forgot-title">
        <div id="forgot-backdrop" class="modal-backdrop absolute inset-0 bg-slate-950/60"></div>

        <div class="absolute inset-0 overflow-hidden">
            <div class="flex min-h-full items-center justify-center p-2 sm:p-4">
                <div id="forgot-panel" class="modal-panel relative flex max-h-[calc(100vh-1rem)] w-full max-w-md flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:max-h-[calc(100vh-2rem)]">

                {{-- Modal header --}}
                <div class="flex shrink-0 items-start justify-between border-b border-slate-200 px-6 py-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-white p-0.5 shadow-sm">
                            <img src="{{ asset('images/bidduang-seal-circle.png') }}" alt="Barangay Bidduang official seal" class="h-full w-full object-contain">
                        </div>
                        <div>
                            <h2 id="forgot-title" class="text-base font-semibold tracking-tight">Reset your password</h2>
                            <p class="text-xs text-slate-500">Barangay Management System</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        id="forgot-close"
                        aria-label="Close dialog"
                        class="flex h-11 w-11 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700"
                    >
                        <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Step 1: request a code --}}
                <div id="forgot-step-email" class="min-h-0 flex-1 overflow-y-auto px-6 py-6">
                    <p class="text-sm text-slate-500">
                        Enter your email and we'll send you a reset code.
                    </p>

                    <form id="forgot-email-form" class="mt-5 space-y-4" novalidate>
                        <div>
                            <label for="forgot-email" class="mb-1.5 block text-sm font-medium text-slate-700">Email address</label>
                            <input
                                id="forgot-email"
                                type="email"
                                required
                                autocomplete="username"
                                placeholder="you@example.com"
                                class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600"
                            >
                            <p id="forgot-email-error" class="mt-1.5 hidden text-sm text-red-600" role="alert"></p>
                        </div>

                        <button
                            type="submit"
                            id="forgot-send-btn"
                            class="btn btn-primary w-full disabled:bg-slate-300 disabled:text-slate-500"
                        >
                            Send Reset Code
                        </button>
                    </form>

                </div>

                {{-- Step 2: code + new password --}}
                <div id="forgot-step-code" class="hidden min-h-0 flex-1 overflow-y-auto px-6 py-6">
                    <p class="text-sm text-slate-500">
                        Code sent to <span id="forgot-masked" class="font-medium text-slate-800"></span>.
                        Expires in 15 minutes.
                    </p>

                    <form id="forgot-code-form" class="mt-5 space-y-4" novalidate>
                        <input type="hidden" id="forgot-email-held" value="">

                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                            <label class="block text-sm font-medium text-slate-700">Reset code</label>
                            <button
                                type="button"
                                id="forgot-paste-btn"
                                class="hidden text-sm font-medium text-red-600 hover:text-red-700"
                            >
                                Paste code
                            </button>
                        </div>
                            <div class="flex justify-between gap-2" id="forgot-code-boxes">
                                @for ($i = 0; $i < 6; $i++)
                                    <input
                                        type="text"
                                        inputmode="text"
                                        pattern="[23456789ABCDEFGHJKMNPQRSTUVWXYZ]"
                                        autocomplete="one-time-code"
                                        maxlength="1"
                                        aria-label="Code character {{ $i + 1 }}"
                                        class="forgot-code-box h-11 w-full rounded-lg border border-slate-300 text-center text-lg font-semibold uppercase focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600"
                                    >
                                @endfor
                            </div>
                            <input type="hidden" id="forgot-code" value="">
                            <p id="forgot-code-error" class="mt-1.5 hidden text-sm text-red-600" role="alert"></p>
                        </div>

                        <div>
                            <label for="forgot-password" class="mb-1.5 block text-sm font-medium text-slate-700">New password</label>
                            <div class="relative">
                                <input
                                    id="forgot-password"
                                    type="password"
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 pr-10 text-sm placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600"
                                >
                                <button
                                    type="button"
                                    data-password-toggle="forgot-password"
                                    aria-label="Show password"
                                    aria-pressed="false"
                                    class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded text-slate-500 transition-colors hover:text-slate-700 focus:outline-none"
                                >
                                    <svg class="icon-eye h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="icon-eye-off hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                                </button>
                            </div>
                            <p id="forgot-password-error" class="mt-1.5 hidden text-sm text-red-600" role="alert"></p>
                        </div>

                        <div>
                            <label for="forgot-password-confirmation" class="mb-1.5 block text-sm font-medium text-slate-700">Confirm new password</label>
                            <div class="relative">
                                <input
                                    id="forgot-password-confirmation"
                                    type="password"
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 pr-10 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600"
                                >
                                <button
                                    type="button"
                                    data-password-toggle="forgot-password-confirmation"
                                    aria-label="Show password"
                                    aria-pressed="false"
                                    class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded text-slate-500 transition-colors hover:text-slate-700 focus:outline-none"
                                >
                                    <svg class="icon-eye h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="icon-eye-off hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                                </button>
                            </div>
                        </div>

                        <button
                            type="submit"
                            id="forgot-reset-btn"
                            class="btn btn-primary w-full disabled:bg-slate-300 disabled:text-slate-500"
                        >
                            Reset Password
                        </button>
                    </form>

                    <p class="mt-4 border-t border-slate-200 pt-4 text-center text-sm text-slate-500">
                        Didn't get the code?
                        <button type="button" id="forgot-resend-btn" class="font-medium text-red-600 hover:text-red-700">
                            Send a new one
                        </button>
                    </p>
                </div>

                {{-- Step 3: success --}}
                <div id="forgot-step-done" class="hidden min-h-0 flex-1 overflow-y-auto px-6 py-10 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100">
                        <svg class="h-7 w-7 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold tracking-tight">Password updated</h3>
                    <p class="mt-1.5 text-sm text-slate-500">Sign in with your new password.</p>
                    <button
                        type="button"
                        id="forgot-done-btn"
                        class="mt-6 btn btn-primary w-full"
                    >
                        Back to sign in
                    </button>
                </div>
            </div>
            </div>
        </div>
    </div>
