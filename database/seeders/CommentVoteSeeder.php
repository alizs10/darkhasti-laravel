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

                // votes per comment: 0..8 (tweak)
                $votersCount = rand(0, 8);
                if ($votersCount === 0) {
                    continue;
                }

                // Eligible voters (usually exclude author)
                $eligible = $users->filter(fn ($u) => $u->id !== $authorId)->values();

                if ($eligible->isEmpty()) {
                    continue;
                }

                // More “realistic” distribution:
                // chosen answers tend to receive more likes
                $baseLikeChance = $comment->is_chosen_answer ? 75 : 45;

                $picked = $eligible->random(min($votersCount, $eligible->count()));

                // $picked can be model or collection depending on Laravel version; normalize:
                $picked = is_iterable($picked) ? $picked : collect([$picked]);

                foreach ($picked as $voter) {
                    // Unique constraint is per (comment_id, user_id) so we can just create once
                    CommentVote::create([
                        'comment_id' => $comment->id,
                        'user_id' => $voter->id,
                        'vote' => (rand(1, 100) <= $baseLikeChance) ? 'like' : 'dislike',
                    ]);
                }
            }
        });
    }
}
