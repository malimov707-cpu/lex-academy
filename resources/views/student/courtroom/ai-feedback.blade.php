<section class="courtroom-info-panel courtroom-learning-feedback">
    <h2>{{ __('AI tavsiyalari') }}</h2>
    @foreach($evaluation->ai_feedback as $item)
        <article>
            <h3>{{ $evaluation->criterionResults->firstWhere('criterion.key', $item['criterion_key'])?->criterion->name }}</h3>
            <p>{{ $item['observation'] }}</p>
            <p><strong>{{ __('Keyingi mashq') }}</strong> {{ $item['next_exercise'] }}</p>
            <details>
                <summary>{{ __('Tavsiyaga asos bo‘lgan harakatlar') }}</summary>
                <ul>
                    @foreach($actions->whereIn('id', $item['action_ids']) as $action)
                        <li>#{{ $action->id }} · {{ $action->stage_key }} · {{ Str::limit($action->content, 200) }}</li>
                    @endforeach
                </ul>
            </details>
        </article>
    @endforeach
</section>
