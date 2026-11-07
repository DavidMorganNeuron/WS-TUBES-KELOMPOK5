@php
    $firstItem = $destinations->firstItem() ?? 0;
    $lastItem = $destinations->lastItem() ?? 0;
@endphp

@if ($destinations->isEmpty())
    <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-12 text-center mb-10">
        <p class="text-4xl mb-4">🔎</p>
        <h2 class="text-2xl font-serif font-bold text-gray-900 mb-2">No destinations match your filters</h2>
        <p class="text-sm text-gray-500 mb-6">Try removing a category, choosing another kabupaten/kota, or using a broader keyword.</p>
        <a href="{{ route('destinations') }}" class="inline-block bg-brand-accent hover:bg-orange-800 text-white text-sm font-bold px-5 py-2.5 rounded-full transition">Reset all filters</a>
    </div>
@else
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
        @foreach ($destinations as $destination)
            <x-destination-card :destination="$destination" />
        @endforeach
    </div>

    <div class="flex flex-col sm:flex-row justify-between items-center gap-4 border-t border-gray-200 pt-6 mb-16">
        <span class="text-sm text-gray-500">
            Showing {{ $firstItem }}–{{ $lastItem }} of {{ number_format($destinations->total()) }} attractions
        </span>
        <div>
            {{ $destinations->links() }}
        </div>
    </div>
@endif
