<?php

namespace App\Http\Controllers;

class HealthController extends Controller
{
    // EventBridge Schedulerからのウォームアップping、監視の疎通確認用
    public function check()
    {
        return response()->json(['status' => 'ok']);
    }
}
