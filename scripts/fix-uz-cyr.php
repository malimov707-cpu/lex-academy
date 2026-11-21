<?php

// Latin -> Cyrillic Uzbek transliteration for lang strings that were
// previously copied verbatim from lang/ru.json.

function apostrophes(string $s): string
{
    // U+02BB (ʻ) -> Ў/ў, U+02BC (ʼ) -> Ъ/ъ handled in tr() itself
    return str_replace(["\u{02bb}", "\u{02bc}", "\u{2019}", "\u{2018}"], ["\u{02bb}", "\u{02bc}", "\u{02bc}", "\u{02bc}"], $s);
}

function tr(string $s): string
{
    // 1) digraphs first (case-sensitive positions)
    $s = str_replace(
        ['Sh', 'Ch', 'SH', 'CH', 'sh', 'ch', "g\u{02bb}", "G\u{02bb}", "o\u{02bb}", "O\u{02bb}", "g'", "G'", "o'", "O'"],
        ['Ш', 'Ч', 'Ш', 'Ч', 'ш', 'ч', 'Ғ', 'Ғ', 'Ў', 'Ғ', 'Ғ', 'Ў', 'Ў'],
        $s
    );

    $map = [
        'a' => 'а', 'b' => 'б', 'c' => 'ц', 'd' => 'д', 'e' => 'е', 'f' => 'ф',
        'g' => 'г', 'h' => 'ҳ', 'i' => 'и', 'j' => 'ж', 'k' => 'к', 'l' => 'л',
        'm' => 'м', 'n' => 'н', 'o' => 'о', 'p' => 'п', 'q' => 'қ', 'r' => 'р',
        's' => 'с', 't' => 'т', 'u' => 'у', 'v' => 'в', 'x' => 'х', 'y' => 'й',
        'z' => 'з', 'w' => 'в', "'" => 'ъ', "\u{02bc}" => 'ъ',
        'A' => 'А', 'B' => 'Б', 'C' => 'Ц', 'D' => 'Д', 'E' => 'Е', 'F' => 'Ф',
        'G' => 'Г', 'H' => 'Ҳ', 'I' => 'И', 'J' => 'Ж', 'K' => 'К', 'L' => 'Л',
        'M' => 'М', 'N' => 'Н', 'O' => 'О', 'P' => 'П', 'Q' => 'Қ', 'R' => 'Р',
        'S' => 'С', 'T' => 'Т', 'U' => 'У', 'V' => 'В', 'X' => 'Х', 'Y' => 'Й',
        'Z' => 'З', 'W' => 'В',
    ];

    $out = '';
    foreach (mb_str_split($s) as $ch) {
        $out .= $map[$ch] ?? $ch;
    }

    return $out;
}

$file = 'lang/uz-cyr.json';
$cyr = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
$ru = json_decode((string) file_get_contents('lang/ru.json'), true, 512, JSON_THROW_ON_ERROR);

// Fix any corrupted entries left by the earlier failed inline run (stray "\u" literals / 'Ъ' vs 'ъ' etc.)
$changed = 0;
foreach ($cyr as $key => $value) {
    if (! isset($ru[$key]) || $ru[$key] !== $value) {
        continue; // already a genuine Cyrillic translation
    }
    if (! preg_match('/[a-zA-Z]/u', (string) $value)) {
        continue; // nothing Latin to convert
    }
    $cyr[$key] = tr((string) $value);
    $changed++;
}

file_put_contents($file, json_encode($cyr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n");
echo "converted: {$changed}\n";

// sanity checks
foreach (['Huquqni oʻrganing. Kelajakni yarating.', 'Taʼlim yoʻnalishlari', 'Birinchi ekran juda kuchli'] as $needle) {
    $found = false;
    foreach ($cyr as $k => $v) {
        if (str_contains((string) $v, $needle)) {
            echo $k, ' => ', $v, "\n";
            $found = true;
        }
    }
    if (! $found) {
        echo "(no latin copy of: {$needle})\n";
    }
}

$latinLeft = 0;
foreach ($cyr as $k => $v) {
    if (isset($ru[$k]) && $ru[$k] === $v && preg_match('/[a-zA-Z]/u', (string) $v)) {
        $latinLeft++;
        echo "STILL LATIN: {$k}\n";
    }
}
echo "latin copies remaining: {$latinLeft}\n";
