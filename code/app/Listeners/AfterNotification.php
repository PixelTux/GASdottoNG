<?php

namespace App\Listeners;

use Illuminate\Notifications\Events\NotificationSent;

class AfterNotification
{
    public function handle(NotificationSent $event)
    {
        if (hasTrait($event->notification, 'App\Notifications\TemporaryFiles')) {
            $files = $event->notification->getFiles();
            foreach ($files as $f) {
                @unlink($f);
            }
        }
    }
}
