@if ($attribution = $destination->imageAttribution())
    <span class="absolute bottom-1 right-1 z-10 rounded bg-black/70 px-1.5 py-0.5 text-[8px] text-white" title="Sumber: {{ $attribution['source_url'] }}; lisensi {{ $attribution['license'] }}">
        Foto: {{ $attribution['artist'] }} · {{ $attribution['license'] }}
    </span>
@endif
