<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Request;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CommentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            Comment::query()->delete();

            $users = User::all();
            $requests = Request::with('author')->get();

            if ($users->isEmpty() || $requests->isEmpty()) {
                return;
            }

            $commentPoolPerRequest = [];

            foreach ($requests as $requestIndex => $request) {
                // 2..8 comments per request
                $count = rand(2, 8);

                $createdComments = collect();

                for ($i = 0; $i < $count; $i++) {
                    $author = $users->random();

                    // ~25% chance this comment is a reply (threaded), if we already have a parent
                    $isReply = $createdComments->count() > 0 && rand(1, 100) <= 25;

                    $parentId = null;

                    if ($isReply) {
                        $parent = $createdComments->random();
                        $parentId = $parent->id;
                    }

                    $createdComments->push(
                        Comment::create([
                            'author_id' => $author->id,
                            'request_id' => $request->id,
                            'parent_id' => $parentId,
                            'body' => $this->makeBody($request->id, $author->username, $i),
                            'is_chosen_answer' => false, // set later
                            'created_at' => Carbon::now()
                                ->subDays(rand(0, 30))
                                ->subHours(rand(0, 23))
                                ->subMinutes(rand(0, 59)),
                            'updated_at' => Carbon::now(),
                        ])
                    );
                }

                // Choose an answer sometimes (e.g., 40% of requests)
                if (rand(1, 100) <= 40 && $createdComments->isNotEmpty()) {
                    // pick a comment from this request (not necessarily top-level)
                    $chosen = $createdComments->random();

                    Comment::where('id', $chosen->id)->update([
                        'is_chosen_answer' => true,
                        'updated_at' => Carbon::now(),
                    ]);
                }

                $commentPoolPerRequest[$request->id] = $createdComments;
            }
        });
    }

    private function makeBody(int $requestId, string $username, int $i): string
    {
        $templates = [
            'Great point on request #'.$requestId.' — thanks!',
            'I agree with @'.$username.'',
            'Could you clarify the details for #'.$requestId.'?',
            'Interesting perspective. Here is my take...',
            'I think this depends on timing and requirements.',
            'Solid explanation. Looking forward to updates.',
        ];

        return $templates[$i % count($templates)].' (comment '.($i + 1).')';
    }
}
