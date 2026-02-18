<?php

namespace App\Events;

use App\Models\AuraNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AuraNotification $notification) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('notifications.' . $this->notification->user_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id'         => $this->notification->id,
            'type'       => $this->notification->type,
            'title'      => $this->notification->title,
            'message'    => $this->notification->message,
            'action_url' => $this->notification->action_url,
            'created_at' => $this->notification->created_at->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'NotificationCreated';
    }
}
