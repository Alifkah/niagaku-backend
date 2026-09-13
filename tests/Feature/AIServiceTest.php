<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected Business $businessA;
    protected Business $businessB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->create();
        $this->businessA = Business::create(['name' => 'Usaha AI A']);
        $this->businessB = Business::create(['name' => 'Usaha AI B']);

        BusinessUser::create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->userA->id,
            'role' => 'OWNER',
        ]);
    }

    /**
     * Test AI Conversation Creation & Messaging with Controlled Tools
     */
    public function test_ai_conversation_creation_and_messaging(): void
    {
        // 1. Create Conversation
        $createRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson('/api/v1/ai/conversations', ['title' => 'Tanya Laba']);

        $createRes->assertStatus(201)
            ->assertJsonPath('success', true);

        $conversationId = $createRes->json('data.conversation.id');

        // 2. Send Message
        $msgRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson("/api/v1/ai/conversations/{$conversationId}/messages", [
                'message' => 'Berapa profit saya bulan ini?',
            ]);

        $msgRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_message.role', 'user')
            ->assertJsonPath('data.assistant_message.role', 'assistant');

        $this->assertStringContainsString('Ringkasan Performa Laba Rugi', $msgRes->json('data.assistant_message.content'));
    }

    /**
     * Test AI Tenant Isolation
     */
    public function test_ai_tenant_isolation(): void
    {
        // Conversation in Business B
        $createRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessA->id)
            ->postJson('/api/v1/ai/conversations', ['title' => 'Privat B']);

        $conversationId = $createRes->json('data.conversation.id');

        // User attempting to access Business B conversation with Business A header must fail 404
        $accessRes = $this->actingAs($this->userA)
            ->withHeader('X-Business-ID', $this->businessB->id)
            ->getJson("/api/v1/ai/conversations/{$conversationId}");

        $accessRes->assertStatus(403);
    }
}
