<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user.
     */
    public function index()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $notifications = $user->notifications()->latest()->paginate(20);

        // Transform data to match frontend interface
        $data = $notifications->map(function ($notification) {
            return [
                'id' => $notification->id,
                'titulo' => $notification->data['title'] ?? 'Notificación',
                'mensaje' => $notification->data['message'] ?? '',
                'tipo' => $notification->data['type'] ?? 'info',
                'leido' => !is_null($notification->read_at),
                'created_at' => $notification->created_at,
                'updated_at' => $notification->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ]
        ]);
    }

    /**
     * Get unread notifications for the authenticated user.
     */
    public function unread()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $notifications = $user->unreadNotifications()->latest()->get();

        $data = $notifications->map(function ($notification) {
            return [
                'id' => $notification->id,
                'titulo' => $notification->data['title'] ?? 'Notificación',
                'mensaje' => $notification->data['message'] ?? '',
                'tipo' => $notification->data['type'] ?? 'info',
                'leido' => false,
                'created_at' => $notification->created_at,
                'updated_at' => $notification->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead($id)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $notification = $user->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->markAsRead();
            return response()->json(['success' => true, 'message' => 'Notificación marcada como leída']);
        }

        return response()->json(['success' => false, 'message' => 'Notificación no encontrada'], 404);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $user->unreadNotifications->markAsRead();

        return response()->json(['success' => true, 'message' => 'Todas las notificaciones marcadas como leídas']);
    }

    /**
     * Delete a notification.
     */
    public function destroy($id)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $notification = $user->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->delete();
            return response()->json(['success' => true, 'message' => 'Notificación eliminada']);
        }

        return response()->json(['success' => false, 'message' => 'Notificación no encontrada'], 404);
    }
}
