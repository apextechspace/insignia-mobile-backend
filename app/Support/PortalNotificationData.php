<?php

namespace App\Support;

class PortalNotificationData
{
    public static function format(array $data): array
    {
        $display = \Filament\Notifications\Notification::make()
            ->title($data['title'])->body(e($data['message']))
            ->icon('heroicon-o-bell-alert')->getDatabaseMessage();
        // Retain the existing business status consumed by the mobile API.
        unset($display['status']);
        return array_merge($data, $display);
    }
}
