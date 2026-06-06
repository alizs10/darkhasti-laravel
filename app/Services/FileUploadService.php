<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Request;
use App\Models\TempFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    protected string $tempDisk = 'temp';   // or 'public' if you prefer one disk

    protected string $mainDisk = 'public';

    protected string $tempFolder = 'uploads';

    protected string $mainFolder = 'uploads';

    public function uploadToTemp(
        UploadedFile $file,
        int $userId,
        ?string $attachable_type = null,
        ?int $attachable_id = null
    ): TempFile {
        $hash = hash(
            'sha256',
            implode('|', [
                hash_file('sha256', $file->getPathname()),
                $userId,
                $attachable_type,
                $attachable_id,
            ])
        );

        $existing = TempFile::query()
            ->where('file_hash', $hash)
            ->where('user_id', $userId)
            ->where('attachable_type', $attachable_type)
            ->where('attachable_id', $attachable_id)
            ->first();

        if (
            $existing &&
            Storage::disk($this->tempDisk)->exists($existing->file_path)
        ) {
            return $existing;
        }


        $fileName = Str::uuid().'.'.$file->getClientOriginalExtension();

        $path = $file->storeAs(
            $this->tempFolder,
            $fileName,
            $this->tempDisk
        );

        return TempFile::create([
            'user_id' => $userId,
            'attachable_id' => $attachable_id,
            'attachable_type' => $attachable_type,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'file_hash' => $hash,
            'file_path' => $path,
            'expires_at' => now()->addHour(),
        ]);
    }

    public function moveToPermanent(
        TempFile $tempFile,
        Comment|Request $attachable
    ): bool {
        if (! Storage::disk($this->tempDisk)->exists($tempFile->file_path)) {
            return false;
        }

        $stream = Storage::disk($this->tempDisk)
            ->readStream($tempFile->file_path);

        if (! $stream) {
            return false;
        }

        $copied = Storage::disk($this->mainDisk)
            ->writeStream($tempFile->file_path, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        if (! $copied) {
            return false;
        }

        Storage::disk($this->tempDisk)
            ->delete($tempFile->file_path);

        $attachable->attachedFiles()->create([
            'file_name' => $tempFile->file_name,
            'file_size' => $tempFile->file_size,
            'mime_type' => $tempFile->mime_type,
            'user_id' => $tempFile->user_id,
            'file_hash' => $tempFile->file_hash,
            'file_path' => $tempFile->file_path,
        ]);

        $tempFile->delete();

        return true;
    }

    public function deleteTempFile(TempFile $tempFile): bool
    {
        Storage::disk($this->tempDisk)->delete($tempFile->file_path);

        return $tempFile->delete();
    }

    public function cleanExpiredTempFiles(): int
    {
        $expired = TempFile::expired()->get();
        $count = 0;

        foreach ($expired as $file) {
            /** @var TempFile $file */
            $this->deleteTempFile($file);
            $count++;
        }

        return $count;
    }
}
