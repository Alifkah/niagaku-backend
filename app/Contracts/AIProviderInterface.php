<?php

namespace App\Contracts;

interface AIProviderInterface
{
    /**
     * Generate response for user query given structured context
     */
    public function generateResponse(string $userPrompt, array $businessContext): string;
}
