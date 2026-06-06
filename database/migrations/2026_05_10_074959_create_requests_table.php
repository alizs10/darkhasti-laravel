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
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            // Changed to datetime as requested
            $table->dateTime('published_at')->nullable(); // Added nullable() in case it's not always set immediately
            // Added author_id as a foreign key referencing the users table
            $table->foreignId('author_id')->nullable()->constrained('users')->onDelete('cascade'); // Use constrained() for foreign key
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('requests', function (Blueprint $table) {
            $table->fullText(['title', 'description']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::table('requests', function (Blueprint $table) {
            $table->dropFullText(['title', 'description']);
        });

        Schema::dropIfExists('requests');
    }
};
