@extends(auth()->user()?->hasRole(\App\Enums\UserRole::Student) ? 'layouts.student' : 'layouts.app')
@section('title', __('Huquqiy kutubxona'))
@section('content')
<section class="library-hero"><div><span>HUQUQ AI ACADEMY</span><h1>{{ __('Huquqiy kutubxona') }}</h1><p>{{ __('Kodekslar, qonunlar, darsliklar va mustaqil izlanish uchun manbalar.') }}</p></div><div class="library-hero-mark" aria-hidden="true">§</div></section>
<div class="library-toolbar"><a class="{{ empty($filters['saved']) ? 'is-active' : '' }}" href="{{ route('library.index') }}">{{ __('Barcha materiallar') }}</a><a class="{{ !empty($filters['saved']) ? 'is-active' : '' }}" href="{{ route('library.index', ['saved' => 1]) }}">{{ __('Mening o‘qish ro‘yxatim') }}</a><a href="{{ route('legal-information-check.index') }}">{{ __('Shubhali huquqiy da’voni tekshirish') }} ↗</a><a href="{{ route('legal-updates.index') }}">{{ __('Qonunchilik monitoringi (Lex.uz)') }} ↗</a>@if(auth()->user()->isAdmin())<a href="{{ route('admin.library.index') }}">{{ __('Kutubxonani boshqarish') }} ↗</a>@endif</div>
<form method="GET" action="{{ route('library.index') }}" class="library-search-form">
    @if (!empty($filters['saved']))<input type="hidden" name="saved" value="1">@endif
    <label class="library-local-filter"><input type="checkbox" name="local" value="1" @checked(request()->boolean('local'))> {{ __('Faqat sayt ichida o‘qiladigan') }}</label>
    <label>{{ __('Qidirish') }}<input type="search" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Hujjat nomi yoki mavzuni kiriting…') }}"></label>
    <label>{{ __('Material turi') }}<select name="type"><option value="">{{ __('Barchasi') }}</option>@foreach($types as $key => $label)<option value="{{ $key }}" @selected(($filters['type'] ?? '') === $key)>{{ __($label) }} ({{ $counts[$key] ?? 0 }})</option>@endforeach</select></label>
    <label>{{ __('Huquq sohasi') }}<select name="topic"><option value="">{{ __('Barchasi') }}</option>@foreach($topics as $topic)<option @selected(($filters['topic'] ?? '') === $topic)>{{ $topic }}</option>@endforeach</select></label>
    <button class="edu-btn edu-btn-primary">{{ __('Izlash') }}</button>
</form>
<div class="library-results-heading"><p>{{ __(':n ta material topildi', ['n' => $resources->total()]) }}</p><a href="{{ route('library.index') }}">{{ __('Filtrlarni tozalash') }}</a></div>
<div class="library-grid">
@forelse ($resources as $resource)
    @php $saved = $resource->bookmarks->isNotEmpty(); @endphp
    <article class="library-card"><div class="library-card-top"><span class="library-resource-icon"><x-nav-icon name="{{ in_array($resource->type, ['code', 'law', 'constitution']) ? 'law' : 'courses' }}" /></span><span class="library-kind">{{ __($types[$resource->type]) }}</span><form method="POST" action="{{ route('library.bookmark', $resource) }}">@csrf @method('PUT')<input type="hidden" name="saved" value="{{ $saved ? '0' : '1' }}"><button class="library-save {{ $saved ? 'is-saved' : '' }}" aria-label="{{ $saved ? __('Ro‘yxatdan olib tashlash') : __('O‘qish ro‘yxatiga saqlash') }}" aria-pressed="{{ $saved ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" fill="{{ $saved ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M6 3h12v18l-6-4-6 4z"/></svg></button></form></div>
    <small class="library-topic">{{ $resource->topic }}</small><h2><a href="{{ route('library.show', $resource) }}">{{ $resource->title }}</a></h2><p>{{ Str::limit($resource->description, 160) }}</p><div class="library-card-meta"><span>{{ $resource->publisher }}</span>@if($resource->year)<span>{{ $resource->year }}</span>@endif</div>
    <div class="library-card-bottom"><span class="{{ $resource->access === 'account' ? 'library-access-account' : '' }}">{{ $resource->local_path || $resource->has_body ? __('Sayt ichida o‘qish') : ($resource->access === 'account' ? __('Manba saytidagi hisob kerak') : __('Tashqi manba')) }}</span><a href="{{ $resource->local_path || $resource->has_body ? route('library.reader', $resource) : route('library.show', $resource) }}">{{ __('O‘rganish') }} →</a></div></article>
@empty<div class="library-empty">{{ __('Mos material topilmadi. Filtrlarni o‘zgartirib ko‘ring.') }}</div>@endforelse
</div>
<div class="mt-6">{{ $resources->links() }}</div>
<p class="library-note">{{ __('Normativ hujjatning holati va tegishli sanadagi tahririni rasmiy manbada tekshiring. Darslik nashr yili qonunning amaldagi tahririni anglatmaydi.') }}</p>
@endsection
