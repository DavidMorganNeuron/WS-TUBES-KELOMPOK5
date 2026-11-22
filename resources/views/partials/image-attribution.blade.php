@if ($attribution = $destination->imageAttribution())
<<<<<<< HEAD
    <span class="absolute bottom-1 right-1 z-10 rounded bg-black/70 px-1.5 py-0.5 text-[8px] text-white" title="Kredit foto: {{ $attribution['artist'] }} · lisensi {{ $attribution['license'] }}">
        Foto: {{ $attribution['artist'] }} · {{ $attribution['license'] }}
=======
    <span class="absolute bottom-1 right-1 z-10 rounded bg-black/70 px-1.5 py-0.5 text-[8px] text-white" title="Source: {{ $attribution['source_url'] }}; licensed {{ $attribution['license'] }}">
        Photo: {{ $attribution['artist'] }} · {{ $attribution['license'] }}
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
    </span>
@endif
