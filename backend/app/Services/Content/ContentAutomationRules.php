<?php

namespace App\Services\Content;

/**
 * Per content-type automation policy (AI / review / auto-publish / priority).
 */
class ContentAutomationRules
{
    /**
     * @return array{use_ai: bool, review_required: bool, auto_publish: bool, label: string, ai_priority: string}
     */
    public function forEventType(?string $eventType): array
    {
        $type = $eventType ?: 'default';
        $rules = config('content.rules.'.$type);
        if (! is_array($rules)) {
            $rules = config('content.rules.default', [
                'use_ai' => false,
                'review_required' => true,
                'auto_publish' => false,
                'ai_priority' => AiBudgetManager::PRIORITY_NONE,
            ]);
        }

        $priority = strtolower((string) ($rules['ai_priority'] ?? (
            ($rules['use_ai'] ?? false) ? AiBudgetManager::PRIORITY_MEDIUM : AiBudgetManager::PRIORITY_NONE
        )));

        return [
            'use_ai' => (bool) ($rules['use_ai'] ?? false),
            'review_required' => (bool) ($rules['review_required'] ?? true),
            'auto_publish' => (bool) ($rules['auto_publish'] ?? false),
            'label' => (string) ($rules['label'] ?? $type),
            'ai_priority' => $priority,
        ];
    }

    public function shouldUseAi(?string $eventType): bool
    {
        return $this->forEventType($eventType)['use_ai'];
    }

    public function shouldAutoPublish(?string $eventType): bool
    {
        $rule = $this->forEventType($eventType);
        if ($rule['review_required']) {
            return false;
        }

        if ($rule['auto_publish']) {
            return true;
        }

        return (bool) config('content.auto_publish', false) && ! (bool) config('content.review_required', true);
    }

    public function requiresReview(?string $eventType): bool
    {
        return $this->forEventType($eventType)['review_required'];
    }

    public function aiPriority(?string $eventType): string
    {
        return $this->forEventType($eventType)['ai_priority'];
    }
}
