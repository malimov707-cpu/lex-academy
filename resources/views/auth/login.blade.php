@extends('layouts.auth')

@section('title', __('Kirish'))

@section('content')
<h1 class="text-xl font-semibold">{{ __('Tizimga kirish') }}</h1>
<p class="mt-1 text-sm" style="color:var(--edu-text-muted)">{{ __('HUQUQ AI Academy — huquqiy ta’lim platformasi.') }}</p>

<form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
    @csrf
    <div>
        <label for="email" class="block text-sm font-medium">Email</label>
        <input id="email" name="email" type="email" required autofocus autocomplete="email" value="{{ old('email') }}"
               class="edu-input mt-1">
        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <div class="flex items-center justify-between">
            <label for="password" class="block text-sm font-medium">{{ __('Parol') }}</label>
        </div>
        <input id="password" name="password" type="password" required autocomplete="current-password"
               class="edu-input mt-1">
        @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="remember" class="rounded"> {{ __('Meni eslab qol') }}
    </label>
    <button type="submit" class="edu-btn edu-btn-primary w-full !py-2.5">{{ __('Kirish') }}</button>
</form>

<p class="mt-5 border-t pt-4 text-center text-sm" style="border-color:var(--edu-line);color:var(--edu-text-muted)">
    {{ __('Hisobingiz yo‘qmi?') }}
    <a href="{{ route('register') }}" class="font-medium hover:underline">{{ __('Ro‘yxatdan o‘tish') }}</a>
</p>
@endsection
