<?php

namespace App\Console\Commands;

use App\Models\VoiceTranscriptSegment;
use Illuminate\Console\Command;

class PruneCourtroomTranscripts extends Command
{
    protected $signature = 'courtroom:prune-transcripts';

    protected $description = 'Delete expired optional voice transcripts according to the configured retention period';

    public function handle(): int
    {
        $days = max(1, (int) config('virtual_courtroom.voice.transcript_retention_days'));
        $deleted = VoiceTranscriptSegment::query()->where('created_at', '<', now()->subDays($days))->delete();
        $this->info('Expired voice transcript segments deleted: '.$deleted);

        return self::SUCCESS;
    }
}
