<?php

namespace App\Http\Controllers;

use App\ApiResponse;
use App\Models\Comment;
use App\Models\Request as RequestModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoteController extends Controller
{
    use ApiResponse;

    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:request,comment',
            'id' => 'required|integer',
            'vote' => 'required|in:like,dislike',
        ]);

        $user = auth()->user();

        if (! $user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if ($validated['type'] === 'request') {
            $model = RequestModel::findOrFail($validated['id']);
            $table = 'requests_votes';
            $foreignKey = 'request_id';
        } else {
            $model = Comment::findOrFail($validated['id']);
            $table = 'comments_votes';
            $foreignKey = 'comment_id';
        }

        $existing = DB::table($table)
            ->where('user_id', $user->id)
            ->where($foreignKey, $model->id)
            ->first();

        $current_vote = $validated['vote'];

        $vote_label = $validated['vote'] === 'like' ? 'لایک' : 'دیس لایک';
        $message = null;

        if ($existing) {
            if ($existing->vote === $validated['vote']) {
                DB::table($table)->where('id', $existing->id)->delete();
                $message = "{$vote_label} شما حذف شد.";
                $current_vote = null;
            } else {
                DB::table($table)
                    ->where('id', $existing->id)
                    ->update([
                        'vote' => $validated['vote'],
                        'updated_at' => now(),
                    ]);
                $message = "{$vote_label} شما ثبت شد.";
                // $current_vote = $validated['vote'];
            }
        } else {
            DB::table($table)->insert([
                'user_id' => $user->id,
                $foreignKey => $model->id,
                'vote' => $validated['vote'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $message = "{$vote_label} شما ثبت شد.";
            // $current_vote = $validated['vote'];
        }

        // If likes/dislikes are computed via accessors or appended attributes,
        // re-load or refresh the model if needed.
        $model->refresh();

        return $this->successResponse(
            [
                'likes' => $model->likes_count ?? 0,
                'dislikes' => $model->dislikes_count ?? 0,
                'current_vote' => $current_vote,
                'message' => $message,
            ],
            $message
        );
    }
}
