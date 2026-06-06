<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            // Foreign key to link to the user who authored the comment
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            // The main content of the comment
            $table->text('body');
            // A flag to indicate if this comment is the chosen answer for a request
            $table->boolean('is_chosen_answer')->default(false);
            // For threaded comments: links to a parent comment. Nullable if it's a top-level comment.
            $table->foreignId('parent_id')->nullable()->constrained('comments')->onDelete('cascade');
            // Foreign key to link to the request this comment belongs to
            $table->foreignId('request_id')->constrained('requests')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
