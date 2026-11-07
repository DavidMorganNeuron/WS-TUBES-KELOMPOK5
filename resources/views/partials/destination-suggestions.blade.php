@if ($destinations->isNotEmpty())
    <ul class="divide-y divide-gray-100" role="listbox">
        @foreach ($destinations as $destination)
            <li>
                <a href="{{ route('destinations', ['q' => $destination->name]) }}" data-suggestion class="flex items-center gap-3 px-4 py-3 transition hover:bg-brand-light focus:bg-brand-light outline-none" role="option">
                    <img src="{{ $destination->imageUrl() }}" alt="" class="w-10 h-10 rounded-lg object-cover flex-shrink-0" loading="lazy">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-bold text-gray-900">{{ $destination->name }}</span>
                        <span class="block truncate text-[11px] text-gray-500">{{ $destination->province() }} • {{ $destination->location }}</span>
                    </span>
                    <span class="whitespace-nowrap text-[10px] font-bold text-brand-primary">⭐ {{ $destination->ratingLabel() }}</span>
                </a>
            </li>
        @endforeach
    </ul>
    <div class="border-t border-gray-100 bg-gray-50 px-4 py-2 text-[10px] font-bold text-gray-500">
        Press Enter to see all matching destinations
    </div>
@endif
