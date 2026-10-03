{{-- Shared fields for cleanup create/edit. Expects $drive (null on create) and $puroks. --}}
<fieldset>
    <legend class="text-sm font-semibold text-slate-900 mb-4">Drive Details</legend>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <x-form.field name="title" label="Drive Title" required :value="$drive->title ?? null" maxlength="100" placeholder="e.g. Coastal Cleanup — Purok 3" />
        </div>
        <div class="sm:col-span-2">
            <x-form.field name="description" label="Description" type="textarea" :rows="3" maxlength="2000" optional-hint :value="$drive->description ?? null" placeholder="Meeting point, what to bring, areas to cover…" />
        </div>
        <x-form.field name="purok_id" label="Purok" type="select" optional-hint :options="$puroks" placeholder-option="All puroks (barangay-wide)" :value="$drive->purok_id ?? null" />
        <x-form.field name="scheduled_at" label="Scheduled Date & Time" type="datetime-local" required :value="$drive?->scheduled_at?->format('Y-m-d\TH:i') ?? null" step="60" />
    </div>
</fieldset>

<fieldset>
    <legend class="text-sm font-semibold text-slate-900 mb-4">Workflow Status</legend>
    @if ($drive === null)
        {{-- New drives always start Scheduled: the controller forces the
             status on store, so this hidden input only satisfies the
             shared required|in validation rule. --}}
        <input type="hidden" name="status" value="Scheduled">
        <p class="text-xs text-slate-500">New drives always start as <strong>Scheduled</strong>. Move the drive to <strong>Ongoing</strong> once volunteers assemble, then to <strong>Completed</strong> or <strong>Cancelled</strong> when it ends.</p>
    @else
        @php
            $allowed = \App\Models\CleanupDrive::TRANSITIONS[$drive->status] ?? \App\Models\CleanupDrive::STATUSES;
        @endphp
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-form.field name="status" label="Status" type="select" required :options="$allowed" :value="$drive->status" />
        </div>
        @if ($drive->status === 'Completed')
            <p class="mt-2 text-xs text-red-700">This drive is <strong>Completed</strong> and terminal: no edits — status or fields — are accepted. Reopen is not possible; schedule a new drive instead.</p>
        @elseif ($drive->status === 'Cancelled')
            <p class="mt-2 text-xs text-slate-500">This drive is <strong>Cancelled</strong>: its descriptive fields stay correctable, but the status can never move again.</p>
        @else
            <p class="mt-2 text-xs text-slate-500">Valid moves from <strong>{{ $drive->status }}</strong>: {{ implode(', ', $allowed) }}. Same-status saves only correct the fields above.</p>
        @endif
    @endif
</fieldset>
