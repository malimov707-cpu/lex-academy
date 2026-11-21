<?php

namespace App\Console\Commands;

use App\Services\Ai\AiUnavailableException;
use App\Services\VirtualCourtroom\CourtroomSpeechProvider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

#[Signature('courtroom:check-voice {--locale= : uz-Latn, uz-Cyrl or ru} {--tts-only : Check synthesized audio and timing only} {--input= : Check transcription using a local recording of the locale test sentence}')]
#[Description('Run live courtroom voice checks using synthetic speech and no student data')]
class CheckCourtroomVoice extends Command
{
    private const SAMPLES = [
        'uz-Latn' => 'Sud majlisini ochaman. Guvoh Olim Qodirov ikkinchi dalil va yigirma beshinchi mart sanasini aytdi.',
        'uz-Cyrl' => 'Суд мажлисини очаман. Гувоҳ Олим Қодиров иккинчи далил ва йигирма бешинчи март санасини айтди.',
        'ru' => 'Открываю судебное заседание. Свидетель Олег Соколов назвал второе доказательство и дату двадцать пятого марта.',
    ];

    public function handle(CourtroomSpeechProvider $speech): int
    {
        $locale = $this->option('locale');
        if ($locale !== null && ! isset(self::SAMPLES[$locale])) {
            $this->error('Locale must be uz-Latn, uz-Cyrl or ru.');

            return self::FAILURE;
        }
        $input = $this->option('input');
        if ($input !== null && ($locale === null || $this->option('tts-only') || ! is_file($input) || ! is_readable($input) || filesize($input) > 8 * 1024 * 1024)) {
            $this->error('--input requires --locale and a readable test recording up to 8 MB.');

            return self::FAILURE;
        }
        $samples = $locale === null ? self::SAMPLES : [$locale => self::SAMPLES[$locale]];
        $enabled = config('virtual_courtroom.voice.server_speech_enabled');
        config()->set('virtual_courtroom.voice.server_speech_enabled', true);
        $this->line($input === null ? 'Synthetic speech check. No microphone or student data is used.' : 'Voice check with the supplied test recording.');

        try {
            foreach ($samples as $language => $text) {
                if ($input !== null) {
                    $this->measureTranscription($speech, new UploadedFile($input, basename($input), test: true), $language, $text);

                    continue;
                }
                $started = hrtime(true);
                $audio = $speech->synthesize($text, $language);
                $this->info(sprintf('%s TTS OK | %d bytes | %d ms | %d timed visemes', $language, strlen($audio['audio']), (hrtime(true) - $started) / 1e6, count($audio['visemes'])));
                if ($this->option('tts-only')) {
                    continue;
                }

                $temporary = tmpfile();
                if ($temporary === false) {
                    $this->error('Cannot create a temporary audio file for the test.');

                    return self::FAILURE;
                }
                try {
                    if (fwrite($temporary, $audio['audio']) !== strlen($audio['audio'])) {
                        $this->error('Cannot write the temporary audio file.');

                        return self::FAILURE;
                    }
                    $name = $audio['mime'] === 'audio/wav' ? 'synthetic.wav' : 'synthetic.mp3';
                    $this->measureTranscription($speech, new UploadedFile(stream_get_meta_data($temporary)['uri'], $name, $audio['mime'], test: true), $language, $text);
                } finally {
                    fclose($temporary);
                }
            }
        } catch (AiUnavailableException $exception) {
            $this->error($language.' — '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            config()->set('virtual_courtroom.voice.server_speech_enabled', $enabled);
        }

        return self::SUCCESS;
    }

    private function measureTranscription(CourtroomSpeechProvider $speech, UploadedFile $audio, string $language, string $text): void
    {
        $started = hrtime(true);
        $transcript = $speech->transcribe($audio, $language);
        $this->line(sprintf('%s STT OK | %d ms | word error %.1f%%', $language, (hrtime(true) - $started) / 1e6, 100 * $this->wordError($text, $transcript)));
        $this->line('Expected: '.$text);
        $this->line('Heard: '.$transcript);
    }

    private function wordError(string $expected, string $heard): float
    {
        $words = static fn (string $text): array => preg_split('/[^\p{L}\p{N}\x{27}]+/u', mb_strtolower(str_replace(['‘', '’', 'ʻ', 'ʼ'], "'", $text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $reference = $words($expected);
        $hypothesis = $words($heard);
        $row = range(0, count($hypothesis));
        foreach ($reference as $index => $word) {
            $next = [$index + 1];
            foreach ($hypothesis as $column => $candidate) {
                $next[] = min($next[$column] + 1, $row[$column + 1] + 1, $row[$column] + (int) ($word !== $candidate));
            }
            $row = $next;
        }

        return $row[count($hypothesis)] / max(1, count($reference));
    }
}
