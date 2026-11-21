<?php

namespace App\Services;

use App\Models\LegalSource;
use App\Models\LegalSourceCheck;
use App\Models\LegalTopicSubscription;
use App\Models\LibraryResource;
use App\Notifications\AcademyNotification;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LegalSourceMonitor
{
    public function check(LegalSource $source): LegalSourceCheck
    {
        $checkedAt = now();
        $host = strtolower((string) parse_url($source->url, PHP_URL_HOST));
        $allowedHosts = config('services.legal_source_monitor.allowed_hosts', []);
        $scheme = strtolower((string) parse_url($source->url, PHP_URL_SCHEME));
        $port = parse_url($source->url, PHP_URL_PORT);
        $hasCredentials = parse_url($source->url, PHP_URL_USER) !== null
            || parse_url($source->url, PHP_URL_PASS) !== null;

        if ($source->status !== 'verified' || ! $source->verified_at || ! $source->verified_by
            || $scheme !== 'https' || ! in_array($host, $allowedHosts, true)
            || ! in_array($port, [null, 443], true) || $hasCredentials) {
            return $source->checks()->create([
                'status' => 'unapproved',
                'checked_at' => $checkedAt,
            ]);
        }

        try {
            $response = Http::accept('text/html,application/xhtml+xml')
                ->withUserAgent('LegalEDU-LegalSourceMonitor/1.0 (+https://lex.uz)')
                ->connectTimeout(5)
                ->timeout(15)
                ->withoutRedirecting()
                ->get($source->url);
        } catch (ConnectionException) {
            return $source->checks()->create([
                'status' => 'unavailable',
                'checked_at' => $checkedAt,
            ]);
        }

        if (! $response->ok()) {
            return $source->checks()->create([
                'status' => 'unavailable',
                'http_status' => $response->status(),
                'checked_at' => $checkedAt,
            ]);
        }

        $text = $this->extractComparableText($response->body());
        if ($text === '') {
            return $source->checks()->create([
                'status' => 'unavailable',
                'http_status' => $response->status(),
                'checked_at' => $checkedAt,
            ]);
        }

        $hash = hash('sha256', $text);
        $previousHash = $source->checks()
            ->whereNotNull('content_sha256')
            ->latest('checked_at')
            ->value('content_sha256');

        $status = $previousHash === null ? 'baseline' : ($previousHash === $hash ? 'unchanged' : 'changed');
        $checkData = [
            'status' => $status,
            'http_status' => $response->status(),
            'content_sha256' => $hash,
            'etag' => Str::limit((string) $response->header('ETag'), 255, ''),
            'source_last_modified' => Str::limit((string) $response->header('Last-Modified'), 255, ''),
            'checked_at' => $checkedAt,
        ];

        return DB::transaction(function () use ($source, $checkData, $status): LegalSourceCheck {
            $check = $source->checks()->create($checkData);
            if ($status === 'changed') {
                $this->notifyTopicSubscribers($source);
            }

            return $check;
        });
    }

    private function notifyTopicSubscribers(LegalSource $source): void
    {
        $topics = LibraryResource::query()
            ->where('status', 'published')
            ->where('url', $source->url)
            ->distinct()
            ->pluck('topic')
            ->when($source->topic, fn ($topics) => $topics->push($source->topic))
            ->unique()
            ->values();
        if ($topics->isEmpty()) {
            return;
        }

        LegalTopicSubscription::query()
            ->with('user')
            ->whereIn('topic', $topics)
            ->get()
            ->each(function (LegalTopicSubscription $subscription): void {
                $subscription->user?->notify(new AcademyNotification(
                    'Huquqiy manba sahifasida o‘zgarish aniqlandi: :topic',
                    route('legal-updates.index'),
                    ['topic' => $subscription->topic],
                ));
            });
    }

    private function extractComparableText(string $html): string
    {
        $withoutNonContent = preg_replace([
            '/<script\b[^>]*>.*?<\/script>/is',
            '/<style\b[^>]*>.*?<\/style>/is',
            '/<!--.*?-->/s',
        ], ' ', $html) ?? '';
        $textWithoutMarkup = preg_replace('/<[^>]*>/u', ' ', $withoutNonContent) ?? '';
        $text = html_entity_decode($textWithoutMarkup, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
