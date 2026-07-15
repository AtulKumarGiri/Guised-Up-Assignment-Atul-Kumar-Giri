<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->string('image_url', 2048)->nullable();
            $table->boolean('has_filter')->default(false);
            $table->float('authenticity_score')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->index('created_at');
        });

        // pgvector column can't be added via Blueprint helpers directly — raw SQL
        DB::statement('ALTER TABLE posts ADD COLUMN embedding vector(384)');

        // HNSW index for fast approximate nearest-neighbor search
        DB::statement('CREATE INDEX idx_posts_embedding_hnsw ON posts USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
