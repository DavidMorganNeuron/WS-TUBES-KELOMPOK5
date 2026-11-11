@if ($attribution = $destination->imageAttribution())
    <span class="absolute bottom-1 right-1 z-10 rounded bg-black/70 px-1.5 py-0.5 text-[8px] text-white" title="Source: {{ $attribution['source_url'] }}; licensed {{ $attribution['license'] }}">
        Photo: {{ $attribution['artist'] }} · {{ $attribution['license'] }}
    </span>
@endif
