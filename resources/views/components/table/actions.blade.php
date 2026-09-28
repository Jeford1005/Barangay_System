@props(['editHref', 'printHref' => null, 'deleteHref' => null, 'confirm' => null])

<span class="inline-flex items-center justify-end gap-1 min-h-11">
    <a href="{{ $editHref }}" class="inline-flex items-center justify-center min-h-11 px-3 rounded-md text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 active:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
        Edit
    </a>
    @if ($printHref)
        <a href="{{ $printHref }}" target="_blank" class="inline-flex items-center justify-center min-h-11 px-3 rounded-md text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 active:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400" title="Print official case sheet">
            Print
        </a>
    @endif
    @if ($deleteHref)
        <form action="{{ $deleteHref }}" method="POST" class="inline" @if($confirm) onsubmit="return confirm({{ $confirm }})" @endif>
            @csrf
            @method('DELETE')
            <button type="submit" class="inline-flex items-center justify-center min-h-11 px-3 rounded-md text-sm font-medium text-red-600 hover:text-red-800 hover:bg-red-50 active:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                Delete
            </button>
        </form>
    @endif
</span>
