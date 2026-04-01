    public static function logTicketCommentCreated(TicketComment $comment, ?User $user = null): self
    {
        return static::recordForSubject($comment->ticket, 'ticket_comment_created', $user, [
            'name' => $comment->ticket->name,
            'uuid' => $comment->ticket->uuid,
            'comment_id' => $comment->id,
            'comment_preview' => mb_substr($comment->comment, 0, 50) . (mb_strlen($comment->comment) > 50 ? '...' : ''),
        ]);
    }
