@extends(auth()->user()?->hasRole(\App\Enums\UserRole::Student) ? 'layouts.student' : 'layouts.app')
@section('title', __('Kurs muhokamalari'))
@section('content')
<h1 class="text-2xl font-semibold">{{ $course->title }} {{ __('— muhokamalar') }}</h1>
@if ($errors->any())<p role="alert" class="text-red-700">{{ $errors->first() }}</p>@endif
<div class="mt-6 grid items-start gap-6 lg:grid-cols-3"><div class="flex flex-col gap-4 lg:col-span-2">
@forelse ($discussions as $discussion)<a href="{{ route('discussions.show', $discussion) }}" class="rounded-2xl bg-white p-6"><h2 class="font-semibold">{{ $discussion->title }}</h2><p class="mt-2 text-sm">{{ $discussion->author->name }} · {{ $discussion->replies_count }} {{ __('ta javob') }}</p></a>@empty<p>{{ __('Hozircha muhokama yo‘q.') }}</p>@endforelse
{{ $discussions->links() }}</div>
<form method="POST" action="{{ route('discussions.store', $course) }}" class="flex flex-col gap-3 rounded-2xl bg-white p-6">@csrf<h2 class="font-semibold">{{ __('Yangi muhokama') }}</h2><label>{{ __('Sarlavha') }}<input name="title" required maxlength="255" class="block w-full rounded border p-2" value="{{ old('title') }}"></label><label>{{ __('Matn') }}<textarea name="body" required maxlength="10000" rows="6" class="block w-full rounded border p-2">{{ old('body') }}</textarea></label><button class="rounded bg-blue-700 p-2 text-white">{{ __('Nashr qilish') }}</button></form>
</div>
@endsection
