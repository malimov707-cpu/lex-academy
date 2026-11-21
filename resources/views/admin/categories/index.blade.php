@extends('layouts.app')
@section('title', __('Kategoriyalar'))
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4">
    <h1 class="text-2xl font-semibold">{{ __('Kategoriyalar') }}</h1>
    <span class="rounded-full bg-white px-3 py-1 text-sm shadow">{{ __('Jami') }}: {{ $categories->total() }}</span>
</div>
@if ($errors->any())<p role="alert" class="mt-3 text-red-700">{{ $errors->first() }}</p>@endif
<div class="mt-6 grid items-start gap-6 xl:grid-cols-[1fr_320px]">
    <div class="flex flex-col gap-4">
        <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
            @foreach ($categories as $category)
                <article class="rounded-xl bg-white p-5 shadow-sm">
                    <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="flex flex-col gap-3">
                        @csrf
                        @method('PUT')
                        <label>{{ __('Nomi') }}
                            <input name="name" value="{{ $category->name }}" required class="mt-1 block w-full rounded border p-2">
                        </label>
                        <label>{{ __('Tavsif') }}
                            <textarea name="description" rows="2" class="mt-1 block w-full rounded border p-2">{{ $category->description }}</textarea>
                        </label>
                        <div class="flex items-center gap-2">
                            <button type="submit" class="rounded bg-blue-700 px-4 py-2 text-white">{{ __('Saqlash') }}</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" data-confirm="{{ __('Ishonchingiz komilmi?') }}" onsubmit="return confirm(this.dataset.confirm)" class="mt-2">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded bg-red-600 px-4 py-2 text-white">{{ __('O‘chirish') }}</button>
                    </form>
                </article>
            @endforeach
        </div>
        <div>{{ $categories->links() }}</div>
    </div>
    <form method="POST" action="{{ route('admin.categories.store') }}" class="flex flex-col gap-3 self-start rounded-xl bg-white p-5 shadow-sm xl:sticky xl:top-4">
        @csrf
        <h2 class="font-semibold">{{ __('Yangi kategoriya') }}</h2>
        <label>{{ __('Nomi') }}
            <input name="name" required class="mt-1 block w-full rounded border p-2">
        </label>
        <label>{{ __('Tavsif') }}
            <textarea name="description" rows="3" class="mt-1 block w-full rounded border p-2"></textarea>
        </label>
        <button type="submit" class="rounded bg-blue-700 p-2 text-white">{{ __('Yaratish') }}</button>
    </form>
</div>
@endsection
