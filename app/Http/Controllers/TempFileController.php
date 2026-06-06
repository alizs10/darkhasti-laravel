<?php

namespace App\Http\Controllers;

use App\ApiResponse;
use App\Models\Comment;
use App\Models\TempFile;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TempFileController extends Controller
{
    use ApiResponse;

    protected $fileService;

    public function __construct(FileUploadService $fileService)
    {
        $this->fileService = $fileService;
    }

    public function upload(Request $request)
    {
        $validated = $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['required', 'file', 'max:5120'],
            'attachable_id' => ['nullable', 'integer'],
            'attachable_type' => [
                'required',
                'string',
                'in:App\Models\Request,App\Models\Comment', 'required_with:attachable_id',
            ],
        ]);

        $user = auth()->user();

        $attachable = null;

        if (
            ! empty($validated['attachable_id']) &&
            ! empty($validated['attachable_type'])
        ) {
            $attachableClass = $validated['attachable_type'];

            $attachable = $attachableClass::query()
                ->where('id', $validated['attachable_id'])
                ->where('author_id', $user->id)
                ->first();

            if (! $attachable) {
                return $this->errorResponse(
                    'Invalid attachable resource',
                    403
                );
            }
        }

        $attachable_id = $validated['attachable_id'] ?? null;

        $tempFiles = collect($validated['files'])
            ->map(fn ($file) => $this->fileService->uploadToTemp(
                $file,
                $user->id,
                $attachable ? $attachable->getMorphClass() : $validated['attachable_type'],
                $attachable ? $attachable->getKey() : $attachable_id,
            ));

        return $this->successResponse(
            $tempFiles,
            'Files uploaded to temporary storage',
            201
        );
    }

    // Get user's unattached temp files (for page refresh)
    public function myTempFiles(Request $request)
    {
        $request->validate([
            'attachable_id' => ['nullable', 'integer'],
            'attachable_type' => [
                'nullable',
                'string',
                Rule::in([
                    \App\Models\Request::class,
                    Comment::class,
                ]),
            ],
        ]);

        $user = $request->user();

        $filledBoth = $request->filled(['attachable_id', 'attachable_type']);
        $filledOnlyAttachableType = $request->filled('attachable_type') && empty($request->attachable_id);

        // dd($filledBoth, $filledOnlyAttachableType);

        $files = TempFile::query()
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->when(
                $filledBoth,
                fn ($query) => $query->where(
                    'attachable_id',
                    $request->attachable_id
                )->where('attachable_type', $request->attachable_type)
            )
            ->when(
                $filledOnlyAttachableType,
                fn ($query) => $query->where(
                    'attachable_type',
                    $request->attachable_type
                )->where(
                    'attachable_id',
                    null
                )
            )
            ->latest()
            ->get();

        return $this->successResponse(
            $files,
            'Temp files fetched successfully'
        );
    }

    // Get temp files for specific attachable (used during update)
    public function forAttachable(Request $request)
    {
        $validated = $request->validate([
            'attachable_id' => ['required', 'integer'],
            'attachable_type' => [
                'required',
                'string',
                'in:App\Models\Request,App\Models\Comment',
            ],
        ]);

        $user = auth()->user();

        if (! $user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $files = TempFile::where('user_id', $user->id)
            ->where('attachable_id', $validated['attachable_id'])
            ->where('attachable_type', $validated['attachable_type'])
            ->get();

        return $this->successResponse(
            $files,
            'Temp files fetched successfully'
        );
    }

    public function delete(TempFile $tempFile)
    {
        if ($tempFile->user_id !== auth()->id()) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $this->fileService->deleteTempFile($tempFile);

        return $this->successResponse(
            null,
            'Temp file deleted successfully'
        );
    }
}
