@if ($attribution = $destination->imageAttribution())
    <span class="absolute bottom-1 right-1 z-10 rounded bg-black/70 px-1.5 py-0.5 text-[8px] text-white" title="Kredit foto: {{ $attribution['artist'] }} · lisensi {{ $attribution['license'] }}">
        Foto: {{ $attribution['artist'] }} · {{ $attribution['license'] }}
    </span>
@endif
