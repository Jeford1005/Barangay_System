@if (request()->boolean('embed'))
    {{-- Embed mode: this page is rendered inside the settings dialog's iframe,
         which supplies the header and the section navigation itself. Only the
         section content travels; scripts, toasts and dialogs stay as they are. --}}
    {{ $slot }}
@else
    {{-- Full page: content only, centered. The section navigation lives in the
         settings dialog (its rail is the one section list), so no tab strip
         renders here - deep links and no-JS still get the section itself. --}}
    <div class="mx-auto max-w-6xl">
        {{ $slot }}
    </div>
@endif
