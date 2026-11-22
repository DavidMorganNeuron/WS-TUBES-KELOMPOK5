@if ($resource !== null)
    <span class="{{ $class }}" rel="{{ $relation }}" resource="{{ $resource->iri }}" typeof="{{ $resource->type }}">
        <span property="{{ $resource->labelProperty }}">{{ $resource->label }}</span>
        @if ($resource->regionType !== null)
            <meta property="ws:tipeWilayah" content="{{ $resource->regionType }}">
        @endif
    </span>
@else
    <span class="{{ $class }}">{{ $label }}</span>
@endif
