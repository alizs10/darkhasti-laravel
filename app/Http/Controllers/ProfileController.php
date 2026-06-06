<?php

namespace App\Http\Controllers;

use App\ApiResponse;
use App\Models\TempFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use ApiResponse;

    public function stats()
    {
        $user = auth()->user();

        if (! $user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $stats = [
            'perm_files_count' => $user->attachedFiles()->count(),
            'temp_files_count' => $user->tempFiles()->count(),
            'requests_count' => $user->requests()->count(),
            'comments_count' => $user->comments()->where('deleted_at', null)->count(),
            'chosen_comments_count' => $user->comments()->where('is_chosen_answer', true)->count(),
        ];

        return $this->successResponse(
            [
                'stats' => $stats,
            ],
            'Here is your stats'
        );
    }

    public function changeUsername(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:99',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
        ]);

        $user->update([
            'username' => $validated['username'],
        ]);

        return $this->successResponse(
            $user->fresh(),
            'Username updated successfully'
        );
    }

    public function deleteAccount(Request $request)
    {
        $validated = $request->validate([
            'password' => 'required|string',
        ]);
        $user = auth()->user();

        // check if password is correct
        if (! Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse('Password is incorrect', 422);
        }

        DB::transaction(function () use ($user) {

            // Delete user's requests (+ comments + attached files)
            $user->requests()->with(['comments', 'attachedFiles'])->get()
                ->each(fn ($request) => $request->deleteWithCommentsAndFiles());

            // Delete comments authored by the user on other requests
            $user->comments()->with(['childs', 'attachedFiles'])->get()
                ->each(fn ($comment) => $comment->deleteWithDescendants());

            // Delete permanent files uploaded by the user
            $user->attachedFiles()->delete();

            // Delete temp files + physical temp files
            TempFile::where('user_id', $user->id)
                ->get()
                ->each(function ($file) {
                    if (Storage::disk('temp')->exists($file->file_path)) {
                        Storage::disk('temp')->delete($file->file_path);
                    }

                    $file->delete();
                });

            // Cleanup auxiliary data
            $user->requestVotes()->delete();
            $user->commentVotes()->delete();
            $user->requestVisits()->delete();

            // Soft delete user
            $user->delete();
        });

        return $this->successResponse(
            null,
            'account deleted successfully'
        );
    }
}
