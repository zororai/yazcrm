<?php

namespace App\Notifications;

use App\Models\DataCollectionExport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Shows in the bell when a queued data collection export has finished.
class DataCollectionExportReady extends Notification
{
    use Queueable;

    public function __construct(private readonly DataCollectionExport $export)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'      => 'data_collection_export_ready',
            'export_id' => $this->export->id,
            'message'   => "Your ".strtoupper($this->export->format)." export of \"{$this->export->form->name}\" is ready to download ("
                .number_format((int) $this->export->row_count).' submissions).',
            'url'       => "/data-collection/exports/{$this->export->id}/download",
        ];
    }
}
