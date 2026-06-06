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
        Schema::create('requests_visits', function (Blueprint $table) {
            $table->id();
            // Foreign key to link to the specific request
            $table->foreignId('request_id')->constrained('requests')->onDelete('cascade');
            // Timestamp to record when the visit happened
            $table->timestamp('visited_at')->useCurrent(); // Automatically set to current timestamp on creation

            // Optional: To track unique visitors
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('ip_address')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests_visits');
    }
};
