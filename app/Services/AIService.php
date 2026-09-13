<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\AI\MockAIProvider;
use Illuminate\Support\Carbon;

class AIService
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected ReceivablesService $receivablesService,
        protected ReportService $reportService,
        protected MockAIProvider $aiProvider
    ) {
    }

    /**
     * Get conversations for active business & user
     */
    public function getConversations(string $businessId, string $userId)
    {
        return AiConversation::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->orderBy('updated_at', 'desc')
            ->get();
    }

    /**
     * Create new AI conversation thread
     */
    public function createConversation(string $businessId, string $userId, string $title = 'Percakapan Baru'): AiConversation
    {
        return AiConversation::create([
            'business_id' => $businessId,
            'user_id' => $userId,
            'title' => $title,
        ]);
    }

    /**
     * Send user message, execute controlled business tools, and generate AI reply
     */
    public function sendMessage(AiConversation $conversation, string $userPrompt): array
    {
        // 1. Save User Message
        $userMsg = AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userPrompt,
        ]);

        // Auto-update conversation title if first message
        if ($conversation->title === 'Percakapan Baru') {
            $conversation->update(['title' => mb_substr($userPrompt, 0, 30)]);
        }

        // 2. Execute Controlled Business Tools (Strictly scoped by active business_id)
        $businessId = $conversation->business_id;
        $now = Carbon::now();
        [$start, $end] = $this->reportService->resolveDateRange('this_month', null, null);

        $businessContext = [
            'dashboard' => $this->dashboardService->getDashboardMetrics($businessId),
            'receivables' => $this->receivablesService->getReceivablesData($businessId),
            'expenses' => $this->reportService->getExpenseReport($businessId, $start, $end),
        ];

        // 3. Generate AI Response via Provider
        $aiReplyContent = $this->aiProvider->generateResponse($userPrompt, $businessContext);

        // 4. Save Assistant Reply
        $assistantMsg = AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $aiReplyContent,
        ]);

        $conversation->touch();

        return [
            'user_message' => $userMsg,
            'assistant_message' => $assistantMsg,
        ];
    }
}
