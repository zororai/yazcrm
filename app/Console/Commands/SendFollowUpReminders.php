<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Notifications\FollowUpDueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendFollowUpReminders extends Command
{
    protected $signature   = 'tickets:follow-up-reminders';
    protected $description = "Remind agents of open tickets whose follow-up date is today (once per ticket per date)";

    public function handle(): int
    {
        $sent = 0;

        Ticket::with('agent')
            ->whereDate('follow_up_date', today())
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNotNull('agent_id')
            ->chunkById(200, function ($tickets) use (&$sent) {
                foreach ($tickets as $ticket) {
                    if (! $ticket->agent) continue;

                    $notification = new FollowUpDueNotification($ticket);
                    $already = DB::table('notifications')
                        ->where('notifiable_type', $ticket->agent->getMorphClass())
                        ->where('notifiable_id', $ticket->agent->id)
                        ->where('type', FollowUpDueNotification::class)
                        ->where('data', 'like', '%"ticket_id":'.$ticket->id.',"follow_up_date":"'.$notification->dueDate().'"%')
                        ->exists();
                    if ($already) continue;

                    $ticket->agent->notify($notification);
                    $sent++;
                }
            });

        $this->info("Sent {$sent} follow-up reminder(s).");

        return self::SUCCESS;
    }
}
