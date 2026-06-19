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
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('token', 500); // VARCHAR(500) - JWTs are typically < 500 chars
            $table->timestamp('expires_at');
            $table->boolean('revoked')->default(false);
            $table->timestamp('revoked_at')->nullable();
            $table->text('replacement_refresh_token')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked']);
            $table->unique('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
