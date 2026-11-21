<?php

namespace App\Console\Commands;

use App\Models\LegalSource;
use App\Services\LegalSourceMonitor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('legal:sources:check {--source= : Check one legal source by ID instead of all verified sources}')]
#[Description('Check approved official legal sources for page changes that need human review')]
class CheckLegalSources extends Command
{
    public function handle(LegalSourceMonitor $monitor): int
    {
        $sourceId = $this->option('source');
        $sources = LegalSource::query()->where('status', 'verified')
            ->whereNotNull('verified_at')
            ->whereNotNull('verified_by');

        if ($sourceId !== null) {
            $sources->whereKey($sourceId);
        }

        $records = $sources->orderBy('id')->get();
        if ($sourceId !== null && $records->isEmpty()) {
            $this->error('Verified legal source not found.');

            return self::FAILURE;
        }

        if ($records->isEmpty()) {
            $this->warn('No verified legal sources are available to check.');

            return self::SUCCESS;
        }

        $changed = 0;
        $unavailable = 0;
        foreach ($records as $source) {
            $check = $monitor->check($source);
            $this->line("{$source->id} {$source->title}: {$check->status}");
            $changed += (int) ($check->status === 'changed');
            $unavailable += (int) in_array($check->status, ['unavailable', 'unapproved'], true);
        }

        $this->info("Checked {$records->count()} source(s); {$changed} page change(s) need review; {$unavailable} check(s) unavailable or unapproved.");

        return self::SUCCESS;
    }
}
