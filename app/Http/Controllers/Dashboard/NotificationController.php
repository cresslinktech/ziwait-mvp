<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\Notifications\NotificationLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** Push on/off for this device, plus the user's own recent delivery attempts. */
    public function index(Request $request)
    {
        return view('dashboard.notifications.index', [
            'logs' => NotificationLog::where('user_id', $request->user()->id)->with('order:id,reference')->latest('id')->limit(30)->get(),
            'devices' => $request->user()->pushSubscriptions()->count(),
        ]);
    }
}
