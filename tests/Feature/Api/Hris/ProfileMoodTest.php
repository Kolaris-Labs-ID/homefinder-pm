<?php

namespace Tests\Feature\Api\Hris;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileMoodTest extends TestCase
{
    use RefreshDatabase;

    public $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'mood_emoji' => '😎',
            'mood_text' => 'shipping',
        ]);

        Sanctum::actingAs($this->user);
    }

    #[Test]
    public function updating_only_the_text_leaves_the_existing_emoji_alone()
    {
        $this->putJson('/api/hris/profile/mood', ['mood_text' => 'reviewing'])
            ->assertOk()
            ->assertJson(['mood_emoji' => '😎', 'mood_text' => 'reviewing']);

        $this->assertSame('😎', $this->user->fresh()->mood_emoji);
    }

    #[Test]
    public function updating_only_the_emoji_leaves_the_existing_text_alone()
    {
        $this->putJson('/api/hris/profile/mood', ['mood_emoji' => '🎉'])
            ->assertOk()
            ->assertJson(['mood_emoji' => '🎉', 'mood_text' => 'shipping']);

        $this->assertSame('shipping', $this->user->fresh()->mood_text);
    }

    #[Test]
    public function an_explicit_null_still_clears_the_field()
    {
        $this->putJson('/api/hris/profile/mood', ['mood_emoji' => null])
            ->assertOk()
            ->assertJson(['mood_emoji' => null, 'mood_text' => 'shipping']);

        $this->assertNull($this->user->fresh()->mood_emoji);
    }

    #[Test]
    public function both_fields_update_together_when_both_are_sent()
    {
        $this->putJson('/api/hris/profile/mood', [
            'mood_emoji' => '🚀',
            'mood_text' => 'launching',
        ])->assertOk();

        $fresh = $this->user->fresh();
        $this->assertSame('🚀', $fresh->mood_emoji);
        $this->assertSame('launching', $fresh->mood_text);
    }
}
