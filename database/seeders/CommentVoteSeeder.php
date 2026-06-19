<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\CommentVote;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommentVoteSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            CommentVote::query()->delete();

            $users = User::all();
            $comments = Comment::with('author')->get();

            if ($users->isEmpty() || $comments->isEmpty()) {
                return;
            }

            foreach ($comments as $comment) {
                $authorId = $comment->author_id;
                $eligible = $users->filter(fn ($u) => $u->id !== $authorId)->values();

                if ($eligible->isEmpty()) {
                    continue;
                }

                $baseVotes = $comment->is_chosen_answer ? rand(6, 11) : ($comment->parent_id ? rand(0, 5) : rand(2, 8));
                $voteCount = min($eligible->count(), $baseVotes);

                $picked = $eligible->shuffle()->take($voteCount);

                $likeChance = $comment->is_chosen_answer ? 80 : ($comment->parent_id ? 55 : 70);

                foreach ($picked as $voter) {
                    CommentVote::create([
                        'comment_id' => $comment->id,
                        'user_id' => $voter->id,
                        'vote' => rand(1, 100) <= $likeChance ? 'like' : 'dislike',
                    ]);
                }
            }
        });
    }
}
