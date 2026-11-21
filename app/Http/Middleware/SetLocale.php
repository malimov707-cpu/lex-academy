<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Laravel locale → Carbon locale.
     * Diqqat: `uz` emas, `uz_Latn` — `uz` fayli eski ruscha matnlarni qaytaradi.
     */
    private const CARBON = [
        'uz-latin' => 'uz_Latn',
        'uz-cyr' => 'uz_Cyrl',
        'ru' => 'ru',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', $request->cookie('academy_locale') ?? $request->user()?->locale ?? 'uz-latin');
        $locale = in_array($locale, ['uz-latin', 'uz-cyr', 'ru'], true) ? $locale : 'uz-latin';

        App::setLocale($locale);
        Carbon::setLocale(self::CARBON[$locale] ?? 'uz');

        return $next($request);
    }
}
