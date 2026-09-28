@php $editing = isset($document); @endphp
<form method="POST" action="{{ $editing ? route('admin.certificate-types.update', $document) : route('admin.certificate-types.store') }}" class="space-y-4">
    @csrf
    @if ($editing) @method('PUT') @endif
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-form.field name="code" label="Code" required maxlength="8" pattern="[A-Za-z0-9]+" :value="old('code', $document->code ?? null)" />
        <x-form.field name="category" label="Category" required maxlength="100" :value="old('category', $document->category ?? null)" />
        <div class="sm:col-span-2"><x-form.field name="title" label="Title" required maxlength="255" :value="old('title', $document->title ?? null)" /></div>
        <x-form.field name="document_type" label="Document type" type="select" required :options="['Certificate','Permit','Clearance','ID','Other']" :value="old('document_type', $document->document_type ?? 'Certificate')" />
        <x-form.field name="status" label="Status" type="select" required :options="['Active','Inactive','Draft']" :value="old('status', $document->status ?? 'Draft')" />
        <x-form.field name="fee" label="Fee" type="number" inputmode="decimal" min="0" max="9999" step="0.01" required :value="old('fee', $document->fee ?? 0)" />
        <div class="sm:col-span-2"><x-form.field name="description" label="Description" type="textarea" :rows="3" maxlength="2000" :value="old('description', $document->description ?? null)" /></div>
        <div class="sm:col-span-2"><x-form.field name="requirements" label="Resident requirements" type="textarea" :rows="4" maxlength="5000" :value="old('requirements', $document->requirements ?? null)" /></div>
    </div>
    @error('code')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    @error('document_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    <div class="flex justify-end gap-2"><a href="{{ route('admin.certificate-types.index') }}" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-medium hover:bg-slate-50">Cancel</a><button type="submit" class="min-h-10 rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white hover:bg-sky-700">{{ $editing ? 'Update document' : 'Create document' }}</button></div>
</form>
