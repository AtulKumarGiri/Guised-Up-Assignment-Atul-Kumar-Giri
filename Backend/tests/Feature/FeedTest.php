<?php

namespace Tests\Feature;

use App\Models\Interaction;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    protected function seedVector(Post $post, array $vector): void
    {
        DB::statement('UPDATE posts SET embedding = ?::vector WHERE id = ?', [
            Post::vectorLiteral($vector), $post->id,
        ]);
    }

    public function test_authenticated_user_can_create_a_post(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/posts', [
            'text' => 'A genuinely long and thoughtful post about a quiet afternoon spent reading.',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('posts', ['user_id' => $user->id]);
    }

    public function test_unauthenticated_user_cannot_create_a_post(): void
    {
        $response = $this->postJson('/api/posts', ['text' => 'Should fail']);

        $response->assertStatus(401);
    }

    public function test_feed_ranks_posts_from_frequently_interacted_authors_higher(): void
    {
        $viewer = User::factory()->create();
        $closeAuthor = User::factory()->create();
        $strangerAuthor = User::factory()->create();

        $closePost = Post::factory()->create(['user_id' => $closeAuthor->id, 'authenticity_score' => 0.5]);
        $strangerPost = Post::factory()->create(['user_id' => $strangerAuthor->id, 'authenticity_score' => 0.5]);

        $this->seedVector($closePost, array_fill(0, 384, 0));
        $this->seedVector($strangerPost, array_fill(0, 384, 0));

        // Viewer has interacted with closeAuthor's posts many times, never with stranger
        for ($i = 0; $i < 5; $i++) {
            Interaction::create(['user_id' => $viewer->id, 'post_id' => $closePost->id, 'type' => 'reaction']);
        }

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/feed');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();

        $closeIndex = array_search($closePost->id, $ids);
        $strangerIndex = array_search($strangerPost->id, $ids);

        $this->assertNotFalse($closeIndex);
        $this->assertNotFalse($strangerIndex);
        $this->assertLessThan($strangerIndex, $closeIndex, 'Post from frequently-interacted author should rank higher');
    }

    public function test_interaction_can_be_logged_against_a_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/interactions', [
            'post_id' => $post->id,
            'type' => 'view',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('interactions', [
            'user_id' => $user->id,
            'post_id' => $post->id,
            'type' => 'view',
        ]);
    }
}
