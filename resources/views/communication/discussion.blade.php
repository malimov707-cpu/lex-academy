@extends(auth()->user()?->hasRole(\App\Enums\UserRole::Student) ? 'layouts.student' : 'layouts.app')
@section('title', $discussion->title)
@section('content')
<div class="max-w-3xl"><h1 class="text-2xl font-semibold">{{ $discussion->title }}</h1><p class="mt-2 text-sm">{{ $discussion->author->name }}</p><p class="mt-6 whitespace-pre-line rounded-2xl bg-white p-6">{{ $discussion->body }}</p>
<div class="mt-4 flex flex-col gap-3">@foreach ($replies as $reply)<article class="rounded-xl bg-white p-5"><p class="text-xs">{{ $reply->author->name }} · {{ $reply->created_at->format('d.m.Y H:i') }}</p><p class="mt-2 whitespace-pre-line">{{ $reply->body }}</p></article>@endforeach</div>{{ $replies->links() }}
@if ($errors->any())<p role="alert" class="text-red-700">{{ $errors->first() }}</p>@endif
@if (! $discussion->is_locked)<form method="POST" action="{{ route('discussions.reply', $discussion) }}" class="mt-6 flex flex-col gap-3">@csrf<label>{{ __('Javob') }}<textarea name="body" rows="4" maxlength="5000" required class="block w-full rounded border p-2">{{ old('body') }}</textarea></label><button class="self-start rounded bg-blue-700 px-4 py-2 text-white">{{ __('Yuborish') }}</button></form>@else<p class="mt-4">{{ __('Muhokama yopilgan.') }}</p>@endif
@if ($canModerate)<form method="POST" action="{{ route('discussions.update', $discussion) }}" class="mt-6">@csrf @method('PATCH')<input type="hidden" name="is_locked" value="{{ $discussion->is_locked ? 0 : 1 }}"><button class="rounded border p-2">{{ $discussion->is_locked ? __('Muhokamani ochish') : __('Muhokamani yopish') }}</button></form>@endif
</div>
@endsection
