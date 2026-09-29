@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}">

        {{-- Small screens: one pair of controls, full width. --}}
        <div class="flex gap-2 items-center justify-between sm:hidden">

            @if ($paginator->onFirstPage())
                <span class="inline-flex min-h-11 items-center px-4 py-2 text-sm font-medium border border-slate-300 bg-white text-slate-400 cursor-not-allowed rounded-lg">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex min-h-11 items-center px-4 py-2 text-sm font-medium border border-slate-300 bg-white text-slate-700 rounded-lg hover:bg-slate-50 hover:text-slate-900 active:bg-slate-100 active:text-slate-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-600 transition-colors">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex min-h-11 items-center px-4 py-2 text-sm font-medium border border-slate-300 bg-white text-slate-700 rounded-lg hover:bg-slate-50 hover:text-slate-900 active:bg-slate-100 active:text-slate-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-600 transition-colors">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="inline-flex min-h-11 items-center px-4 py-2 text-sm font-medium border border-slate-300 bg-white text-slate-400 cursor-not-allowed rounded-lg">
                    {!! __('pagination.next') !!}
                </span>
            @endif

        </div>

        {{-- Roomy screens: the range on the left, the page group on the right. --}}
        <div class="hidden sm:flex-1 sm:flex sm:gap-2 sm:items-center sm:justify-between">

            <div>
                <p class="text-sm text-slate-500">
                    {!! __('Showing') !!}
                    @if ($paginator->firstItem())
                        <span class="font-medium text-slate-700">{{ $paginator->firstItem() }}</span>
                        {!! __('to') !!}
                        <span class="font-medium text-slate-700">{{ $paginator->lastItem() }}</span>
                    @else
                        {{ $paginator->count() }}
                    @endif
                    {!! __('of') !!}
                    <span class="font-medium text-slate-700">{{ $paginator->total() }}</span>
                    {!! __('results') !!}
                </p>
            </div>

            <div>
                {{-- One segmented control: shared borders collapse via -ml-px,
                     so only the outer corners of the group are rounded. --}}
                <span class="inline-flex rtl:flex-row-reverse shadow-sm rounded-lg">

                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                            <span class="inline-flex min-h-11 items-center px-2 py-2 text-sm font-medium border border-slate-300 bg-white text-slate-400 cursor-not-allowed rounded-l-lg" aria-hidden="true">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex min-h-11 items-center px-2 py-2 text-sm font-medium border border-slate-300 bg-white text-slate-700 rounded-l-lg hover:bg-slate-50 hover:text-slate-900 active:bg-slate-100 active:text-slate-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-600 transition-colors" aria-label="{{ __('pagination.previous') }}">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <span aria-disabled="true">
                                <span class="inline-flex min-h-11 items-center px-4 py-2 -ml-px text-sm font-medium border border-slate-300 bg-white text-slate-500 cursor-default">{{ $element }}</span>
                            </span>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page">
                                        <span class="inline-flex min-h-11 items-center px-4 py-2 -ml-px text-sm font-semibold border border-sky-600 bg-sky-600 text-white cursor-default">{{ $page }}</span>
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="inline-flex min-h-11 items-center px-4 py-2 -ml-px text-sm font-medium border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-900 active:bg-slate-100 active:text-slate-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-600 transition-colors" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex min-h-11 items-center px-2 py-2 -ml-px text-sm font-medium border border-slate-300 bg-white text-slate-700 rounded-r-lg hover:bg-slate-50 hover:text-slate-900 active:bg-slate-100 active:text-slate-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-600 transition-colors" aria-label="{{ __('pagination.next') }}">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @else
                        <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                            <span class="inline-flex min-h-11 items-center px-2 py-2 -ml-px text-sm font-medium border border-slate-300 bg-white text-slate-400 cursor-not-allowed rounded-r-lg" aria-hidden="true">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </span>
                    @endif
                </span>
            </div>
        </div>
    </nav>
@endif
