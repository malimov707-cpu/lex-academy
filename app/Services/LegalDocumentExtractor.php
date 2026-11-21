<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use RuntimeException;

class LegalDocumentExtractor
{
    /** @return array{body: string, contents: list<array{title: string, offset: int}>} */
    public function extract(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $root = $xpath->query('//*[@id="divCont"]')->item(0);
        if (! $root) {
            throw new RuntimeException('LexUZ hujjat konteyneri topilmadi. Eski nusxa saqlandi.');
        }
        $allowed = ['ACT_FORM', 'ACT_TITLE', 'ACT_TEXT', 'CLAUSE_DEFAULT', 'TEXT_HEADER_DEFAULT', 'TEXT_BOLD_CENTER', 'BY_DEFAULT'];
        $parts = [];
        $contents = [];
        $offset = 0;
        foreach ($xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " lx_elem ")]', $root) as $element) {
            $type = explode(' ', $element->getAttribute('class'))[0];
            if (! in_array($type, $allowed, true)) {
                continue;
            }
            foreach (iterator_to_array($xpath->query('.//*[contains(@class,"lx_elem2") or contains(@class,"COMMENT") or contains(@class,"INDEXES_ON_REF") or self::script or self::style]', $element)) as $remove) {
                $remove->parentNode?->removeChild($remove);
            }
            foreach (iterator_to_array($xpath->query('.//br', $element)) as $break) {
                $break->parentNode->replaceChild($document->createTextNode("\n"), $break);
            }
            foreach ($xpath->query('.//tr', $element) as $row) {
                foreach ($xpath->query('./td | ./th', $row) as $cell) {
                    $cell->appendChild($document->createTextNode(' | '));
                }
                $row->appendChild($document->createTextNode("\n"));
            }
            $text = trim(preg_replace('/[\p{Z}\t]+/u', ' ', $element->textContent));
            if ($text === '') {
                continue;
            }
            if (in_array($type, ['ACT_TITLE', 'CLAUSE_DEFAULT', 'TEXT_HEADER_DEFAULT', 'TEXT_BOLD_CENTER'], true)) {
                $contents[] = ['title' => mb_substr($text, 0, 200), 'offset' => $offset];
            }
            $parts[] = $text;
            $offset += mb_strlen($text) + 2;
        }
        $body = implode("\n\n", $parts);
        if (mb_strlen($body) < 1000 || count($contents) < 2) {
            throw new RuntimeException('Hujjat matni to‘liq ajratilmadi. Eski nusxa saqlandi.');
        }

        return ['body' => $body, 'contents' => $contents];
    }
}
