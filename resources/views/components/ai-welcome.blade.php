@props(['title' => null, 'description' => null, 'compact' => false])
<section {{ $attributes->class(['ai-welcome', 'ai-welcome-compact' => $compact]) }}>
    <img src="{{ asset('images/ai-tutor-mascot.png') }}" width="260" height="260" class="ai-mascot" alt="" fetchpriority="high">
    <span class="ai-eyebrow">HUQUQ AI · {{ __('Sizning ta’lim yordamchingiz') }}</span>
    <h1>{{ $title ?? __('AI Ustozga xush kelibsiz') }}</h1>
    <p>{{ $description ?? __('O‘rganing, savol bering va huquqiy bilimingizni rivojlantiring.') }}</p>
    {{ $slot }}
</section>
