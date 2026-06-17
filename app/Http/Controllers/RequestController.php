<?php

namespace App\Http\Controllers;

use App\ApiResponse;
use App\Models\Comment;
use App\Models\Request as RequestModel;
use App\Models\RequestVisit;
use App\Models\TempFile;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RequestController extends Controller
{
    use ApiResponse;

    protected $fileService;

    public function __construct(FileUploadService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * Get paginated list of requests
     */
    public function index(Request $request)
    {
        $request->validate([
            'order' => ['nullable', Rule::in(['visit', 'new', 'favorite', 'comment', 'old'])],
            'cursor' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],

            // 1. Add custom validation for per_page
            'per_page' => ['nullable', function ($attribute, $value, $fail) {
                // Allow 'all', or a number between 1 and 100
                if ($value !== 'all' && (! is_numeric($value) || $value < 1 || $value > 100)) {
                    $fail('The :attribute must be a number between 1 and 100 or "all".');
                }
            }],
        ]);

        $order = $request->query('order', 'visit');
        $search = $request->query('search');
        $perPage = $request->query('per_page', 30); // Default to 30

        $query = RequestModel::whereNotNull('published_at')
            ->with(['author', 'attachedFiles'])
            ->withCount(['visits', 'likes', 'replies']);

        // Apply search filter if search term is provided
        if ($search) {
            $query->whereFullText(['title', 'description'], $search);
        }

        switch ($order) {
            case 'new':
                $query->orderByDesc('published_at')->orderByDesc('id');
                break;
            case 'old':
                $query->orderBy('published_at', 'asc')->orderBy('id', 'asc');
                break;
            case 'favorite':
                $query->orderByDesc('likes_count')->orderByDesc('id');
                break;
            case 'comment':
                $query->orderByDesc('replies_count')->orderByDesc('id');
                break;
            case 'visit':
            default:
                $query->orderByDesc('visits_count')->orderByDesc('id');
                break;
        }

        // 2. Handle the "all" condition
        if ($perPage === 'all') {
            $requests = $query->get();

            // Return a standard JSON response (Adjust this to match your API's standard success format)
            return $this->successResponse(
                $requests,
                'All requests fetched successfully',
                200,
                [
                    'total' => $requests->count(),
                ]
            );
        }

        // 3. Handle standard numeric pagination
        $total = $query->count();
        $requests = $query->cursorPaginate((int) $perPage);

        return $this->cursorPaginatedResponse(
            $requests->items(),
            $requests,
            'Requests fetched successfully',
            $total
        );
    }

    public function getAll()
    {
        $requests = RequestModel::whereNotNull('published_at')->get();

        return $this->successResponse(
            $requests,
            'All Requests fetched successfully'
        );
    }

    /**
     * Get My paginated list of requests
     */
    public function myRequests(Request $request)
    {
        $request->validate([
            'order' => ['nullable', Rule::in(['visit', 'new', 'favorite', 'comment', 'old'])],
            'cursor' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $order = $request->query('order', 'visit');
        $search = $request->query('search');

        $user = auth('api')->user();

        $query = RequestModel::where('author_id', $user->id)->with(['author', 'attachedFiles'])
            ->withCount(['visits', 'likes', 'replies']);

        // Apply search filter if search term is provided
        if ($search) {
            $query->whereFullText(['title', 'description'], $search);
        }

        switch ($order) {
            case 'new':
                // If "new" means most recently published:
                $query->orderByDesc('created_at')->orderByDesc('id');
                break;
            case 'old':
                $query->orderBy('created_at', 'asc')->orderBy('id', 'asc');
                break;

            case 'favorite':
                $query->orderByDesc('likes_count')->orderByDesc('id');
                break;

            case 'comment':
                $query->orderByDesc('replies_count')->orderByDesc('id');
                break;

            case 'visit':
            default:
                $query->orderByDesc('visits_count')->orderByDesc('id');
                break;
        }

        $total = $query->count();
        $requests = $query->cursorPaginate(30);

        return $this->cursorPaginatedResponse(
            $requests->items(),
            $requests,
            'Requests fetched successfully',
            $total
        );
    }

    /**
     * Get related paginated list of requests
     */
    public function related(RequestModel $request)
    {
        $currentRequest = RequestModel::findOrFail($request->id);

        // Extract keywords from title (remove common words, split)
        $words = explode(' ', $request->title);
        $keywords = implode(' ', array_slice($words, 0, 5));

        $requests = RequestModel::whereNotNull('published_at')->with(['author', 'attachedFiles'])
            ->withCount(['visits', 'likes', 'replies'])
            ->where('id', '!=', $currentRequest->id)
            ->whereFullText(['title', 'description'], $keywords)
            ->orderByDesc('visits_count')
            ->orderByDesc('id')
            ->limit(9)  // Just get 10 items
            ->get();     // Use get() not paginate()

        return $this->successResponse(
            $requests,
            'Related requests fetched successfully'
        );
    }

    /**
     * Create a new request
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'temp_files' => 'array|max:10',
            'temp_files.*' => 'integer|exists:temp_files,id',
            'save_as_draft' => 'nullable|boolean',
        ]);

        $user = auth()->user();
        if (! $user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $drafted = $validated['save_as_draft'] ?? false;

        $req = RequestModel::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'author_id' => $user->id,
            'published_at' => $drafted ? null : now(),
        ]);

        // Move temp files to permanent
        if (isset($validated['temp_files']) && ! empty($validated['temp_files'])) {
            $tempFiles = TempFile::whereIn('id', $validated['temp_files'])
                ->where('user_id', $user->id)
                ->get();

            foreach ($tempFiles as $temp) {
                $this->fileService->moveToPermanent($temp, $req);
            }
        }

        $req->load(['author', 'attachedFiles']);

        return $this->successResponse(
            [
                'request' => $req,
            ],
            'Request created successfully',
            201
        );
    }

    /**
     * Get a single request
     */
    public function show(RequestModel $request)
    {

        $request->load(['author', 'attachedFiles', 'chosenAnswer', 'chosenAnswer.author', 'chosenAnswer.attachedFiles']);
        $request->loadCount(['replies']);

        $user_vote = null;
        $user = auth('api')->user();

        if ($user) {
            $user_vote = $user->requestVotes()->where('request_id', $request->id)->first();

            $request->setAttribute('user_vote_status', $user_vote ? $user_vote->vote : null);
        }

        if ($request->chosenAnswer) {
            $request->chosenAnswer->loadCount(['replies', 'likes', 'dislikes']);
        }

        RequestVisit::create([
            'request_id' => $request->id,
            'user_id' => auth()->check() ? auth()->id() : null,
            'ip_address' => request()->ip(),
        ]);

        return $this->successResponse(
            $request,
            'Request fetched successfully'
        );
    }

    /**
     * Get single my request
     */
    public function showMy(RequestModel $request)
    {
        $user = auth('api')->user();

        if (! $request || ! $user || $request->author_id != $user->id) {
            return $this->errorResponse('Request Not Found!', 404);
        }

        $request->load(['attachedFiles']);

        return $this->successResponse(
            $request,
            'Request fetched successfully'
        );
    }

    /**
     * Update a request
     */
    public function update(Request $request, RequestModel $requestModel)
    {
        if ($requestModel->author_id !== auth()->id()) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'deleted_main_files' => 'array',
            'deleted_main_files.*' => 'integer|exists:attached_files,id',
            'temp_files' => 'array|max:10',
            'temp_files.*' => 'integer|exists:temp_files,id',
            'save_as_draft' => 'nullable|boolean',
        ]);

        $updateData = $request->only(['title', 'description']);

        if ($request->has('save_as_draft')) {
            $updateData['published_at'] = $request->boolean('save_as_draft')
                ? null
                : now();
        }

        if (! empty($updateData)) {
            $requestModel->update($updateData);
        }

        // Delete requested main files
        if (isset($validated['deleted_main_files']) && ! empty($validated['deleted_main_files'])) {
            $requestModel->attachedFiles()
                ->whereIn('id', $validated['deleted_main_files'])
                ->delete();
        }

        // Move new temp files
        if (isset($validated['temp_files']) && ! empty($validated['temp_files'])) {
            $tempFiles = TempFile::whereIn('id', $validated['temp_files'])
                ->where('user_id', auth()->id())
                ->get();

            foreach ($tempFiles as $temp) {
                $this->fileService->moveToPermanent($temp, $requestModel);
            }
        }

        $requestModel->load(['author', 'attachedFiles']);

        return $this->successResponse(
            [
                'request' => $requestModel,
            ],
            'Request updated successfully'
        );
    }

    /**
     * Delete a request
     */
    public function destroy(RequestModel $request)
    {
        if ($request->author_id !== auth()->id()) {
            return $this->errorResponse('Unauthorized', 403);
        }

        // Delete the request, all its comments (replies & files), and attached files
        $request->deleteWithCommentsAndFiles();

        return $this->successResponse(
            null, // No data to return on successful deletion
            'Request deleted successfully'
        );
    }

    public function toggleAnswer(Request $httpRequest)
    {
        $validated = $httpRequest->validate([
            'comment_id' => ['required', 'integer', 'exists:comments,id'],
        ]);

        // Load comment with its parent request
        $comment = Comment::with('request')->find($validated['comment_id']);

        if (! $comment || ! $comment->request) {
            return $this->errorResponse('Comment or associated request not found', 404);
        }

        $supportRequest = $comment->request;

        // Authorize: only the request author can toggle the chosen answer
        if ($supportRequest->author_id !== auth()->id()) {
            return $this->errorResponse('Unauthorized', 403);
        }

        // Use a transaction to prevent inconsistencies
        return DB::transaction(function () use ($comment, $supportRequest) {
            // Find the currently chosen answer for this request (if any)
            $currentChosen = Comment::where('request_id', $supportRequest->id)
                ->where('is_chosen_answer', true)
                ->first();

            // Case 1: The same comment is already the chosen answer → remove it
            if ($currentChosen && $currentChosen->id === $comment->id) {
                $comment->update(['is_chosen_answer' => false]);
                $message = 'Comment is not the chosen answer anymore';
            } else {
                // Case 2: Different comment (or none) → set this comment as chosen
                // First, unset the flag on any previously chosen comment
                if ($currentChosen) {
                    $currentChosen->update(['is_chosen_answer' => false]);
                }
                // Then set the new chosen answer
                $comment->update(['is_chosen_answer' => true]);
                $message = 'Comment chosen as answer successfully';
            }

            $comment->load(['author', 'attachedFiles']);
            $comment->loadCount(['likes', 'dislikes', 'replies']);
            $comment->fresh();

            return $this->successResponse($comment, $message);
        });
    }
}
