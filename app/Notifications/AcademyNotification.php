<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class AcademyNotification extends Notification
{
    /** @param array<string, string> $parameters */
    public function __construct(public string $title, public string $path, public array $parameters = []) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{title: string, path: string} */
    public function toArray(object $notifiable): array
    {
        $parameters = $this->parameters;
        if (isset($parameters['decision'])) {
            $parameters['decision'] = __($parameters['decision']);
        }

        return ['title' => __($this->title, $parameters), 'path' => $this->path];
    }
}
