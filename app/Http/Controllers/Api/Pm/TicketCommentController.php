<?php

namespace App\Http\Controllers\Api\Pm;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketCommentController extends Controller
{
    /**
     * List comments for a ticket.
     *
     * GET /api/pm/tickets/{ticket}/comments
     */
    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        $project = $ticket->project;
        abort_if(! $project, 404, 'Ticket has no associated project.');
        $this->authorizeProjectAccess($request->user(), $project);

        $comments = $ticket->comments()
            ->with('user:id,name,email,avatar_url')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (TicketComment $comment) => $this->formatComment($comment));

        return response()->json([
            'data' => $comments,
        ]);
    }

    /**
     * Add a comment to a ticket.
     *
     * POST /api/pm/tickets/{ticket}/comments
     *
     * TicketComment::booted() triggers NotificationService::notifyCommentAdded().
     */
    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $project = $ticket->project;
        abort_if(! $project, 404, 'Ticket has no associated project.');
        $this->authorizeProjectAccess($request->user(), $project);

        $data = $request->validate([
            'comment' => ['required', 'string'],
        ]);

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'comment' => $data['comment'],
        ]);

        $comment->load('user:id,name,email,avatar_url');

        return response()->json([
            'message' => 'Comment added successfully.',
            'data' => $this->formatComment($comment),
        ], 201);
    }

    /**
     * Update own comment.
     *
     * PUT /api/pm/comments/{comment}
     *
     * TicketComment::booted() triggers NotificationService::notifyCommentUpdated().
     */
    public function update(Request $request, TicketComment $comment): JsonResponse
    {
        $this->authorizeCommentOwner($request->user(), $comment);

        $project = $comment->ticket?->project;
        abort_if(! $project, 404, 'Comment has no associated project.');
        $this->authorizeProjectAccess($request->user(), $project);

        $data = $request->validate([
            'comment' => ['required', 'string'],
        ]);

        $comment->update([
            'comment' => $data['comment'],
        ]);

        $comment->load('user:id,name,email,avatar_url');

        return response()->json([
            'message' => 'Comment updated successfully.',
            'data' => $this->formatComment($comment),
        ]);
    }

    /**
     * Delete own comment.
     *
     * DELETE /api/pm/comments/{comment}
     */
    public function destroy(Request $request, TicketComment $comment): JsonResponse
    {
        $this->authorizeCommentOwner($request->user(), $comment);

        $project = $comment->ticket?->project;
        abort_if(! $project, 404, 'Comment has no associated project.');
        $this->authorizeProjectAccess($request->user(), $project);

        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully.',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function authorizeProjectAccess($user, $project): void
    {
        if ($user->hasRole('super_admin')) {
            return;
        }

        abort_if(
            ! $project->members()->where('users.id', $user->id)->exists(),
            403,
            'You are not a member of this project.'
        );
    }

    protected function authorizeCommentOwner($user, TicketComment $comment): void
    {
        // super_admin can edit/delete any comment
        if ($user->hasRole('super_admin')) {
            return;
        }

        abort_if(
            $comment->user_id !== $user->id,
            403,
            'You can only modify your own comments.'
        );
    }

    protected function formatComment(TicketComment $comment): array
    {
        return [
            'id' => $comment->id,
            'ticket_id' => $comment->ticket_id,
            'comment' => $comment->comment,
            'user' => $comment->user ? [
                'id' => $comment->user->id,
                'name' => $comment->user->name,
                'email' => $comment->user->email,
                'avatar_url' => $comment->user->avatar_url,
            ] : null,
            'created_at' => $comment->created_at?->toIso8601String(),
            'updated_at' => $comment->updated_at?->toIso8601String(),
        ];
    }
}
