@extends('layouts.public')

@section('title', __('Aloqa') . ' — HUQUQ AI Academy')

@section('content')
    <section class="edu-public-section">
        <div class="edu-public-container max-w-2xl text-center py-16">
            <h1 class="edu-public-h2">{{ __('Aloqa') }}</h1>
            <p class="edu-public-lead mt-4">{{ __('Savollaringiz bo‘lsa, biz bilan bog‘laning.') }}</p>
            <div class="edu-public-mini-card mt-10 !text-left">
                <h2 class="edu-public-h2 !text-lg">{{ __('Bog‘lanish') }}</h2>
                <ul class="mt-4 space-y-2">
                    <li>📧 <span class="sr-only">{{ __('Email') }}</span> info@huquqai.academy</li>
                </ul>
                <p class="mt-4 text-sm opacity-70">{{ __('Ish vaqti: dushanba–shanba, 9:00–18:00.') }}</p>
            </div>
        </div>
    </section>
@endsection
