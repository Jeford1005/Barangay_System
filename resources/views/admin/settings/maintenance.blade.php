<x-app-layout>
@section('page_header')
    <x-page-header title="System Maintenance" subtitle="Backups, service status, and system requirements" />
@endsection

@section('content')
<x-settings-shell>
    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="border-b border-slate-200 pb-7">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">System status</p>
                <h2 class="mt-2 text-xl font-semibold tracking-tight text-slate-900">Runtime overview</h2>
                <p class="mt-1 text-sm text-slate-500">Read-only checks for the services this application depends on.</p>
            </div>
            <form method="POST" action="{{ route('admin.settings.cache.clear') }}" data-confirm="Clear the application and view caches?" data-confirm-title="Clear caches" data-confirm-accept="Clear" data-confirm-icon="cog-6-tooth" data-confirm-tone="primary">
                @csrf
                <button type="submit" class="inline-flex min-h-11 items-center text-sm font-medium text-slate-600 hover:text-sky-700 hover:underline focus:outline-none focus:ring-2 focus:ring-sky-600">Clear caches</button>
            </form>
        </div>

        <dl class="mt-6 divide-y divide-slate-200 border-y border-slate-200">
            <div class="flex items-center justify-between gap-4 py-3"><dt class="text-sm text-slate-500">Environment</dt><dd class="text-sm font-medium text-slate-900">{{ ucfirst($system['environment']) }}</dd></div>
            <div class="flex items-center justify-between gap-4 py-3"><dt class="text-sm text-slate-500">PHP / Laravel</dt><dd class="text-sm font-medium text-slate-900">{{ $system['php_version'] }} / {{ $system['laravel_version'] }}</dd></div>
            <div class="flex items-center justify-between gap-4 py-3"><dt class="text-sm text-slate-500">Database</dt><dd class="text-sm font-medium text-slate-900">{{ ucfirst($system['database']) }} <span class="{{ $system['database_connected'] ? 'text-emerald-700' : 'text-red-700' }}">· {{ $system['database_connected'] ? 'Connected' : 'Unavailable' }}</span></dd></div>
            <div class="flex items-center justify-between gap-4 py-3"><dt class="text-sm text-slate-500">Mail driver</dt><dd class="text-sm font-medium text-slate-900">{{ ucfirst($system['mail']) }} · <a href="{{ route('admin.mail.health') }}" class="font-medium text-sky-700 hover:underline">Check mail</a></dd></div>
            <div class="flex items-center justify-between gap-4 py-3"><dt class="text-sm text-slate-500">Cache / Session / Queue</dt><dd class="text-sm font-medium text-slate-900">{{ ucfirst($system['cache']) }} / {{ ucfirst($system['session']) }} / {{ ucfirst($system['queue']) }}</dd></div>
            <div class="flex items-center justify-between gap-4 py-3"><dt class="text-sm text-slate-500">Storage free</dt><dd class="text-sm font-medium text-slate-900">{{ $system['storage_free'] === null ? 'Unknown' : number_format($system['storage_free'] / 1024 / 1024, 1).' MB' }}</dd></div>
            <div class="flex items-center justify-between gap-4 py-3"><dt class="text-sm text-slate-500">Cache probe</dt><dd class="text-sm font-medium {{ $health['cache_connected'] ? 'text-emerald-700' : 'text-red-700' }}">{{ $health['cache_connected'] ? 'Ready' : 'Unavailable' }}</dd></div>
            <div class="flex items-center justify-between gap-4 py-3"><dt class="text-sm text-slate-500">Storage writable</dt><dd class="text-sm font-medium {{ $health['storage_writable'] ? 'text-emerald-700' : 'text-red-700' }}">{{ $health['storage_writable'] ? 'Yes' : 'No' }}</dd></div>
            <div class="flex items-center justify-between gap-4 py-3"><dt class="text-sm text-slate-500">Pending migrations</dt><dd class="text-sm font-medium {{ is_countable($health['pending_migrations']) && count($health['pending_migrations']) > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ is_countable($health['pending_migrations']) ? count($health['pending_migrations']) : 'Unknown' }}</dd></div>
            <div class="flex items-center justify-between gap-4 py-3"><dt class="text-sm text-slate-500">Queue heartbeat</dt><dd class="text-sm font-medium {{ $health['queue_heartbeat_at'] ? 'text-emerald-700' : 'text-amber-700' }}">{{ $health['queue_heartbeat_at'] ? date('M j, Y g:i A', $health['queue_heartbeat_at']) : 'No recent job heartbeat' }}</dd></div>
            <div class="flex items-center justify-between gap-4 py-3"><dt class="text-sm text-slate-500">Queue / failed jobs</dt><dd class="text-sm font-medium {{ ($health['failed_jobs'] ?? 0) > 0 ? 'text-red-700' : 'text-slate-900' }}">{{ $health['queued_jobs'] }} queued · {{ $health['failed_jobs'] ?? 'unknown' }} failed</dd></div>
            <div class="flex items-center justify-between gap-4 py-3"><dt class="text-sm text-slate-500">Last backup</dt><dd class="text-sm font-medium text-slate-900">{{ $health['last_backup'] ? date('M j, Y g:i A', $health['last_backup']['created_at']) : 'None' }}</dd></div>
            <div class="flex items-center justify-between gap-4 py-3"><dt class="text-sm text-slate-500">Last backup run</dt><dd class="text-sm font-medium {{ data_get($health, 'last_backup_run.status') === 'Failed' ? 'text-red-700' : 'text-slate-900' }}">{{ data_get($health, 'last_backup_run.status') ?? 'None' }}{{ data_get($health, 'last_backup_run.error_message') ?? '' }}</dd></div>
        </dl>
    </section>

    <section class="border-b border-slate-200 py-7">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-700">Recovery</p>
                <h2 class="mt-2 text-xl font-semibold tracking-tight text-slate-900">Database backups</h2>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">Backups run through the application queue and are stored outside the public folder. The newest 10 are retained automatically; download and keep an additional copy in a secure location.</p>
                <p class="mt-1 text-xs text-slate-500">Queue processing must be running with <code>php artisan queue:work</code> (the development Composer script starts it automatically).</p>
            </div>
            <form method="POST" action="{{ route('admin.settings.backups.store') }}">
                @csrf
                <button type="submit" class="btn btn-primary">Create backup</button>
            </form>
        </div>

        @if (! in_array(config('database.default'), ['sqlite', 'mysql', 'mariadb'], true))
            <p class="mt-5 border-l-2 border-amber-400 bg-amber-50 p-3 text-sm text-amber-800">Automatic backup creation is not supported for the "{{ config('database.default') }}" driver. Configure a database-specific backup job before using it.</p>
        @endif

        <div class="mt-5 overflow-x-auto border-y border-slate-200">
            @if (count($backups) === 0)
                <p class="py-8 text-center text-sm text-slate-500">No backups have been created yet.</p>
            @else
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <caption class="sr-only">Database backups</caption>
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-3 py-3 font-medium">File</th>
                            <th scope="col" class="px-3 py-3 font-medium">Created</th>
                            <th scope="col" class="px-3 py-3 font-medium">Size</th>
                            <th scope="col" class="no-print px-3 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($backups as $backup)
                            <tr>
                                <td class="px-3 py-3 font-medium text-slate-800">{{ $backup['name'] }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ date('M j, Y g:i A', $backup['created_at']) }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ number_format($backup['size'] / 1024, 1) }} KB</td>
                                <td class="no-print px-3 py-3 text-right">
                                    <div class="inline-flex gap-2">
                                        <a href="{{ route('admin.settings.backups.download', $backup['name']) }}" class="text-xs font-medium text-sky-700 hover:underline">Download</a>
                                        <form method="POST" action="{{ route('admin.settings.backups.destroy', $backup['name']) }}" data-confirm="Delete this backup?" data-confirm-title="Delete backup" data-confirm-accept="Delete" data-confirm-icon="trash">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-medium text-red-700 hover:underline">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
        <p class="mt-4 text-xs leading-5 text-slate-500">Restore is intentionally performed through a controlled server-side operation, not a casual browser action. Keep at least one verified off-server copy.</p>
    </section>

    <section class="pt-7">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Deployment reference</p>
        <h2 class="mt-2 text-xl font-semibold tracking-tight text-slate-900">System requirements</h2>
        <dl class="mt-5 divide-y divide-slate-200 border-y border-slate-200">
            <div class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"><dt class="text-sm text-slate-500">Application runtime</dt><dd class="text-sm font-medium text-slate-900">PHP 8.2+ with Laravel 12</dd></div>
            <div class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"><dt class="text-sm text-slate-500">Frontend build</dt><dd class="text-sm font-medium text-slate-900">Node.js 18+ with npm</dd></div>
            <div class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"><dt class="text-sm text-slate-500">Database</dt><dd class="text-sm font-medium text-slate-900">SQLite for small deployments; MySQL/PostgreSQL for production</dd></div>
            <div class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"><dt class="text-sm text-slate-500">Production baseline</dt><dd class="text-sm font-medium text-slate-900">HTTPS, scheduled backups, restricted storage permissions</dd></div>
        </dl>
    </section>
</x-settings-shell>
@endsection
</x-app-layout>
