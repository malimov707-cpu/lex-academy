@extends(auth()->user()?->hasRole(\App\Enums\UserRole::Student) ? 'layouts.student' : 'layouts.app')
@section('title', $resource->title)
@section('content')
<div class="library-reader-heading"><a href="{{ route('library.show', $resource) }}">← {{ __('Material haqida') }}</a><span>{{ __('Sayt ichida o‘qish') }}</span>@if($resource->local_path)<a href="{{ route('library.file', [$resource, 'download' => 1]) }}">{{ __('Yuklab olish') }} · {{ strtoupper($resource->local_format) }}</a>@endif</div>
<h1 class="library-reader-title">{{ $resource->title }}</h1>
<div class="library-local-meta"><span>{{ __('Mahalliy nusxa') }} @if($resource->imported_at) · {{ $resource->imported_at->format('d.m.Y H:i') }} @endif</span>@if($resource->license)<a href="{{ $resource->license_url }}" target="_blank" rel="noopener noreferrer">{{ $resource->license }}</a>@endif</div>
@if ($resource->attribution)<details class="library-attribution" open><summary>{{ __('Manba va foydalanish shartlari') }}</summary><p>{{ $resource->attribution }}</p>@if($resource->import_source_url)<a href="{{ $resource->import_source_url }}" target="_blank" rel="noopener noreferrer">{{ __('Asl manba') }} ↗</a>@endif</details>@endif
@if ($resource->local_format === 'pdf')
    <p class="library-note">{{ __('PDF shu saytdan ochiladi. Mobil brauzerda ko‘rinmasa, faylni yuklab oling.') }}</p>
    <object class="library-pdf-reader" data="{{ route('library.file', $resource) }}" type="application/pdf" aria-label="{{ $resource->title }}"><p>{{ __('PDF shu saytdan ochiladi. Mobil brauzerda ko‘rinmasa, faylni yuklab oling.') }}</p><a href="{{ route('library.file', [$resource, 'download' => 1]) }}">{{ __('Yuklab olish') }}</a></object>
@else
<div class="library-reader-tools">
    <form method="GET" action="{{ route('library.reader', $resource) }}"><label for="reader-find">{{ __('Matn ichidan qidirish') }}</label><div><input id="reader-find" name="find" value="{{ $find }}" minlength="2" maxlength="100"><button>{{ __('Izlash') }}</button></div></form>
    @if($contents->isNotEmpty())<form method="GET" action="{{ route('library.reader', $resource) }}"><label for="reader-section">{{ __('Mundarija') }}</label><div><select name="page" id="reader-section">@foreach($contents as $item)<option value="{{ $item['page'] }}" @selected($loop->first && $page === $item['page'])>{{ $item['title'] }}</option>@endforeach</select><button>{{ __('O‘tish') }}</button></div></form>@endif
</div>
@if($find)<section class="library-reader-matches" aria-label="{{ __('Qidiruv natijalari') }}">@forelse($matches as $match)<a href="{{ route('library.reader', [$resource, 'page' => $match['page']]) }}">…{{ $match['excerpt'] }}… <strong>{{ __('Sahifa') }} {{ $match['page'] }} →</strong></a>@empty<p>{{ __('Mos matn topilmadi.') }}</p>@endforelse</section>@endif
<div class="library-reader-navigation">@if($page > 1)<a href="{{ route('library.reader', [$resource, 'page' => $page - 1]) }}">← {{ __('Oldingi sahifa') }}</a>@else<span></span>@endif<span>{{ __('Sahifa') }} {{ $page }} / {{ $totalPages }}</span>@if($page < $totalPages)<a href="{{ route('library.reader', [$resource, 'page' => $page + 1]) }}">{{ __('Keyingi sahifa') }} →</a>@else<span></span>@endif</div>
<article class="library-reader-text" tabindex="0">{{ $pageText }}</article>
<div class="library-reader-navigation"><form method="GET" action="{{ route('library.reader', $resource) }}"><label for="reader-page">{{ __('Sahifa') }}</label><input id="reader-page" type="number" min="1" max="{{ max(1, $totalPages) }}" name="page" value="{{ $page }}"><button>{{ __('O‘tish') }}</button></form>@if($page < $totalPages)<a href="{{ route('library.reader', [$resource, 'page' => $page + 1]) }}">{{ __('Keyingi sahifa') }} →</a>@endif</div>
@endif
<p class="library-note">{{ __('Mahalliy nusxa avtomatik yangilanmaydi. Normativ hujjatning tegishli sanadagi tahririni asl manbada solishtiring.') }}</p>
@endsection
