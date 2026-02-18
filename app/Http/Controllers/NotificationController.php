<?php

namespace App\Http\Controllers;

use App\Models\AuraNotification;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function markRead(AuraNotification $notification): JsonResponse
    {
        abort_if($notification->user_id !== auth()->id(), 403);

        $notification->update(['is_read' => true]);

        return response()->json(['ok' => true]);
    }

    public function readAll(): JsonResponse
    {
        AuraNotification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['ok' => true]);
    }
}
