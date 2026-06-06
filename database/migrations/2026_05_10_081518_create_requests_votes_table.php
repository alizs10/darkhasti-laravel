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
        Schema::create('requests_votes', function (Blueprint $table) {
            $table->id();
            // Foreign key to link to the specific request
            $table->foreignId('request_id')->constrained('requests')->onDelete('cascade');
            // Stores the vote type: 'like' or 'dislike'
            $table->enum('vote', ['like', 'dislike']); // Using enum for restricted choices
            // Foreign key to link to the user who voted
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Add a unique constraint to prevent a user from voting multiple times on the same request
            $table->unique(['request_id', 'user_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests_votes');
    }
};
