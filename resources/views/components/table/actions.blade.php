@props(['editHref', 'printHref' => null, 'deleteHref' => null, 'confirm' => null, 'title' => null, 'icon' => 'trash'])

<span class="inline-flex items-center justify-end gap-2">
    <a href="{{ $editHref }}" class="btn btn-neutral btn-row">
        Edit
    </a>
    @if ($printHref)
        <a href="{{ $printHref }}" target="_blank" class="btn btn-neutral btn-row" title="Print official case sheet">
            Print
        </a>
    @endif
    @if ($deleteHref)
        <form action="{{ $deleteHref }}" method="POST" class="inline" @if($confirm) data-confirm="{{ $confirm }}"@if($title) data-confirm-title="{{ $title }}"@endif data-confirm-accept="Delete" data-confirm-icon="{{ $icon }}" @endif>
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-row">
                Delete
            </button>
        </form>
    @endif
</span>
