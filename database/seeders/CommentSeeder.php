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

            foreach ($requests as $requestIndex => $request) {
                $topLevelCount = rand(3, 7);
                $topLevelComments = collect();
                $allComments = collect();

                for ($i = 0; $i < $topLevelCount; $i++) {
                    $author = $users->random();
                    $comment = Comment::create([
                        'author_id' => $author->id,
                        'request_id' => $request->id,
                        'parent_id' => null,
                        'body' => $this->makeBody($request->title, $author->username, null),
                        'is_chosen_answer' => false,
                        'created_at' => $this->randomCreatedAt($request->published_at),
                        'updated_at' => Carbon::now(),
                    ]);

                    $topLevelComments->push($comment);
                    $allComments->push($comment);
                }

                foreach ($topLevelComments as $parentComment) {
                    if (rand(1, 100) <= 60) {
                        $replyCount = rand(1, 3);
                        for ($j = 0; $j < $replyCount; $j++) {
                            $replyAuthor = $users->where('id', '!=', $parentComment->author_id)->random();
                            $reply = Comment::create([
                                'author_id' => $replyAuthor->id,
                                'request_id' => $request->id,
                                'parent_id' => $parentComment->id,
                                'body' => $this->makeBody($request->title, $replyAuthor->username, $parentComment->author->username),
                                'is_chosen_answer' => false,
                                'created_at' => $this->randomCreatedAt($parentComment->created_at),
                                'updated_at' => Carbon::now(),
                            ]);

                            $allComments->push($reply);

                            if (rand(1, 100) <= 30) {
                                $subReplyAuthor = $users->where('id', '!=', $reply->author_id)->random();
                                $subReply = Comment::create([
                                    'author_id' => $subReplyAuthor->id,
                                    'request_id' => $request->id,
                                    'parent_id' => $reply->id,
                                    'body' => $this->makeBody($request->title, $subReplyAuthor->username, $reply->author->username),
                                    'is_chosen_answer' => false,
                                    'created_at' => $this->randomCreatedAt($reply->created_at),
                                    'updated_at' => Carbon::now(),
                                ]);

                                $allComments->push($subReply);
                            }
                        }
                    }
                }

                if (rand(1, 100) <= 45 && $allComments->isNotEmpty()) {
                    $chosen = $allComments->random();
                    $chosen->update(['is_chosen_answer' => true]);
                }
            }
        });
    }

    private function makeBody(string $requestTitle, string $authorUsername, ?string $replyToUsername): string
    {
        $templates = [
            'این موضوع برای خیلی‌ها مهم است. به نظرم بهتر است در بخش اول بیشتر روی نیازها تمرکز کنید.',
            'با توجه به تجربه‌ام، اگر یک نمونه کار مرتبط داشته باشید شانس پاسخگویی بالاتری دارید.',
            'اگر توضیح بیشتری درباره سابقه داشته باشید، می‌توان پیشنهاد بهتری داد.',
            'این سوال معمولاً در مصاحبه پرسیده می‌شود، پس بهتر است جواب دقیق و شفاف آماده کنید.',
            'من هم با همین مشکل روبه‌رو بودم و راه‌حل من این بود که اول از همه جزئیات را در یک فایل پیوست کنم.',
            'به نظر می‌رسد نیاز دارید بخش اهداف شغلی را روشن‌تر بنویسید.',
            'اگر بخواهید، می‌توانم یک جمله برای معرفی بهتر پیشنهاد کنم.',
            'این موضوع مهم است؛ شاید بهتر باشد مثال‌های واقعی اضافه کنید.',
            'دیدگاه شخصی من این است که روی ساختار پاسخ تمرکز کنید و سپس هزینه‌ها را مقایسه کنید.',
            'اگر پاسخ‌ها‌ی قبلی کمک نکرد، لطفا جزئیات بیشتری از وضعیت فعلی ارسال کنید.',
        ];

        $baseText = $templates[array_rand($templates)];

        if ($replyToUsername !== null) {
            return "@{$replyToUsername} {$baseText}";
        }

        return $baseText;
    }

    private function randomCreatedAt($referenceDate)
    {
        if (! $referenceDate instanceof Carbon) {
            $referenceDate = Carbon::parse($referenceDate);
        }

        return $referenceDate
            ->copy()
            ->addHours(rand(1, 72))
            ->addMinutes(rand(0, 59));
    }
}
