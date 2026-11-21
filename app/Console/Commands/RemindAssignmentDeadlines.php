<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Models\User;
use App\Notifications\AcademyNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemindAssignmentDeadlines extends Command
{
    protected $signature = 'academy:remind-deadlines';

    protected $description = 'Notify active students about assignment deadlines within the next 24 hours';

    public function handle(): int
    {
        $count = 0;
        $assignments = Assignment::query()->whereBetween('deadline', [now(), now()->addDay()])
            ->whereHas('course', fn ($query) => $query->where('status', 'published'))
            ->whereDoesntHave('legalCase', fn ($query) => $query->where('status', '!=', 'published'))->with('course');
        foreach ($assignments->lazyById() as $assignment) {
            $ids = $assignment->course->enrollments()->where('status', 'active')->pluck('student_id');
            foreach (User::query()->whereIn('id', $ids)->cursor() as $student) {
                if ($assignment->submissions()->where('student_id', $student->id)->whereIn('status', ['submitted', 'resubmitted', 'approved', 'graded'])->exists()) {
                    continue;
                }
                $hash = hash('sha256', $assignment->id.':'.$student->id.':'.$assignment->deadline->timestamp);
                $id = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-'.substr($hash, 12, 4).'-'.substr($hash, 16, 4).'-'.substr($hash, 20, 12);
                $created = DB::table('notifications')->insertOrIgnore([
                    'id' => $id, 'type' => AcademyNotification::class, 'notifiable_type' => $student->getMorphClass(), 'notifiable_id' => $student->id,
                    'data' => json_encode(['title' => __('Topshiriq muddati yaqin: :title', ['title' => $assignment->title], $student->preferredLocale()), 'path' => '/learn/assignments'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $count += $created;
            }
        }
        $this->info($count.' ta eslatma yaratildi.');

        return self::SUCCESS;
    }
}
