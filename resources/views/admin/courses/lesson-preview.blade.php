@extends('layouts.app')
@section('title', __('Darsni oldindan ko‘rish'))
@section('content')
<a href="{{ route('admin.lessons.edit', $lesson) }}" class="text-blue-700 underline">{{ __('Muharrirga qaytish') }}</a><article class="mt-4 max-w-4xl rounded-2xl bg-white p-8"><h1 class="text-2xl font-semibold">{{ $lesson->title }}</h1><p class="mt-6 whitespace-pre-line">{{ $lesson->body }}</p>@include('student.lesson-blocks')</article>
@endsection
