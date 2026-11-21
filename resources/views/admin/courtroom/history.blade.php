@extends('layouts.app')
@section('title', __('Ssenariy versiyalari'))
@section('content')
<a href="{{ route('admin.courtroom.edit', $scenario) }}">← {{ __('Ssenariyga qaytish') }}</a><h1 class="edu-page-title mt-3">{{ __('Versiyalar tarixi') }}</h1><p class="edu-page-lead">{{ $scenario->title }}</p>
<form method="POST" action="{{ route('admin.courtroom.duplicate', $scenario) }}" class="mt-4">@csrf<button class="edu-btn edu-btn-secondary">{{ __('Alohida ssenariy sifatida nusxalash') }}</button></form>
<div class="edu-card mt-6 overflow-x-auto"><table class="edu-table"><thead><tr><th>{{ __('Versiya') }}</th><th>{{ __('Holat') }}</th><th>{{ __('Tarkib') }}</th><th>{{ __('Nashr sanasi') }}</th><th>{{ __('Tekshirish') }}</th></tr></thead><tbody>@foreach($versions as $item)<tr><td>v{{ $item->version }}</td><td>{{ $item->status }}</td><td>{{ $item->roles_count }} {{ __('rol') }} · {{ $item->stages_count }} {{ __('bosqich') }} · {{ $item->evidence_count }} {{ __('dalil') }}</td><td>{{ $item->published_at?->format('d.m.Y H:i') ?? '—' }}</td><td><a href="{{ route('admin.courtroom.preview', ['scenario' => $scenario, 'version' => $item->version]) }}">{{ __('Oldindan ko‘rish') }} →</a></td></tr>@endforeach</tbody></table>{{ $versions->links() }}</div>
@endsection
