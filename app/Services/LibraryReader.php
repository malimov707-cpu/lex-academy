<?php

namespace App\Services;

class LibraryReader
{
    /** @return list<array{text: string, offset: int}> */
    public function pages(string $body): array
    {
        $pages = [];
        $offset = 0;
        $length = mb_strlen($body);
        while ($offset < $length) {
            $text = mb_substr($body, $offset, 14000);
            if ($offset + 14000 < $length) {
                $boundary = mb_strrpos($text, "\n\n");
                if ($boundary !== false && $boundary > 6000) {
                    $text = mb_substr($text, 0, $boundary + 2);
                }
            }
            $pages[] = ['text' => $text, 'offset' => $offset];
            $offset += mb_strlen($text);
        }

        return $pages;
    }

    /** @param list<array{text: string, offset: int}> $pages */
    public function pageAt(array $pages, int $offset): int
    {
        foreach ($pages as $index => $page) {
            if ($offset < $page['offset'] + mb_strlen($page['text'])) {
                return $index + 1;
            }
        }

        return max(1, count($pages));
    }

    /**
     * @param  list<array{text: string, offset: int}>  $pages
     * @return list<array{excerpt: string, page: int}>
     */
    public function find(string $body, string $term, array $pages): array
    {
        if (mb_strlen($term) < 2) {
            return [];
        }
        $matches = [];
        $offset = 0;
        while (count($matches) < 20 && ($position = mb_stripos($body, $term, $offset)) !== false) {
            $matches[] = ['excerpt' => mb_substr($body, max(0, $position - 80), mb_strlen($term) + 180), 'page' => $this->pageAt($pages, $position)];
            $offset = $position + mb_strlen($term);
        }

        return $matches;
    }
}
