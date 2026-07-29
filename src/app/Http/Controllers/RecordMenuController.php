<?php

namespace App\Http\Controllers;

use App\Models\RecordMenu;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;

class RecordMenuController extends Controller
{
    public function create(Request $request, RecordMenu $recordMenu){
        // updateOrCreate()でCreateすると、既にデータがあるとupdateし、なければcreateを自動で行ってくれる。
        // updateOrCreate(['探索カラム名' => 条件となる値], ['値を格納するカラム名' => 値],);
        $recordMenu->updateOrCreate([
                'user_id'=>$request->user_id,
                'category_id'=>$request->category_id,
                'menu_id'=>$request->menu_id,
                'record_state_id'=>$request->record_state_id,
                'recorded_at'=>$request->recorded_at
            ],[
                'user_id'=>$request->user_id,
                'category_id'=>$request->category_id,
                'menu_id'=>$request->menu_id,
                'record_state_id'=>$request->record_state_id,
                'recorded_at'=>$request->recorded_at
            ]
        );
        return response()->json(["status_code" => 200, "message" => "記録開始します"]);
    }
}
