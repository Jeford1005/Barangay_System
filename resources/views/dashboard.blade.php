<x-app-layout>
@section('page_header')
    <x-page-header title="Dashboard" subtitle="Welcome back, {{ auth()->user()?->name }}. Here is an overview of barangay records." />
@endsection

@section('content')
<div class="max-w-7xl mx-auto">

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('residents.index') }}" class="bg-white rounded-xl shadow p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-gray-500">Residents</p>
                <x-icon name="residents" class="h-6 w-6 text-blue-500" />
            </div>
            <p class="mt-2 text-3xl font-bold text-gray-900">{{ $residentCount }}</p>
        </a>
        <a href="{{ route('households.index') }}" class="bg-white rounded-xl shadow p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-gray-500">Households</p>
                <x-icon name="households" class="h-6 w-6 text-emerald-500" />
            </div>
            <p class="mt-2 text-3xl font-bold text-gray-900">{{ $householdCount }}</p>
        </a>
        <a href="{{ route('puroks.index') }}" class="bg-white rounded-xl shadow p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-gray-500">Puroks</p>
                <x-icon name="puroks" class="h-6 w-6 text-violet-500" />
            </div>
            <p class="mt-2 text-3xl font-bold text-gray-900">{{ $purokCount }}</p>
        </a>
        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-gray-500">Officials</p>
                <x-icon name="building-office-2" class="h-6 w-6 text-amber-500" />
            </div>
            <p class="mt-2 text-3xl font-bold text-gray-900">{{ $officialCount }}</p>
        </div>
    </div>

    <div class="mt-8 bg-white rounded-xl shadow overflow-hidden">
        <div class="bg-gray-50 px-6 py-4">
            <h3 class="text-sm font-semibold text-gray-900">Quick Actions</h3>
        </div>
        <div class="p-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <a href="{{ route('residents.index') }}?open=resident" class="inline-flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-500">
                <x-icon name="user-plus" class="h-4 w-4 text-green-600" />
                Register a resident
            </a>
            <a href="{{ route('households.index') }}?open=household" class="inline-flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-500">
                <x-icon name="households" class="h-4 w-4 text-green-600" />
                Add a household
            </a>
            <a href="{{ route('certificates.index') }}?open=certificate" class="inline-flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-500">
                <x-icon name="document-text" class="h-4 w-4 text-green-600" />
                Issue a certificate
            </a>
            @if (auth()->user()?->isAdmin())
                <a href="{{ route('puroks.index') }}?open=purok" class="inline-flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-500">
                    <x-icon name="plus" class="h-4 w-4 text-green-600" />
                    Add a purok
                </a>
            @endif
        </div>
    </div>
</div>
@endsection
</x-app-layout>
