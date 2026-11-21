@extends('layouts.app')

@section('title', $title)

@section('content')
<main class="mx-auto w-full max-w-7xl px-4 py-8 lg:px-8">
    @include('layouts.partials.feedback')
    <div class="mb-6">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $group ? __('Guruh tarixi') : __('Talaba natijalari tarixi') }}</p>
        <h1 class="mt-1 text-3xl font-bold">{{ $title }}</h1>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800"><tr><th class="p-4">{{ __('Sana') }}</th><th class="p-4">{{ __('Ssenariy') }}</th><th class="p-4">{{ __('Natija') }}</th><th class="p-4">{{ __('Rol') }}</th><th class="p-4">{{ $group ? __('O‘rtacha individual') : __('Individual') }}</th><th class="p-4">{{ __('Faollik') }}</th><th class="p-4">{{ __('Yakuniy baho') }}</th></tr></thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr class="border-t border-slate-200 dark:border-slate-700">
                            <td class="p-4">{{ $row['date'] }}</td>
                            <td class="p-4"><a class="font-semibold underline" href="{{ route('courtroom.results.show', $row['session_id']) }}">{{ $row['scenario'] }}</a></td>
                            <td class="p-4">{{ $row['winner'] ? \App\Services\VirtualCourtroom\GroupResultReport::winnerLabel($row['winner']) : '—' }}</td>
                            <td class="p-4">{{ $row['role'] ?? '—' }}</td>
                            <td class="p-4">{{ $row['individual'] === null ? '—' : number_format((float) $row['individual'], 1) }}</td>
                            <td class="p-4">{{ $row['activity'] === null ? '—' : number_format((float) $row['activity'], 1) }}</td>
                            <td class="p-4">{{ $row['final'] === null ? '—' : number_format((float) $row['final'], 1) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @if($page)
        <div class="mt-5">{{ $page->links() }}</div>
    @endif
</main>
@endsection
