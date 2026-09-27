<x-app-layout>
@section('page_header')
    <x-page-header title="Mail Health Check" subtitle="Verifies the setup that password-reset emails depend on. Current driver: {{ $report['mailer'] }}" />
@endsection

@section('content')
<x-settings-shell current="mail">
<div class="max-w-3xl mx-auto">
    <div class="no-print mb-4 flex flex-wrap items-center justify-end rounded-xl border border-neutral-200 bg-white p-3 shadow-sm">
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold
            {{ $report['ready'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
            {{ $report['ready'] ? 'Ready' : 'Needs attention' }}
        </span>
    </div>
    <div class="bg-white rounded-xl shadow overflow-hidden">

        <div class="p-6">
            <ol class="space-y-3">
                @foreach ($report['checks'] as $check)
                    <li class="flex items-start gap-3 rounded-lg border p-4
                        {{ $check['status'] === 'pass' ? 'border-green-200 bg-green-50' : ($check['status'] === 'warn' ? 'border-yellow-200 bg-yellow-50' : 'border-red-200 bg-red-50') }}">
                        @if ($check['status'] === 'pass')
                            <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0 text-green-600" />
                        @elseif ($check['status'] === 'warn')
                            <x-icon name="exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-yellow-600" />
                        @else
                            <x-icon name="x-circle" class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
                        @endif

                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900">{{ $check['label'] }}</p>
                            <p class="text-sm text-gray-600">{{ $check['detail'] }}</p>
                            @if ($check['hint'] ?? null)
                                <p class="mt-1 text-xs text-yellow-800 bg-yellow-100 rounded px-2 py-1 inline-block">
                                    {{ $check['hint'] }}
                                </p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>

            <div class="mt-6 border-t border-gray-200 pt-6">
                <h3 class="text-sm font-semibold text-gray-900">Send a test email</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Confirms delivery end-to-end, exactly as a password-reset email would travel.
                    With the log driver, the message appears in <code class="text-xs bg-gray-100 px-1 rounded">storage/logs/laravel.log</code>.
                </p>
                <form action="{{ route('admin.mail.test') }}" method="POST" class="mt-3 flex flex-col gap-2 sm:flex-row">
                    @csrf
                    <label for="mail-test-recipient" class="sr-only">Test email recipient</label>
                    <input
                        id="mail-test-recipient"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="recipient@example.com"
                        class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                    <button type="submit" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                        Send Test Email
                    </button>
                </form>
                @error('email')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-6 rounded-lg bg-neutral-900 p-5 text-sm text-neutral-300">
                <p class="font-semibold text-white">Gmail quick setup</p>
                <ol class="mt-2 list-decimal list-inside space-y-1 text-xs leading-relaxed">
                    <li>Enable 2-Step Verification on the sending Google account</li>
                    <li>Create an App Password at <span class="text-blue-300">myaccount.google.com/apppasswords</span> (16 characters)</li>
                    <li>In <code>.env</code>: <code>MAIL_MAILER=smtp</code>, <code>MAIL_HOST=smtp.gmail.com</code>, <code>MAIL_PORT=587</code></li>
                    <li><code>MAIL_USERNAME</code> and <code>MAIL_FROM_ADDRESS</code> = the same Gmail address, <code>MAIL_PASSWORD</code> = App Password</li>
                    <li>Restart <code>php artisan serve</code>, then re-run this check</li>
                </ol>
            </div>
        </div>
    </div>
</div>
</x-settings-shell>
@endsection
</x-app-layout>
