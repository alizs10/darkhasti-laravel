<?php

namespace App\Http\Controllers;

use App\ApiResponse;
use App\Models\Comment;
use App\Models\Request as RequestModel;
use App\Models\TempFile;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    use ApiResponse;

    protected FileUploadService $fileService;

    public function __construct(FileUploadService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * Get replies for a specific comment (with cursor pagination)
     */
    public function index(Request $request, Comment $comment)
    {
        $request->validate([
            'order' => ['nullable', Rule::in(['new', 'favorite', 'comment', 'old'])],
            'cursor' => ['nullable', 'string'],
        ]);

        $order = $request->query('order', 'new');

        $query = $comment->childs()
            ->with(['author', 'attachedFiles'])
            ->withCount(['likes', 'dislikes', 'replies']);

        switch ($order) {
            case 'favorite':
                $query->orderByDesc('likes_count')
                    ->orderByDesc('id');
                break;

            case 'comment':
                $query->orderByDesc('replies_count')
                    ->orderByDesc('id');
                break;
            case 'old':
                $query->orderBy('created_at', 'asc')->orderBy('id', 'asc');
                break;

            case 'new':
            default:
                $query->latest('id');
                break;
        }

        // $user = auth('api')->user();

        // if ($user) {
        //     $query->withUserVote($user->id);
        // }

        $total = $query->count();

        // Use cursor pagination (15 items per page by default)
        $replies = $query->cursorPaginate(10);

        return $this->cursorPaginatedResponse(
            $replies->items(),
            $replies,
            'Comments fetched successfully',
            $total
        );
    }

    public function getAll()
    {
        return $this->successResponse(
            Comment::all(),
            'All comments fetched successfully'
        );
    }

    /**
     * Get comments for a specific request (with cursor pagination + threading)
     */
    public function requestComments(Request $request, RequestModel $requestModel)
    {
        $request->validate([
            'order' => ['nullable', Rule::in(['new', 'favorite', 'comment', 'old'])],
            'cursor' => ['nullable', 'string'],
        ]);

        $order = $request->query('order', 'new');

        $query = $requestModel->comments()
            ->with(['author', 'attachedFiles'])
            ->withCount(['likes', 'dislikes', 'replies'])
            ->whereNull('parent_id');

        // dd($order);

        switch ($order) {
            case 'favorite':
                $query->orderByDesc('likes_count')
                    ->orderByDesc('id');
                break;

            case 'comment':
                $query->orderByDesc('replies_count')
                    ->orderByDesc('id');
                break;

            case 'old':
                $query->orderBy('created_at', 'asc')->orderBy('id', 'asc');
                break;

            case 'new':
            default:
                $query->latest('created_at');
                break;
        }

        $total = $query->count();

        // Use cursor pagination (15 items per page by default)
        $comments = $query->cursorPaginate(10);

        return $this->cursorPaginatedResponse(
            $comments->items(),
            $comments,
            'Comments fetched successfully',
            $total
        );
    }

    /**
     * Create new comment
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'request_id' => ['required', 'exists:requests,id'],
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'exists:comments,id'],
            'temp_files' => ['array', 'max:10'],
            'temp_files.*' => ['integer', 'exists:temp_files,id'],
        ]);

        $user = auth()->user();

        if (! $user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        // Check if parent belongs to the same request
        if (! empty($validated['parent_id'])) {
            $parent = Comment::findOrFail($validated['parent_id']);

            if ($parent->request_id !== (int) $validated['request_id']) {
                return $this->errorResponse('Invalid parent comment', 422);
            }
        }

        $comment = Comment::create([
            'author_id' => $user->id,
            'request_id' => $validated['request_id'],
            'body' => $validated['body'],
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        // Move temp files to permanent
        if (! empty($validated['temp_files'])) {
            $tempFiles = TempFile::whereIn('id', $validated['temp_files'])
                ->where('user_id', $user->id)
                ->get();

            foreach ($tempFiles as $temp) {
                /** @var TempFile $temp */
                $this->fileService->moveToPermanent($temp, $comment);
            }
        }

        $comment->load(['author', 'attachedFiles']);
        $comment->loadCount(['likes', 'dislikes', 'replies']);

        return $this->successResponse(
            $comment,
            'Comment created successfully',
            201
        );
    }

    /**
     * Get single comment
     */
    public function show(Comment $comment)
    {
        // $user = auth('api')->user();

        // Load relationships and counts
        $comment->load([
            'ancestors.attachedFiles',
            'request.author',
            'request.attachedFiles',
            'author',
            'attachedFiles',
        ]);

        return $this->successResponse(
            $comment,
            'Comment fetched successfully'
        );
    }

    /**
     * Get single my comment
     */
    public function showMy(Comment $comment)
    {
        $user = auth('api')->user();

        if (! $comment || ! $user || $comment->author_id != $user->id) {
            return $this->errorResponse('Comment Not Found!', 404);
        }

        $comment->load(['attachedFiles']);

        return $this->successResponse(
            $comment,
            'Comment fetched successfully'
        );
    }

    /**
     * Update comment
     */
    public function update(Request $request, Comment $comment)
    {
        if ($comment->author_id !== auth()->id()) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validated = $request->validate([
            'body' => ['sometimes', 'required', 'string', 'max:5000'],
            'deleted_main_files' => ['array'],
            'deleted_main_files.*' => ['integer', 'exists:attached_files,id'],
            'temp_files' => ['array', 'max:10'],
            'temp_files.*' => ['integer', 'exists:temp_files,id'],
        ]);

        if (array_key_exists('body', $validated)) {
            $comment->update([
                'body' => $validated['body'],
            ]);
        }

        // Delete selected main files
        if (! empty($validated['deleted_main_files'])) {
            $comment->attachedFiles()
                ->whereIn('id', $validated['deleted_main_files'])
                ->delete();
        }

        // Move new temp files
        if (! empty($validated['temp_files'])) {
            $tempFiles = TempFile::whereIn('id', $validated['temp_files'])
                ->where('user_id', auth()->id())
                ->get();

            foreach ($tempFiles as $temp) {
                /** @var TempFile $temp */
                $this->fileService->moveToPermanent($temp, $comment);
            }
        }

        $comment->load(['author', 'attachedFiles']);

        return $this->successResponse(
            [
                'comment' => $comment,
            ],
            'Comment updated successfully'
        );
    }

    /**
     * Delete comment
     */
    public function destroy(Comment $comment)
    {
        if ($comment->author_id !== auth()->id()) {
            return $this->errorResponse('Unauthorized', 403);
        }

        // Delete the comment and all its descendants (attached files and child)
        $comment->deleteWithDescendants();

        return $this->successResponse(
            null,
            'Comment deleted successfully'
        );
    }
}
