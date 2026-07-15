<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use App\Services\EmbeddingClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $alice = User::create([
            'name' => 'Alice Test',
            'email' => 'alice@example.com',
            'password' => Hash::make('password'),
        ]);

        $bob = User::create([
            'name' => 'Bob Test',
            'email' => 'bob@example.com',
            'password' => Hash::make('password'),
        ]);

        $embeddingClient = app(EmbeddingClient::class);

        $samplePosts = [
            [$alice, "Went for a walk this morning and just sat by the lake for an hour. No filter, no plan, just needed the quiet."],
            [$bob, "Finally fixed that bug that's been haunting me for three days. Small win but it feels huge."],
            [$alice, "Funny travel story: got completely lost in Kochi last week looking for a bookstore, ended up finding the best filter coffee of my life instead."],
            [$bob, "Rainy evening, made soup, watched the window fog up. Simple day."],
        ];

        foreach ($samplePosts as [$user, $text]) {
            $post = Post::create([
                'user_id' => $user->id,
                'text' => $text,
                'authenticity_score' => 0.75,
            ]);

            $embedding = $embeddingClient->embed($text);
            DB::statement('UPDATE posts SET embedding = ?::vector WHERE id = ?', [
                Post::vectorLiteral($embedding),
                $post->id,
            ]);
        }
    }
}
