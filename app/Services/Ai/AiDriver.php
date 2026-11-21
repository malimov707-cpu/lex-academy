<?php

namespace App\Services\Ai;

/**
 * AI Ustoz driver contract.
 *
 * Har qanday provayder (OpenAI, Anthropic, mahalliy FastAPI/RAG servis)
 * shu interfeysni amalga oshiradi. Integratsiya kaliti yo'q bo'lsa
 * DisabledDriver ishlatiladi — u soxta javob QAYTARMAYDI.
 */
interface AiDriver
{
    /**
     * @param  list<array{role: string, content: string}>  $system
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{content: string, confidence: float|null, total_tokens?: int|null}
     *
     * @throws AiUnavailableException
     */
    public function complete(array $system, array $messages): array;
}
