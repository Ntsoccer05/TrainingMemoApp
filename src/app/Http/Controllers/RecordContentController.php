<?php

namespace App\Http\Controllers;

use App\Models\RecordMenu;
use App\Models\RecordContent;
use App\Models\RecordState;
use Illuminate\Http\Request;

class RecordContentController extends Controller
{
    public function index(Request $request, RecordMenu $recordMenu, RecordState $recordState){
        $tgtRecords = Null;

        $user_id = $request->user_id;
        $category_id = $request->category_id;
        $menu_id = $request->menu_id;
        $record_state_id = $request->record_state_id;
        $recorded_at = $request->recorded_at;
        $recordContents=[];
        $recordContent=[];
        $menu=[];
        $category=[];

        // ホーム画面で記録の詳細表示
        if(!$category_id && !$recorded_at){
            //親テーブルがあると子供のデータ取得時に呼び出した親のデータも一緒に取得できる。
            //load()によってN+1問題対応 load()の引数はModels/RecordState.phpの関数名
            $records = $recordState->where('user_id', $user_id)->get()->load(['recordMenus']);
            //記録日の重複削除
            $records = $records->unique('recorded_at');
            foreach($records as $record){
                // 初期化(初期化しないと前回のデータに追加されてしまうため)
                $recordContent=[];
                $menu = [];
                $category = [];

                $recordMenus = $record->recordMenus;
                $recorded_at = [
                    "record_id"=>$record->id,
                    "recorded_at"=>$record->recorded_at
                ];
                $hasRecordMenu = $recordMenu->where('record_state_id', $record->id)->exists();
                //load()によってN+1問題対応 load()の引数はModels/RecordMenu.phpの関数名
                $tgtRecordMenu = $recordMenu->where('record_state_id', $record->id)->get()->load(['menu', 'category']);
                $recordContent['recorded_at']=$recorded_at;
                // メニュー登録がある場合
                if($hasRecordMenu){
                    foreach($tgtRecordMenu as $recordMenuContent){
                            $menuContent = $recordMenuContent->menu->content;
                            $menuId = $recordMenuContent->menu_id;
                            $categoryContent = $recordMenuContent->category->content;
                            $categoryId = $recordMenuContent->category_id;
                            $menu[] = [
                                "menu_id"=>$menuId,
                                "menu_content"=>$menuContent
                            ];
                            $category[] = [
                                "category_id"=>$categoryId,
                                "category_content"=>$categoryContent
                            ];
                            // array_unique：重複を削除
                            $menu = array_unique($menu, SORT_REGULAR);
                            $category = array_unique($category, SORT_REGULAR);
                            $recordContent['menu']=$menu;
                            $recordContent['category']=$category;
                        }
                }
                $recordContents[] = $recordContent;
            }
            return response()->json(["status_code" => 200, "message" => "記録した全てのデータを取得", 'records'=>$recordContents]);
        }
        // メニュー選択画面にて記録済みメニューをマーキング
        if(isset($recorded_at)){
            $record = $recordState->where('user_id', $user_id)->where('recorded_at', $recorded_at)->first()->load(['recordMenus']);
            // 初期化
            $recordContent=[];
            $recorded_at = [
                "record_id"=>$record->id,
                "recorded_at"=>$record->recorded_at
            ];
            $hasRecordMenu = $recordMenu->where('record_state_id', $record->id)->exists();
            $recordContent['recorded_at']=$recorded_at;
            // メニュー登録がある場合
            if($hasRecordMenu){
                $recordMenus = $record->recordMenus->load(['menu', 'category']);
                foreach($recordMenus as $i =>$recordMenu){
                    $menuContent = $recordMenu->menu->content;
                    $menuId = $recordMenu->menu_id;
                    $categoryContent = $recordMenu->category->content;
                    $categoryId = $recordMenu->category_id;
                    $menu[] = [
                        "menu_id"=>$menuId,
                        "menu_content"=>$menuContent
                    ];
                    $category[] = [
                        "category_id"=>$categoryId,
                        "category_content"=>$categoryContent
                    ];
                    // array_unique：重複を削除
                    $menu = array_unique($menu, SORT_REGULAR);
                    $category = array_unique($category, SORT_REGULAR);
                    $recordContent['menu']=$menu;
                    $recordContent['category']=$category;
                }
            }
            $recordContents[] = $recordContent;
            return response()->json(["status_code" => 200, "message" => "選択した日付のデータを取得", 'records'=>$recordContents]);
        }
        // 筋トレ記録画面にて記録済み内容を表示
        $tgtRecord=$recordMenu->where(function($query) use($user_id, $category_id, $menu_id,$record_state_id){
            $query->where([['user_id', $user_id], ['category_id', $category_id], ['menu_id', $menu_id],['record_state_id', $record_state_id]]);
        })->first();

        if($tgtRecord){
            $tgtRecords=$tgtRecord->recordContents()->orderBy('set', 'asc')->get();
            return response()->json(["status_code" => 200, "message" => "記録日のデータを取得", 'tgtRecords'=>$tgtRecords]);
        }else{
            return response()->json(["status_code" => 200, "message" => "記録日のデータはありません", 'tgtRecords'=>$tgtRecords]);
        }
    }

    // 履歴を確認時に使用
    public function show(Request $request, RecordMenu $recordMenu){
        $historyTgtRecords = null;

        $user_id = $request->user_id;
        $category_id = $request->category_id;
        $menu_id = $request->menu_id;
        $record_state_id = $request->record_state_id;
        $recorded_at = $request->recorded_at;

        // loadで特定のカラムのみ取得する場合リレーションに必要な主キーや外部キーは必ず指定する必要がある
        $historyTgtMenus = $recordMenu->where(function($query) use($user_id, $category_id, $menu_id, $recorded_at){
            $query->where([['user_id', $user_id], ['category_id', $category_id], ['menu_id', $menu_id], ['recorded_at', '<', $recorded_at]]);
        })->orderBy('recorded_at', 'desc')->take(5)->get()->load('recordState:id,bodyWeight');

        if($historyTgtMenus){
            foreach($historyTgtMenus as $historyTgtMenu){
                $historyTgtRecords[] = $historyTgtMenu->recordContents()->orderBy('set', 'asc')->get();
            }
            return response()->json(["status_code" => 200, "message" => "記録日のデータを取得", "historyTgtMenus"=>$historyTgtMenus, "historyTgtRecords"=> $historyTgtRecords]);
        }
    }

    // 筋トレ内容を記録
    public function create(Request $request, RecordMenu $recordMenu,RecordContent $recordContent){
        $user_id = $request->user_id;
        $category_id = $request->category_id;
        $menu_id = $request->menu_id;
        $record_state_id = $request->record_state_id;
        $recorded_at = $request->recorded_at;

        $totalSet = 0;
        
        $tgtRecordMenu=$recordMenu->where(function($query) use($user_id, $category_id, $menu_id,$record_state_id){
            $query->where([['user_id', $user_id], ['category_id', $category_id], ['menu_id', $menu_id],['record_state_id', $record_state_id]]);
        })->first();
        if($tgtRecordMenu){
            if($request->set === 0){
                return response()->json(["status_code" => 200, "message" => "初期データは登録済みです。"]);
            }
            $tgtMenu = $tgtRecordMenu->menu;
            $tgtRecordContent = $tgtRecordMenu->recordContents()->where('set',$request->set)->first();
            if($tgtRecordContent){
                if(!$request->weight && 
                   !$request->rep && 
                   !$request->right_weight && 
                   !$request->right_rep && 
                   !$request->left_weight &&
                   !$request->left_rep
                ){
                    $tgtRecordContent->delete();
                }else{
                    $tgtRecordContent->user_id=$request->user_id;
                    $tgtRecordContent->record_state_id=$request->record_state_id;
                    $tgtRecordContent->record_menu_id=$tgtRecordMenu->id;
                    $tgtRecordContent->menu_id=$tgtRecordMenu->menu_id;
                    $tgtRecordContent->category_id=$tgtRecordMenu->category_id;
                    $tgtRecordContent->weight = $request->weight;
                    $tgtRecordContent->right_weight = $request->right_weight;
                    $tgtRecordContent->right_rep = $request->right_rep;
                    $tgtRecordContent->left_weight = $request->left_weight;
                    $tgtRecordContent->left_rep = $request->left_rep;
                    $tgtRecordContent->set = $request->set;
                    $tgtRecordContent->rep = $request->rep;
                    if(empty($tgtMenu->oneSide) && ($request->weight || $request->rep)){
                        $tgtRecordContent->volume = $request->weight * $request->rep;
                    }
                    if(!empty($tgtMenu->oneSide) && ($request->right_weight || $request->right_rep)){
                        $tgtRecordContent->right_volume = $request->right_weight * $request->right_rep;
                    }
                    if(!empty($tgtMenu->oneSide) && ($request->left_weight || $request->left_rep)){
                        $tgtRecordContent->left_volume = $request->left_weight * $request->left_rep;
                    }
                    $tgtRecordContent->memo = $request->memo;
                    $tgtRecordContent->save();
                }
                $totalSet = $tgtRecordMenu->recordContents()->count();
                if($totalSet === 0){
                    $tgtRecordMenu->delete();
                }
                return response()->json(["status_code" => 200, "message" => "記録を更新しました。",  "totalSet"=> $totalSet]);
            }
        }else{
            if(!$request->weight && 
               !$request->rep && 
               !$request->right_weight && 
               !$request->right_rep && 
               !$request->left_weight &&
               !$request->left_rep &&
               ($request->set !== 0)
            ){
                return response()->json(["status_code" => 200, "message" => "データを入力してください。",  "totalSet"=> $totalSet]);
            }else{
                $recordMenu->user_id=$request->user_id;
                $recordMenu->record_state_id=$record_state_id;
                $recordMenu->recorded_at=$recorded_at;
                $recordMenu->menu_id=$menu_id;
                $recordMenu->category_id=$category_id;
                $recordMenu->save();
                if($request->set === 0){
                    return response()->json(["status_code" => 200, "message" => "初期データを登録しました。"]);
                }
            }
        }
        $tgtRecordMenu=$recordMenu->where(function($query) use($user_id, $category_id, $menu_id,$record_state_id){
            $query->where([['user_id', $user_id], ['category_id', $category_id], ['menu_id', $menu_id],['record_state_id', $record_state_id]]);
        })->first();
        if(!$request->weight &&
           !$request->rep &&
           !$request->right_weight &&
           !$request->right_rep &&
           !$request->left_weight &&
           !$request->left_rep
        ){
            $totalSet = $tgtRecordMenu->recordContents()->count();
            return response()->json(["status_code" => 200, "message" => "データを入力してください。",  "totalSet"=> $totalSet]);
        }else{
            $tgtMenu = $tgtRecordMenu->menu;
            $recordContent->user_id=$request->user_id;
            $recordContent->record_state_id=$request->record_state_id;
            $recordContent->record_menu_id=$tgtRecordMenu->id;
            $recordContent->menu_id=$tgtRecordMenu->menu_id;
            $recordContent->category_id=$tgtRecordMenu->category_id;
            $recordContent->weight = $request->weight;
            $recordContent->right_weight = $request->right_weight;
            $recordContent->right_rep = $request->right_rep;
            $recordContent->left_weight = $request->left_weight;
            $recordContent->left_rep = $request->left_rep;
            $recordContent->set = $request->set;
            $recordContent->rep = $request->rep;
            if(empty($tgtMenu->oneSide) && ($request->weight || $request->rep)){
                $recordContent->volume = $request->weight * $request->rep;
            }
            if(!empty($tgtMenu->oneSide) && ($request->right_weight || $request->right_rep)){
                $recordContent->right_volume = $request->right_weight * $request->right_rep;
            }
            if(!empty($tgtMenu->oneSide) && ($request->left_weight || $request->left_rep)){
                $recordContent->left_volume = $request->left_weight * $request->left_rep;
            }
            $recordContent->memo = $request->memo;
            $recordContent->save();
            $totalSet = $tgtRecordMenu->recordContents()->count();
            return response()->json(["status_code" => 200, "message" => "新規に記録しました。", "tgtRecordMenu"=>$tgtRecordMenu,  "totalSet"=> $totalSet]);
        }
    }

    // 筋トレ内容を削除
    public function delete(Request $request, RecordMenu $recordMenu){
        $user_id = $request->user_id;
        $category_id = $request->category_id;
        $menu_id = $request->menu_id;
        $record_state_id = $request->record_state_id;
        $set = $request->set;

        $tgtRecordMenu=$recordMenu->where(function($query) use($user_id, $category_id, $menu_id,$record_state_id){
            $query->where([['user_id', $user_id], ['category_id', $category_id], ['menu_id', $menu_id],['record_state_id', $record_state_id]]);
        })->first();
        if($tgtRecordMenu){
            $tgtRecordMenu->delete();
            return response()->json(["status_code" => 200, "message" => "データを削除しました。"]);
        }else{
            return response()->json(["status_code" => 200, "message" => "削除データが存在しませんでした。"]);
        }
        return response()->json(["status_code" => 200, "message" => "削除データが存在しませんでした。"]);
    }
}