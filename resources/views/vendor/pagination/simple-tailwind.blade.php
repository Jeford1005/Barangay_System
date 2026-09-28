@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex gap-2 items-center justify-between">

        @if ($paginator->onFirstPage())
            <span class="inline-flex min-h-10 items-center px-4 py-2 text-sm font-medium text-slate-400 bg-white border border-slate-300 cursor-not-allowed rounded-lg">
                {!! __('pagination.previous') !!}
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex min-h-10 items-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-600 active:bg-slate-100 active:text-slate-800 transition-colors">
                {!! __('pagination.previous') !!}
            </a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex min-h-10 items-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-600 active:bg-slate-100 active:text-slate-800 transition-colors">
                {!! __('pagination.next') !!}
            </a>
        @else
            <span class="inline-flex min-h-10 items-center px-4 py-2 text-sm font-medium text-slate-400 bg-white border border-slate-300 cursor-not-allowed rounded-lg">
                {!! __('pagination.next') !!}
            </span>
        @endif

    </nav>
@endif
