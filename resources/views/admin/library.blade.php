@extends('layouts.app')
@section('title', __('Kutubxonani boshqarish'))
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4"><h1 class="text-2xl font-semibold">{{ __('Kutubxonani boshqarish') }}</h1><a href="{{ route('library.index') }}" class="text-blue-700">{{ __('Huquqiy kutubxona') }} ↗</a></div>
<details class="edu-panel mt-5"><summary class="cursor-pointer font-semibold">+ {{ __('Yangi material') }}</summary><form method="POST" action="{{ route('admin.library.store') }}" class="mt-5">@csrf @include('admin.library-fields', ['resource' => null])<button class="edu-btn edu-btn-primary mt-4">{{ __('Saqlash') }}</button></form></details>
<div class="mt-5 space-y-3">@foreach($resources as $resource)<article class="edu-card flex flex-wrap items-center justify-between gap-4 p-4"><div><h2 class="font-medium">{{ $resource->title }}</h2><p class="mt-1 text-xs text-slate-500">{{ __($types[$resource->type]) }} · {{ __($resource->status) }} · {{ $resource->topic }}</p></div><a href="{{ route('admin.library.edit', $resource) }}" class="text-sm text-blue-700">{{ __('Tahrirlash') }} →</a></article>@endforeach</div><div class="mt-5">{{ $resources->links() }}</div>
@endsection
