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
        Schema::create('attached_files', function (Blueprint $table) {
            $table->id();
            // The original name of the uploaded file
            $table->string('file_name');
            // The size of the file in bytes (or kilobytes, etc., depending on your convention)
            $table->unsignedBigInteger('file_size');
            // The MIME type of the file (e.g., 'image/jpeg', 'application/pdf')
            $table->string('mime_type');
            // The user who uploaded the file
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->string('file_path');           // Path in storage

            // Polymorphic relationship columns
            // 'attachable_id' will store the ID of the related model (request or comment)
            $table->unsignedBigInteger('attachable_id');
            // 'attachable_type' will store the class name of the related model (e.g., 'App\Models\Request', 'App\Models\Comment')
            $table->string('attachable_type');

            $table->string('file_hash')->nullable();
            // Constraint: Prevent uploading the exact same file content (identified by hash)
            // to the same parent item by the same user more than once.
            $table->unique(['attachable_id', 'attachable_type', 'user_id', 'file_hash'], 'fk_attachable_user_filehash');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attached_files');
    }
};
