@php
    $isEdit = $document !== null;
    $prefix = $isEdit ? 'type-'.$document->id : 'type-new';
    $formKey = $isEdit ? 'edit-'.$document->id : 'new';
    $ownsOld = old('_form') === $formKey;
    $hasErrors = $ownsOld && $errors->any();

    $types = \App\Models\Document::TYPES;
    $statuses = \App\Models\Document::STATUSES;

    $code = $ownsOld ? (string) old('code', '') : (string) ($document?->code ?? '');
    $title = $ownsOld ? (string) old('title', '') : (string) ($document?->title ?? '');
    $description = $ownsOld ? old('description') : ($document?->description ?? '');
    $documentType = $ownsOld ? (string) old('document_type', 'Certificate') : (string) ($document?->document_type ?? 'Certificate');
    $fee = $ownsOld ? (string) old('fee', '0') : (string) (float) ($document?->fee ?? 0);
    $status = $ownsOld ? (string) old('status', 'Active') : (string) ($document?->status ?? 'Active');
@endphp

<div id="{{ $prefix }}"
     data-modal
     role="dialog"
     aria-modal="true"
     aria-labelledby="{{ $prefix }}-title"
     aria-hidden="true"
     @if ($hasErrors) data-open-on-load @endif
     class="fixed inset-0 z-40 hidden">
    <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" data-close-modal></div>

    <div class="absolute inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
            <div class="relative w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-7">

                <button type="button"
                        data-close-modal
                        aria-label="Close dialog"
                        class="absolute right-4 top-4 rounded-md p-1.5 text-slate-400 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" width="18" height="18">
                        <path d="M18 6 6 18M6 6l12 12"/>
                    </svg>
                </button>

                <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-400">Certificate catalog</p>
                <h2 id="{{ $prefix }}-title" class="mt-2 text-xl font-semibold text-slate-900">
                    {{ $isEdit ? 'Edit '.$document->code : 'New certificate type' }}
                </h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-500">
                    The code becomes the start of every control number this type receives
                    (<span class="font-medium text-slate-700">{{ $code !== '' ? $code : 'CLR' }}-{{ now()->year }}-0001</span>).
                    Once the type has been issued, the code can no longer be changed.
                </p>

                <form method="POST"
                      action="{{ $isEdit ? route('admin.certificate-types.update', $document) : route('admin.certificate-types.store') }}"
                      data-submit-loading data-loading-label="Saving…"
                      class="mt-6 space-y-4">
                    @csrf
                    @if ($isEdit)
                        @method('PUT')
                    @endif
                    <input type="hidden" name="_form" value="{{ $formKey }}">

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="{{ $prefix }}-code" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Code</label>
                            <input id="{{ $prefix }}-code"
                                   type="text"
                                   name="code"
                                   value="{{ $code }}"
                                   required
                                   maxlength="10"
                                   placeholder="CLR"
                                   autocomplete="off"
                                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm uppercase focus:border-sky-500 focus:ring-sky-500 @if ($ownsOld && $errors->has('code')) border-red-400 focus:border-red-400 focus:ring-red-400/20 @endif">
                            @if ($ownsOld)
                                @error('code')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            @endif
                            <p class="mt-1.5 text-xs text-slate-400">2–8 letters or numbers, saved uppercase.</p>
                        </div>

                        <div>
                            <label for="{{ $prefix }}-fee" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Fee ₱</label>
                            <input id="{{ $prefix }}-fee"
                                   type="number"
                                   name="fee"
                                   value="{{ $fee }}"
                                   required
                                   min="0"
                                   max="9999"
                                   step="0.01"
                                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @if ($ownsOld && $errors->has('fee')) border-red-400 focus:border-red-400 focus:ring-red-400/20 @endif">
                            @if ($ownsOld)
                                @error('fee')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            @endif
                        </div>
                    </div>

                    <div>
                        <label for="{{ $prefix }}-title-input" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Title</label>
                        <input id="{{ $prefix }}-title-input"
                               type="text"
                               name="title"
                               value="{{ $title }}"
                               required
                               maxlength="150"
                               placeholder="Barangay Clearance"
                               autocomplete="off"
                               class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @if ($ownsOld && $errors->has('title')) border-red-400 focus:border-red-400 focus:ring-red-400/20 @endif">
                        @if ($ownsOld)
                            @error('title')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        @endif
                    </div>

                    <div>
                        <label for="{{ $prefix }}-description" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Description <span class="text-slate-400 normal-case font-normal">(optional)</span>
                        </label>
                        <textarea id="{{ $prefix }}-description"
                                  name="description"
                                  rows="2"
                                  maxlength="255"
                                  placeholder="What this certificate certifies"
                                  class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @if ($ownsOld && $errors->has('description')) border-red-400 focus:border-red-400 focus:ring-red-400/20 @endif">{{ $description }}</textarea>
                        @if ($ownsOld)
                            @error('description')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        @endif
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="{{ $prefix }}-type" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Type</label>
                            <select id="{{ $prefix }}-type"
                                    name="document_type"
                                    required
                                    class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @if ($ownsOld && $errors->has('document_type')) border-red-400 focus:border-red-400 focus:ring-red-400/20 @endif">
                                @foreach ($types as $type)
                                    <option value="{{ $type }}" @selected($documentType === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                            @if ($ownsOld)
                                @error('document_type')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            @endif
                        </div>

                        <div>
                            <label for="{{ $prefix }}-status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                            <select id="{{ $prefix }}-status"
                                    name="status"
                                    required
                                    class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @if ($ownsOld && $errors->has('status')) border-red-400 focus:border-red-400 focus:ring-red-400/20 @endif">
                                @foreach ($statuses as $option)
                                    <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                            @if ($ownsOld)
                                @error('status')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            @endif
                        </div>
                    </div>

                    @if ($ownsOld && $errors->any())
                        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <ul class="list-disc space-y-1 pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <button type="submit"
                            class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300 disabled:cursor-wait disabled:opacity-70">
                        {{ $isEdit ? 'Save changes' : 'Add certificate type' }}
                    </button>
                </form>

                <div class="mt-5 border-t border-slate-100 pt-4 text-center">
                    <button type="button" data-close-modal class="text-sm text-slate-500 transition hover:text-slate-800">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
