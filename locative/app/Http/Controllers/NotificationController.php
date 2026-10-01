<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Notifications de l'utilisateur connecté (cloche de l'en-tête). Le texte
 * est construit côté interface à partir de { type, params } (traduit).
 */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->notifications()->getQuery();

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $list = $this->paginated($query, $request)->getData(true);
        $list['data'] = array_map(fn (array $n) => [
            'id' => $n['id'],
            'type' => $n['data']['type'] ?? null,
            'params' => $n['data']['params'] ?? [],
            'link' => $n['data']['link'] ?? null,
            'read_at' => $n['read_at'],
            'created_at' => $n['created_at'],
        ], $list['data']);
        $list['unread'] = $request->user()->unreadNotifications()->count();

        return response()->json($list);
    }

    public function unreadCount(Request $request)
    {
        return response()->json(['data' => ['unread' => $request->user()->unreadNotifications()->count()]]);
    }

    public function markRead(Request $request, string $id)
    {
        /** @var DatabaseNotification $notification */
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['data' => ['unread' => $request->user()->unreadNotifications()->count()]]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['data' => ['unread' => 0]]);
    }
}
