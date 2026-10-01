<x-app-layout>
@section('page_header')
    <x-page-header title="Profile" subtitle="Your resident record on file with the barangay office." />
@endsection

@section('content')
<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="px-6 py-14 text-center">
        <x-icon name="residents" class="mx-auto h-10 w-10 text-slate-300" />
        <h2 class="mt-3 text-sm font-semibold text-slate-900">Profile setup pending</h2>
        <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">Your account is approved, but no resident record is linked to it yet. Please contact the barangay office so staff can set up your record — then sign in again to see your profile.</p>
        <form method="POST" action="{{ route('logout') }}" class="mt-5">
            @csrf
            <button type="submit" class="btn btn-neutral">Sign out</button>
        </form>
    </div>
</div>
@endsection
</x-app-layout>
