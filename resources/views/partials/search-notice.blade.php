@if (isset($searchPlan) && $searchPlan->correction !== null)
    <div class="mb-5 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900" role="status">
        Menampilkan hasil untuk <strong>{{ $searchPlan->correction['corrected'] }}</strong>.
        <a class="ml-1 font-bold underline" href="{{ route($routeName ?? 'destinations', ['q' => $searchPlan->originalQuery, 'exact' => 1]) }}">Cari tanpa koreksi otomatis</a>
    </div>
@elseif (isset($searchPlan) && $searchPlan->suggestions !== [])
    <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Mungkin maksud Anda:
        @foreach ($searchPlan->suggestions as $suggestion)
            <a class="ml-1 font-bold underline" href="{{ route($routeName ?? 'destinations', ['q' => $suggestion]) }}" data-suggestion>{{ $suggestion }}</a>@if (! $loop->last),@endif
        @endforeach
    </div>
@endif
