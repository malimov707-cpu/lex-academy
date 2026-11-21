@extends(auth()->user()?->hasRole(\App\Enums\UserRole::Student) ? 'layouts.student' : 'layouts.app')
@section('title', __('Shaxsiy muloqot'))
@section('content')
<h1 class="text-2xl font-semibold">{{ $assignment->mentor->name }} · {{ $assignment->student->name }}</h1><p>{{ $assignment->course->title }}</p>
@if ($errors->any())<p role="alert" class="text-red-700">{{ $errors->first() }}</p>@endif
<div class="mt-6 flex max-w-3xl flex-col gap-4">
<div data-chat-messages class="flex flex-col gap-3">@forelse ($messages->reverse() as $message)<article class="rounded-xl bg-white p-5"><p class="text-xs">{{ $message->sender->name }} · {{ $message->created_at->format('d.m.Y H:i') }} · {{ $message->read_at ? __('O‘qilgan') : __('Yetkazilgan') }}</p><p class="mt-2 whitespace-pre-line">{{ $message->body }}</p></article>@empty<p>{{ __('Hozircha xabar yo‘q.') }}</p>@endforelse</div>
{{ $messages->links() }}
<form method="POST" action="{{ route('conversations.store', $assignment) }}" class="flex flex-col gap-3 rounded-xl bg-white p-5">@csrf
<label>{{ __('Xabaringiz') }}<textarea name="body" rows="4" maxlength="5000" required class="block w-full rounded border p-2">{{ old('body') }}</textarea></label><button class="self-start rounded bg-blue-700 px-4 py-2 text-white">{{ __('Yuborish') }}</button>
</form>
<a href="{{ route('conversations.show', $assignment) }}" class="text-blue-700 underline">{{ __('Yangi xabarlarni ko‘rish') }}</a>
</div>
@endsection
