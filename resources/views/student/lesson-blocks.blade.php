<div class="mt-6 flex flex-col gap-6">
@foreach ($lesson->blocks as $block)
    @php $content = $block->content; $url = !empty($content['material_id']) ? route('materials.download', ['material' => $content['material_id'], 'inline' => 1]) : ($content['url'] ?? null); @endphp
    @switch($block->type)
        @case('heading')<h2 class="text-xl font-semibold">{{ $content['text'] }}</h2>@break
        @case('text')<p class="whitespace-pre-line leading-relaxed">{{ $content['text'] }}</p>@break
        @case('quote')<blockquote class="border-l-4 border-blue-700 pl-5 italic whitespace-pre-line">{{ $content['text'] }}</blockquote>@break
        @case('legal_citation')<aside class="rounded-xl bg-amber-50 p-5"><p class="whitespace-pre-line">{{ $content['text'] }}</p>@if (!empty($content['url']))<a href="{{ $content['url'] }}" rel="noopener noreferrer" target="_blank" class="text-blue-700 underline">{{ __('Huquqiy manba') }}</a>@endif</aside>@break
        @case('image')<figure><img src="{{ $url }}" alt="{{ $content['text'] ?? '' }}" loading="lazy" class="max-h-96 max-w-full rounded-xl object-contain"><figcaption class="mt-2 text-sm">{{ $content['text'] ?? '' }}</figcaption></figure>@break
        @case('video')<figure><video src="{{ $url }}" controls preload="metadata" class="w-full rounded-xl"></video><figcaption class="mt-2 text-sm">{{ $content['text'] ?? '' }}</figcaption></figure>@break
        @case('audio')<figure><audio src="{{ $url }}" controls preload="metadata" class="w-full"></audio><figcaption class="mt-2 text-sm">{{ $content['text'] ?? '' }}</figcaption></figure>@break
        @case('document')<a href="{{ $url }}" class="rounded-xl border p-4 text-blue-700 underline" target="_blank" rel="noopener noreferrer">{{ ($content['text'] ?? '') ?: __('Hujjatni ochish') }}</a>@break
        @case('quiz')<a href="{{ route('student.quiz.show', [$course, $content['resource_id']]) }}" class="rounded-xl border p-4 text-blue-700 underline">{{ ($content['text'] ?? '') ?: __('Testni ochish') }}</a>@break
        @case('assignment')<a href="{{ route('student.assignments') }}" class="rounded-xl border p-4 text-blue-700 underline">{{ ($content['text'] ?? '') ?: __('Topshiriqni ochish') }}</a>@break
        @case('interactive_question')
        <section class="rounded-xl bg-blue-50 p-5"><h3 class="font-semibold">{{ $content['text'] }}</h3>
            @if (empty($preview))
            @php $response = $responses->get($block->id); @endphp
            <form method="POST" action="{{ route('student.blocks.respond', $block) }}" class="mt-3 flex flex-col gap-3">@csrf<label>{{ __('Javob') }}<input name="answer" value="{{ $response?->answer }}" maxlength="4000" required class="block w-full rounded border bg-white p-2"></label><button class="self-start rounded bg-blue-700 px-4 py-2 text-white">{{ __('Tekshirish') }}</button></form>
            @if ($response)<p class="mt-3 text-sm">{{ $response->is_correct ? __('To‘g‘ri javob!') : __('Qayta o‘ylab ko‘ring.') }}</p>@endif
            @endif
        </section>@break
    @endswitch
@endforeach
</div>
