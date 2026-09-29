<x-app-layout>
@section('page_header')
    <x-page-header title="Analytics" subtitle="Live at-a-glance statistics across all barangay modules.">
        <x-slot:actions>
            <a href="{{ route('reports.index') }}" class="inline-flex min-h-11 items-center px-2 text-sm font-medium text-sky-700 hover:bg-sky-50 focus:outline-none focus:ring-2 focus:ring-sky-600">Printable reports →</a>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
<div class="max-w-7xl mx-auto">

    {{-- KPI cards ------------------------------------------------------ --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            ['label' => 'Active Residents', 'value' => number_format($kpis->residents), 'sub' => number_format($kpis->voters).' registered voters', 'icon' => 'residents'],
            ['label' => 'Households', 'value' => number_format($kpis->households), 'sub' => $kpis->puroks.' puroks', 'icon' => 'households'],
            ['label' => 'Certificates Issued', 'value' => number_format($kpis->certificates), 'sub' => $kpis->pendingRequests.' requests pending', 'icon' => 'printer'],
            ['label' => 'Open Blotter Cases', 'value' => number_format($kpis->openCases), 'sub' => $kpis->pendingApprovals.' account approvals waiting', 'icon' => 'clipboard-document-list'],
        ] as $card)
            <div class="bg-white rounded-xl shadow p-5">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                    <x-icon name="{{ $card['icon'] }}" class="h-5 w-5 text-sky-600" />
                </div>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ $card['value'] }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $card['sub'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Population charts ---------------------------------------------- --}}
    <h3 class="mt-10 mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Population</h3>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Sex distribution: donut --}}
        <div class="bg-white rounded-xl shadow p-6">
            <h4 class="text-sm font-semibold text-slate-900">Sex Distribution</h4>
            @php
                $sexTotal = max(1, collect($sexDistribution)->sum('count'));
                $donutR = 54; $donutC = 2 * M_PI * $donutR; $offset = 0;
            @endphp
            <div class="mt-4 flex items-center gap-5">
                <svg viewBox="0 0 140 140" class="h-32 w-32 shrink-0 -rotate-90">
                    <circle cx="70" cy="70" r="{{ $donutR }}" fill="none" stroke="#f1f5f9" stroke-width="20" />
                    @foreach ($sexDistribution as $seg)
                        @if ($seg['count'] > 0)
                            @php $len = $donutC * $seg['count'] / $sexTotal; @endphp
                            <circle cx="70" cy="70" r="{{ $donutR }}" fill="none"
                                stroke="{{ $seg['color'] }}" stroke-width="20"
                                stroke-dasharray="{{ $len }} {{ $donutC - $len }}"
                                stroke-dashoffset="{{ -$offset }}" />
                            @php $offset += $len; @endphp
                        @endif
                    @endforeach
                </svg>
                <ul class="space-y-2 text-sm">
                    @foreach ($sexDistribution as $seg)
                        <li class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-sm" style="background: {{ $seg['color'] }}"></span>
                            <span class="text-slate-700">{{ $seg['label'] }}</span>
                            <span class="font-semibold text-slate-900">{{ number_format($seg['count']) }}</span>
                            <span class="text-slate-500">({{ round($seg['count'] * 100 / $sexTotal) }}%)</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Age brackets: vertical bars --}}
        <div class="bg-white rounded-xl shadow p-6">
            <h4 class="text-sm font-semibold text-slate-900">Age Brackets</h4>
            @php
                $ageTotal = max(1, collect($ageDistribution)->sum('count'));
                $ageKnown = collect($ageDistribution)->sum('count');
            @endphp
            <div class="mt-4 flex items-end gap-2 h-32">
                @foreach ($ageDistribution as $bar)
                    <div class="flex flex-1 flex-col items-center justify-end gap-1 h-full">
                        <span class="text-[11px] font-semibold text-slate-700">{{ $bar['count'] ?: '' }}</span>
                        <div class="w-full rounded-t-md transition-all"
                            style="height: {{ max(2, $bar['count'] * 100 / $ageTotal) }}%; background: {{ $bar['color'] }}"></div>
                        <span class="text-[11px] text-slate-500">{{ $bar['label'] }}</span>
                    </div>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-slate-500">{{ number_format($ageKnown) }} residents with known birth dates</p>
        </div>

        {{-- By purok: horizontal bars --}}
        <div class="bg-white rounded-xl shadow p-6">
            <h4 class="text-sm font-semibold text-slate-900">Residents by Purok</h4>
            <ul class="mt-4 space-y-3">
                @foreach ($byPurok as $row)
                    <li>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-700">{{ $row['label'] }}</span>
                            <span class="font-semibold text-slate-900">{{ number_format($row['count']) }}</span>
                        </div>
                        <div class="mt-1 h-2 w-full rounded-full bg-slate-100">
                            <div class="h-2 rounded-full bg-sky-600" style="width: {{ $row['count'] * 100 / $maxPurok }}%"></div>
                        </div>
                    </li>
                @endforeach
                @if (empty($byPurok))
                    <li class="text-sm text-slate-500">No puroks yet.</li>
                @endif
            </ul>
        </div>
    </div>

    {{-- Trends + blotter ------------------------------------------------ --}}
    <h3 class="mt-10 mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Trends &amp; Peace &amp; Order</h3>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Registrations trend --}}
        <div class="bg-white rounded-xl shadow p-6">
            <h4 class="text-sm font-semibold text-slate-900">New Resident Registrations — last 12 months</h4>
            <div class="mt-4 flex items-end gap-1 h-36">
                @foreach ($registrationTrend as $bar)
                    <div class="group flex flex-1 flex-col items-center justify-end gap-1 h-full" title="{{ $bar['label'] }}: {{ $bar['count'] }}">
                        <span class="text-[10px] font-semibold text-slate-500 opacity-0 group-hover:opacity-100">{{ $bar['count'] ?: '' }}</span>
                        <div class="w-full rounded-t bg-sky-600 group-hover:bg-sky-700"
                            style="height: {{ $bar['count'] * 100 / max(1, collect($registrationTrend)->max('count')) }}%; min-height: {{ $bar['count'] ? '2px' : '0' }}"></div>
                        <span class="text-[10px] text-slate-500">{{ explode(' ', $bar['label'])[0] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Certificate issuance trend --}}
        <div class="bg-white rounded-xl shadow p-6">
            <h4 class="text-sm font-semibold text-slate-900">Certificates Issued — last 12 months</h4>
            <div class="mt-4 flex items-end gap-1 h-36">
                @foreach ($certTrend as $bar)
                    <div class="group flex flex-1 flex-col items-center justify-end gap-1 h-full" title="{{ $bar['label'] }}: {{ $bar['count'] }}">
                        <span class="text-[10px] font-semibold text-slate-500 opacity-0 group-hover:opacity-100">{{ $bar['count'] ?: '' }}</span>
                        <div class="w-full rounded-t bg-sky-600 group-hover:bg-sky-700"
                            style="height: {{ $bar['count'] * 100 / max(1, collect($certTrend)->max('count')) }}%; min-height: {{ $bar['count'] ? '2px' : '0' }}"></div>
                        <span class="text-[10px] text-slate-500">{{ explode(' ', $bar['label'])[0] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Blotter status donut --}}
        <div class="bg-white rounded-xl shadow p-6">
            <h4 class="text-sm font-semibold text-slate-900">Blotter Case Status</h4>
            @php
                $blotterTotal = max(1, collect($blotterStatus)->sum('count'));
                $bR = 54; $bC = 2 * M_PI * $bR; $bOffset = 0;
            @endphp
            <div class="mt-4 flex items-center gap-5">
                <svg viewBox="0 0 140 140" class="h-28 w-28 shrink-0 -rotate-90">
                    <circle cx="70" cy="70" r="{{ $bR }}" fill="none" stroke="#f1f5f9" stroke-width="20" />
                    @foreach ($blotterStatus as $seg)
                        @if ($seg['count'] > 0)
                            @php $len = $bC * $seg['count'] / $blotterTotal; @endphp
                            <circle cx="70" cy="70" r="{{ $bR }}" fill="none"
                                stroke="{{ $seg['color'] }}" stroke-width="20"
                                stroke-dasharray="{{ $len }} {{ $bC - $len }}"
                                stroke-dashoffset="{{ -$bOffset }}" />
                            @php $bOffset += $len; @endphp
                        @endif
                    @endforeach
                </svg>
                <ul class="space-y-2 text-sm">
                    @foreach ($blotterStatus as $seg)
                        <li class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-sm" style="background: {{ $seg['color'] }}"></span>
                            <span class="text-slate-700">{{ $seg['label'] }}</span>
                            <span class="font-semibold text-slate-900">{{ number_format($seg['count']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Top complaint types --}}
        <div class="bg-white rounded-xl shadow p-6">
            <h4 class="text-sm font-semibold text-slate-900">Top Complaint Types</h4>
            <ul class="mt-4 space-y-3">
                @foreach ($topComplaints as $row)
                    <li>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-700">{{ $row['label'] }}</span>
                            <span class="font-semibold text-slate-900">{{ $row['count'] }}</span>
                        </div>
                        <div class="mt-1 h-2 w-full rounded-full bg-slate-100">
                            <div class="h-2 rounded-full bg-amber-500" style="width: {{ $row['count'] * 100 / $maxComplaint }}%"></div>
                        </div>
                    </li>
                @endforeach
                @if (empty($topComplaints))
                    <li class="text-sm text-slate-500">No blotter cases yet.</li>
                @endif
            </ul>
        </div>

        {{-- Welfare summary --}}
        <div class="bg-white rounded-xl shadow p-6">
            <h4 class="text-sm font-semibold text-slate-900">Welfare Assistance</h4>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Approved amount</dt>
                    <dd class="font-semibold text-slate-900">₱{{ number_format($welfareAmounts->approved, 2) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Released amount</dt>
                    <dd class="font-semibold text-emerald-600">₱{{ number_format($welfareAmounts->released, 2) }}</dd>
                </div>
            </dl>
            <ul class="mt-4 space-y-1.5 border-t border-slate-200 pt-3 text-xs">
                @foreach ($welfareStatus as $seg)
                    <li class="flex justify-between">
                        <span class="text-slate-600">{{ $seg['label'] }}</span>
                        <span class="font-semibold text-slate-900">{{ $seg['count'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Footer note ----------------------------------------------------- --}}
    <p class="mt-8 text-xs text-slate-500">
        Figures are live counts from the database. {{ $seniors }} senior residents (60+) currently registered —
        see <a href="{{ route('reports.population') }}" class="text-sky-700 hover:underline">Reports</a> for printable official versions.
    </p>
</div>
@endsection
</x-app-layout>
