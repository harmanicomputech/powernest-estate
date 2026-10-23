<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Stores uploads on the private disk (never publicly addressable). Files are served only
 * through DocumentController after a policy check on the owning record.
 */
class DocumentStore
{
    public const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'application/pdf'];

    public function store(Model $owner, UploadedFile $file, User $uploader, string $category = 'attachment'): Document
    {
        $mime = $file->getMimeType();
        abort_unless(in_array($mime, self::ALLOWED_MIMES, true), 422, 'Unsupported file type.');

        $extension = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic', default => 'pdf',
        };

        $path = $file->storeAs('documents/'.$owner->estate_id.'/'.Str::kebab(class_basename($owner)), Str::uuid().'.'.$extension, 'local');

        return Document::create([
            'estate_id' => $owner->estate_id,
            'documentable_type' => $owner->getMorphClass(),
            'documentable_id' => $owner->getKey(),
            'uploaded_by' => $uploader->id,
            'category' => $category,
            'disk' => 'local',
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
            'mime_type' => $mime,
            'size' => $file->getSize(),
        ]);
    }
}
