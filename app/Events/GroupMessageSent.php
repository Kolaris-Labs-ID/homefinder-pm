<?php

namespace App\Events;

use App\Models\GroupMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public GroupMessage $message;
    public array $senderInfo;
    public int $groupId;

    /**
     * Create a new event instance.
     */
    public function __construct(GroupMessage $message)
    {
        $this->message = $message;
        $this->groupId = $message->group_id;

        // Include sender info for display
        $sender = $message->sender;
        $this->senderInfo = [
            'id' => $sender->id,
            'name' => $sender->name,
            'avatar' => $sender->avatar_url ?? null,
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('group-chat.' . $this->groupId),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'group_id' => $this->message->group_id,
                'user_id' => $this->message->user_id,
                'body' => $this->message->body,
                'attachment' => $this->message->attachment,
                'created_at' => $this->message->created_at->toISOString(),
            ],
            'sender' => $this->senderInfo,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
