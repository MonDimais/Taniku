<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    private function requireLogin()
    {
        $uid = Session::get('user_id');
        if (!$uid) return null;
        $user = DB::table('users')->where('id_user', $uid)->first();
        return $user;
    }

    public function index(Request $request)
    {
        $user = $this->requireLogin();
        if (!$user) {
            return response()->json(['error' => 'Not authorized'], 401);
        }
        $notifs = DB::table('notifications')
            ->where('id_user', $user->id_user)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($notifs);
    }

    public function unreadCount(Request $request)
    {
        $user = $this->requireLogin();
        if (!$user) {
            return response()->json(['count' => 0], 401);
        }
        $count = DB::table('notifications')
            ->where('id_user', $user->id_user)
            ->where('is_read', 0)
            ->count();
        return response()->json(['count' => $count]);
    }

    public function readAll(Request $request)
    {
        $user = $this->requireLogin();
        if (!$user) {
            return response()->json(['error' => 'Not authorized'], 401);
        }
        DB::table('notifications')
            ->where('id_user', $user->id_user)
            ->update(['is_read' => 1]);
        return response()->json(['success' => true]);
    }

    public function read(Request $request, $id)
    {
        $user = $this->requireLogin();
        if (!$user) {
            return response()->json(['error' => 'Not authorized'], 401);
        }
        DB::table('notifications')
            ->where('id_notification', $id)
            ->where('id_user', $user->id_user)
            ->update(['is_read' => 1]);
        return response()->json(['success' => true]);
    }
}
