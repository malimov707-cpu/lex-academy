<?php

namespace App\Services;

use App\Models\LibraryResource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class LibraryContentImporter
{
    public function __construct(private LegalDocumentExtractor $extractor) {}

    public function importLaw(LibraryResource $resource): void
    {
        if (! in_array($resource->type, ['constitution', 'code', 'law'], true) || ! preg_match('~\Ahttps://lex\.uz/docs/-[0-9]+\z~', $resource->url ?? '') || $resource->access !== 'open') {
            throw new RuntimeException('Faqat katalogdagi ochiq LexUZ hujjatlarini import qilish mumkin.');
        }
        $response = Http::connectTimeout(10)->timeout(45)->withoutRedirecting()->withOptions([
            'on_headers' => function ($response): void {
                if ((int) $response->getHeaderLine('Content-Length') > 20 * 1024 * 1024) {
                    throw new RuntimeException('Hujjat hajmi limitdan oshdi.');
                }
            },
        ])->get($resource->url)->throw();
        $html = $response->body();
        if (strlen($html) > 20 * 1024 * 1024) {
            throw new RuntimeException('Hujjat hajmi limitdan oshdi.');
        }
        $document = $this->extractor->extract($html);
        $attribution = $resource->title.'. Manba: '.$resource->url.'. Nusxa olingan sana: '.now()->toDateString().'. Rasmiy hujjat matni; LexUZ tahririy sharhlari va interfeysi kiritilmagan. HTMLdan oddiy matnga ajratildi; jadval va format farqlari bo‘lishi mumkin. Nusxa amaldagi yuridik tahrirni kafolatlamaydi.';
        $text = $attribution."\n\n".$document['body'];
        $hash = hash('sha256', $text);
        $path = 'library/'.$resource->catalog_key.'/'.$hash.'.txt';
        if (! Storage::disk('local')->put($path, $text)) {
            throw new RuntimeException('Matnni lokal saqlash muvaffaqiyatsiz.');
        }
        $resource->update(['body' => $document['body'], 'contents' => $document['contents'], 'local_path' => $path, 'local_format' => 'txt',
            'content_sha256' => $hash, 'content_bytes' => strlen($text), 'imported_at' => now(), 'import_source_url' => $resource->url,
            'license' => 'Rasmiy hujjat — O‘RQ-42, 8-modda', 'license_url' => 'https://lex.uz/docs/-1022944', 'attribution' => $attribution]);
    }

    public function importBook(string $key): void
    {
        $book = config('library_content.books.'.$key);
        if (! is_array($book) || ! in_array(parse_url($book['download_url'], PHP_URL_HOST), ['assets.openstax.org', 'tile.loc.gov'], true)
            || ! str_starts_with($book['download_url'], 'https://') || $book['license'] !== 'CC BY 4.0') {
            throw new RuntimeException('Kitob tasdiqlangan ochiq import ro‘yxatida yo‘q.');
        }
        $disk = Storage::disk('local');
        $temporary = 'library/incoming-'.Str::uuid().'.pdf';
        $disk->makeDirectory('library');
        try {
            $response = Http::connectTimeout(10)->timeout(120)->withoutRedirecting()->withOptions([
                'sink' => $disk->path($temporary),
                'progress' => function ($total, $downloaded): void {
                    if ($downloaded > 64 * 1024 * 1024 || $total > 64 * 1024 * 1024) {
                        throw new RuntimeException('Kitob hajmi limitdan oshdi.');
                    }
                },
            ])->get($book['download_url'])->throw();
            if ($response->status() !== 200 || ! $disk->exists($temporary)) {
                throw new RuntimeException('Kitob fayli olinmadi.');
            }
            $file = fopen($disk->path($temporary), 'rb');
            $signature = fread($file, 5);
            $size = $disk->size($temporary);
            fseek($file, max(0, $size - 8192));
            $trailer = stream_get_contents($file);
            fclose($file);
            if ($signature !== '%PDF-' || $size < 10000 || $size > 64 * 1024 * 1024 || ! str_contains($trailer, '%%EOF')) {
                throw new RuntimeException('Manba haqiqiy PDF qaytarmadi.');
            }
            $hash = hash_file('sha256', $disk->path($temporary));
            $path = 'library/'.$key.'/'.$hash.'.pdf';
            $disk->makeDirectory('library/'.$key);
            if (! $disk->exists($path) && ! $disk->move($temporary, $path)) {
                throw new RuntimeException('Kitobni lokal saqlash muvaffaqiyatsiz.');
            }
            LibraryResource::query()->updateOrCreate(['catalog_key' => $key], collect($book)->except('download_url')->all() + [
                'type' => 'textbook', 'language' => 'en', 'status' => 'published', 'access' => 'open',
                'local_path' => $path, 'local_format' => 'pdf', 'content_sha256' => $hash, 'content_bytes' => $disk->size($path),
                'imported_at' => now(), 'import_source_url' => $book['download_url'], 'checked_at' => today(),
            ]);
        } finally {
            $disk->delete($temporary);
        }
    }
}
