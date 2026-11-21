@extends(auth()->user()?->hasRole(\App\Enums\UserRole::Student) ? 'layouts.student' : 'layouts.app')
@section('title', __('Shaxsiy muloqot'))
@section('content')
<h1 class="text-2xl font-semibold">{{ __('Mentor bilan shaxsiy muloqot') }}</h1>
<div class="mt-6 grid gap-4 md:grid-cols-2">
@forelse ($assignments as $assignment)<a href="{{ route('conversations.show', $assignment) }}" class="rounded-2xl bg-white p-6"><h2 class="font-semibold">{{ $assignment->course->title }}</h2><p class="mt-2">{{ $assignment->mentor->name }} · {{ $assignment->student->name }}</p></a>@empty<p>{{ __('Aktiv mentor biriktirishi yo‘q.') }}</p>@endforelse
</div>{{ $assignments->links() }}
@endsection
