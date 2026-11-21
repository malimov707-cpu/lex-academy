<?php

namespace App\Services\VirtualCourtroom;

use App\Models\SessionEvent;
use App\Models\SimulationSession;

class SessionEventRecorder
{
    /**
     * The caller must hold a database lock on the simulation session.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(
        SimulationSession $session,
        string $type,
        ?string $actorRoleKey = null,
        ?string $stageKey = null,
        array $payload = [],
    ): SessionEvent {
        $sequence = ((int) $session->events()->max('sequence')) + 1;

        return $session->events()->create([
            'sequence' => $sequence,
            'type' => $type,
            'actor_role_key' => $actorRoleKey,
            'stage_key' => $stageKey,
            'payload' => $payload === [] ? null : $payload,
            'occurred_at' => now(),
        ]);
    }
}
