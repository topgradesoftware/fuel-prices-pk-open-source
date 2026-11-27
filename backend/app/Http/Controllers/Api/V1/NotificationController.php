<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListNotificationsRequest;
use App\Repositories\NotificationRepository;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationRepository $notifications,
    ) {}

    public function index(ListNotificationsRequest $request): JsonResponse
    {
        $perPage = (int) ($request->validated('per_page') ?? 20);
        $page = $this->notifications->recent($perPage);

        return ApiResponse::success(
            [
                'items' => $page->getCollection()->map(fn ($n) => [
                    'id' => $n->id,
                    'type' => $n->type->value,
                    'title' => $n->title,
                    'body' => $n->body,
                    'data' => $n->data,
                    'created_at' => $n->created_at?->toIso8601String(),
                ])->values(),
            ],
            'Notifications',
            [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        );
    }
}
