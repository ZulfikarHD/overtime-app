<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of unread notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $unreadNotifications = $user->unreadNotifications()
            ->latest()
            ->take(50)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => (string) $notification->id,
                    'type' => $notification->type,
                    'data' => $notification->data,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at?->toIso8601String(),
                    'created_at_human' => $notification->created_at?->diffForHumans(),
                ];
            });

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $unreadNotifications,
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->first();

        if (! $notification) {
            return response()->json([
                'message' => 'Notification not found.',
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read.',
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark all unread notifications as read for the authenticated user.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'All notifications marked as read.',
            'unread_count' => 0,
        ]);
    }
}
