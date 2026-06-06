<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temp_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type');
            $table->string('file_hash')->unique(); // Prevent duplicate uploads
            $table->string('file_path');           // Path in storage (temp directory)
            
            // Polymorphic - will be filled after attaching to request/comment
            $table->unsignedBigInteger('attachable_id')->nullable();
            $table->string('attachable_type')->nullable();
            
            $table->timestamp('expires_at');
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'expires_at']);
            $table->index(['attachable_type', 'attachable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temp_files');
    }
};