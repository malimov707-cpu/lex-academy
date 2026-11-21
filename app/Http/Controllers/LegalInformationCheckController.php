<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiUnavailableException;
use App\Services\LegalClaimVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalInformationCheckController extends Controller
{
    public function index(): View
    {
        return view('legal-information-check.index');
    }

    public function store(Request $request, LegalClaimVerifier $verifier): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'min:20', 'max:4000'],
        ], [
            'text.required' => __('Tekshirish uchun matn kiriting.'),
            'text.min' => __('Matn kamida 20 belgidan iborat bo‘lsin.'),
            'text.max' => __('Matn 4 000 belgidan oshmasin.'),
        ]);

        try {
            $result = $verifier->verify($request->user(), trim($data['text']));
        } catch (AiUnavailableException $exception) {
            return response()->json(['error' => $exception->getMessage()], 503)
                ->header('Cache-Control', 'private, no-store');
        }

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }
}
