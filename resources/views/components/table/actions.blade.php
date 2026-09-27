@props(['editHref', 'printHref' => null, 'deleteHref' => null, 'confirm' => null])

<span class="inline-flex items-center justify-end gap-1 min-h-11">
    <a href="{{ $editHref }}" class="inline-flex items-center justify-center min-h-11 px-3 rounded-md text-sm font-medium text-green-700 hover:text-green-800 hover:bg-green-50 active:bg-green-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-600">
        Edit
    </a>
    @if ($printHref)
        <a href="{{ $printHref }}" target="_blank" class="inline-flex items-center justify-center min-h-11 px-3 rounded-md text-sm font-medium text-blue-700 hover:text-blue-800 hover:bg-blue-50 active:bg-blue-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500" title="Print official case sheet">
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
