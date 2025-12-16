<?php

namespace App\Helpers;

use App\Models\User;
use Illuminate\Support\Facades\Notification;

class NotificationHelper
{
    /**
     * Notify all administrators
     */
    public static function notifyAdmins($notification)
    {
        $admins = User::whereHas('rol', function ($query) {
            $query->where('nombre', 'Administrador');
        })->get();

        foreach ($admins as $admin) {
            $admin->notify($notification);
        }
    }

    /**
     * Notify users by role name
     */
    public static function notifyByRole(string $roleName, $notification)
    {
        $users = User::whereHas('rol', function ($query) use ($roleName) {
            $query->where('nombre', $roleName);
        })->get();

        foreach ($users as $user) {
            $user->notify($notification);
        }
    }

    /**
     * Notify all users except a specific one
     */
    public static function notifyAllExcept(int $userId, $notification)
    {
        $users = User::where('id', '!=', $userId)->get();

        foreach ($users as $user) {
            $user->notify($notification);
        }
    }

    /**
     * Notify specific users by IDs
     */
    public static function notifyUsers(array $userIds, $notification)
    {
        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            $user->notify($notification);
        }
    }
}
