<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\Business;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIController extends Controller
{
    public function __construct(protected AIService $aiService)
    {
    }

    protected function checkTenantAccess(Request $request, AiConversation $conversation): void
    {
        /** @var Business $activeBusiness */
        $activeBusiness = $request->attributes->get('active_business');

        if (! $activeBusiness || $conversation->business_id !== $activeBusiness->id) {
            abort(404, 'Percakapan tidak ditemukan.');
        }
    }

    /**
     * List AI conversations for active business
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Business $activeBusiness */
        $activeBusiness = $request->attributes->get('active_business');

        $conversations = $this->aiService->getConversations($activeBusiness->id, $request->user()->id);

        return response()->json([
            'success' => true,
            'data' => [
                'conversations' => $conversations,
            ],
        ]);
    }

    /**
     * Create new AI conversation thread
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Business $activeBusiness */
        $activeBusiness = $request->attributes->get('active_business');

        $conversation = $this->aiService->createConversation(
            $activeBusiness->id,
            $request->user()->id,
            $request->input('title', 'Percakapan Baru')
        );

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => $conversation,
            ],
        ], 201);
    }

    /**
     * Get conversation detail with message history
     */
    public function show(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->checkTenantAccess($request, $conversation);

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => $conversation,
                'messages' => $conversation->messages,
            ],
        ]);
    }

    /**
     * Send user message & get AI response
     */
    public function sendMessage(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->checkTenantAccess($request, $conversation);

        $request->validate([
            'message' => ['required', 'string'],
        ]);

        $result = $this->aiService->sendMessage($conversation, $request->input('message'));

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
