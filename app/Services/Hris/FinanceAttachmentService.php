<?php

namespace App\Services\Hris;

use App\Models\Hris\FinanceAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores, hashes, dedup-flags, locks and deletes finance attachments on the private disk.
 * No public URLs are ever produced; downloads are authorized at the controller/policy layer.
 */
class FinanceAttachmentService
{
    private const DISK = 'private';

    public function store(Model $attachable, UploadedFile $file, string $role, User $uploader): FinanceAttachment
    {
        $hash = hash_file('sha256', $file->getRealPath());
        $size = $file->getSize();
        $mime = $file->getClientMimeType();
        $originalName = $file->getClientOriginalName();

        $duplicateIds = FinanceAttachment::where('file_hash', $hash)->pluck('id')->all();

        $path = $file->store("finance-attachments/{$role}", self::DISK);

        $metadata = [];
        if (! empty($duplicateIds)) {
            $metadata['duplicate_warning'] = true;
            $metadata['duplicate_of'] = array_values($duplicateIds);
        }

        return FinanceAttachment::create([
            'attachable_type' => get_class($attachable),
            'attachable_id' => $attachable->id,
            'attachment_role' => $role,
            'file_path' => $path,
            'file_hash' => $hash,
            'file_size' => $size,
            'mime_type' => $mime,
            'original_filename' => $originalName,
            'uploaded_by' => $uploader->id,
            'uploaded_at' => now(),
            'metadata' => $metadata ?: null,
        ]);
    }

    /**
     * Lock an attachment (it has become part of a finance verification or COO approval snapshot).
     * Locked files are immutable — replacing one requires re-review/re-approval.
     */
    public function lock(FinanceAttachment $attachment, string $reason): void
    {
        $attachment->update([
            'locked_at' => now(),
            'locked_reason' => $reason,
        ]);
    }

    /**
     * Delete an attachment + its file. Locked attachments cannot be silently replaced/removed.
     *
     * @throws \RuntimeException when the attachment is locked.
     */
    public function delete(FinanceAttachment $attachment): void
    {
        if ($attachment->isLocked()) {
            throw new \RuntimeException('Locked finance attachment cannot be deleted or replaced.');
        }

        Storage::disk(self::DISK)->delete($attachment->file_path);
        $attachment->delete();
    }
}
