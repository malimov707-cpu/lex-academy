@extends('layouts.auth')

@section('title', __('Ro‘yxatdan o‘tish'))

@section('content')
<h1 class="text-xl font-semibold">{{ __('Hisob yaratish') }}</h1>
<p class="mt-1 text-sm" style="color:var(--edu-text-muted)">{{ __('HUQUQ AI Academy — talaba sifatida ro‘yxatdan o‘ting.') }}</p>

<form method="POST" action="{{ route('register') }}" class="mt-6 space-y-3.5">
    @csrf
    <div>
        <label for="name" class="block text-sm font-medium">{{ __('To‘liq ism') }}</label>
        <input id="name" name="name" type="text" required autofocus autocomplete="name" value="{{ old('name') }}"
               class="edu-input mt-1">
        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="email" class="block text-sm font-medium">Email</label>
        <input id="email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}"
               class="edu-input mt-1">
        @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="phone" class="block text-sm font-medium">{{ __('Telefon') }} <span class="font-normal opacity-60">({{ __('ixtiyoriy') }})</span></label>
        <input id="phone" name="phone" type="tel" autocomplete="tel" value="{{ old('phone') }}" placeholder="+998 90 123 45 67"
               class="edu-input mt-1">
    </div>
    <div class="grid gap-3.5 sm:grid-cols-2">
        <div>
            <label for="password" class="block text-sm font-medium">{{ __('Parol') }}</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   class="edu-input mt-1">
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium">{{ __('Tasdiqlash') }}</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   class="edu-input mt-1">
        </div>
    </div>
    @error('password')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    <p class="text-[0.68rem]" style="color:var(--edu-text-muted)">{{ __('Parol kamida 8 belgi, harf va raqam bo‘lishi kerak.') }}</p>

    <button type="submit" class="edu-btn edu-btn-primary w-full !py-2.5">{{ __('Ro‘yxatdan o‘tish') }}</button>
</form>

<p class="mt-5 border-t pt-4 text-center text-sm" style="border-color:var(--edu-line);color:var(--edu-text-muted)">
    {{ __('Hisobingiz bormi?') }}
    <a href="{{ route('login') }}" class="font-medium hover:underline">{{ __('Kirish') }}</a>
</p>
@endsection
