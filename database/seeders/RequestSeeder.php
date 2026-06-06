<?php

namespace Database\Seeders;

use App\Models\Request;
use App\Models\RequestVisit;
use App\Models\RequestVote;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RequestSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // Clean up in FK-safe order
            RequestVote::query()->delete();
            RequestVisit::query()->delete();
            Request::query()->delete();

            $users = User::all();

            if ($users->isEmpty()) {
                $this->command?->warn('No users found. Run UserSeeder first.');

                return;
            }

            // Create 5 requests per user
            $requests = collect();

            foreach ($users as $userIndex => $user) {
                for ($i = 1; $i <= 5; $i++) {
                    $publishedAt = Carbon::now()
                        ->subDays(($userIndex * 3) + rand(0, 10))
                        ->subHours(rand(0, 23))
                        ->subMinutes(rand(0, 59));

                    $requests->push(
                        Request::create([
                            'title' => "Request {$user->username} #{$i}",
                            'description' => 'Lorem ipsum description for '.$user->username,
                            'author_id' => $user->id,
                            'published_at' => $publishedAt,
                        ])
                    );
                }
            }

            // Fake visits
            foreach ($requests as $requestIndex => $request) {
                // Older requests tend to have more visits
                $baseVisits = $requestIndex < 10 ? rand(12, 20) : ($requestIndex < 30 ? rand(6, 14) : rand(2, 8));

                for ($i = 0; $i < $baseVisits; $i++) {
                    $visitor = (rand(1, 100) <= 75)
                        ? $users->random() // 75% logged-in user
                        : null;            // 25% guest

                    RequestVisit::create([
                        'request_id' => $request->id,
                        'visited_at' => Carbon::now()
                            ->subDays(rand(0, 30))
                            ->subHours(rand(0, 23))
                            ->subMinutes(rand(0, 59)),
                        'user_id' => $visitor?->id,
                        'ip_address' => fakeIp(),
                    ]);
                }
            }

            // Votes: deliberately structured so sorting by likes/favorites is meaningful
            foreach ($requests as $requestIndex => $request) {
                $authorId = $request->author_id;

                // Exclude author from voting on their own request most of the time
                $eligibleVoters = $users->filter(fn ($user) => $user->id !== $authorId)->values();

                // Decide vote profile by request position
                if ($requestIndex < 10) {
                    // Top 10 requests: clearly popular
                    $likeTarget = min(7, $eligibleVoters->count());
                    $dislikeTarget = min(1, max(0, $eligibleVoters->count() - $likeTarget));
                } elseif ($requestIndex < 20) {
                    // Next 10: mixed
                    $likeTarget = min(4, $eligibleVoters->count());
                    $dislikeTarget = min(3, max(0, $eligibleVoters->count() - $likeTarget));
                } elseif ($requestIndex < 35) {
                    // Next 15: mostly disliked
                    $likeTarget = min(1, $eligibleVoters->count());
                    $dislikeTarget = min(5, max(0, $eligibleVoters->count() - $likeTarget));
                } else {
                    // Remaining requests: low activity
                    $likeTarget = rand(0, 2);
                    $dislikeTarget = rand(0, 2);
                }

                $usedUserIds = [];

                // Create likes first
                for ($i = 0; $i < $likeTarget; $i++) {
                    $voter = $this->pickUnusedUser($eligibleVoters, $usedUserIds);
                    if (! $voter) {
                        break;
                    }

                    RequestVote::create([
                        'request_id' => $request->id,
                        'user_id' => $voter->id,
                        'vote' => 'like',
                    ]);
                }

                // Then dislikes
                for ($i = 0; $i < $dislikeTarget; $i++) {
                    $voter = $this->pickUnusedUser($eligibleVoters, $usedUserIds);
                    if (! $voter) {
                        break;
                    }

                    RequestVote::create([
                        'request_id' => $request->id,
                        'user_id' => $voter->id,
                        'vote' => 'dislike',
                    ]);
                }

                // Small chance of 1 extra random vote for natural variation
                if (rand(1, 100) <= 40) {
                    $voter = $this->pickUnusedUser($eligibleVoters, $usedUserIds);

                    if ($voter) {
                        RequestVote::create([
                            'request_id' => $request->id,
                            'user_id' => $voter->id,
                            'vote' => rand(0, 100) <= 70 ? 'like' : 'dislike',
                        ]);
                    }
                }
            }
        });
    }

    private function pickUnusedUser($users, array &$usedUserIds)
    {
        foreach ($users->shuffle()->values() as $user) {
            if (! in_array($user->id, $usedUserIds, true)) {
                $usedUserIds[] = $user->id;

                return $user;
            }
        }

        return null;
    }
}

/** Helper: lightweight fake IP */
function fakeIp(): string
{
    return rand(10, 223).'.'.rand(0, 255).'.'.rand(0, 255).'.'.rand(0, 255);
}
