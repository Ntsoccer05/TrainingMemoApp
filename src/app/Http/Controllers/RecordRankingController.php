<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\RankingRecord;
use App\Models\RecordContent;
use App\Models\RecordMenu;
use Illuminate\Http\Request;

class RecordRankingController extends Controller
{
    // 個人のメニュー別MAX表示
    public function index(Request $request, Menu $menu,RecordMenu $recordMenu, RecordContent $recordContent){
        $user_id = $request->user_id;
        
        $dispContents=[];
        $bestContents=[];

        //load()によってN+1問題対応 loadしたリレーション先のデータも格納される
        // distinctとselectはセット、distinctで重複削除、selectで取り出すカラムを取得
        $recordMenus = $recordMenu->where('user_id', $user_id)->distinct()->select('menu_id','category_id')->orderBy('category_id','asc')->get()->load('menu','category');
        $menus = $menu->where('user_id', $user_id)->distinct()->select('id','category_id','content', 'oneSide')->orderBy('category_id','asc')->get()->load('category','recordContents');
        foreach($menus as $menu){
            $bestContents=[];
            if(count($menu->recordContents) === 0){
                $bestContents['menu']= $menu;
                $bestContents['category']= $menu->category;
                $bestContents['emptyData']= 1;
            }else{
                $bestContents['emptyData']= 0;
                if($menu->oneSide === 1){
                    $menuBestRightWeight = $menu->recordContents->sortByDesc('right_weight')->first()->right_weight;
                    $menuBestLeftWeight = $menu->recordContents->sortByDesc('left_weight')->first()->left_weight;
                    $bestContents['menuBestRightWeight'] = $menuBestRightWeight;
                    $bestContents['menuBestLeftWeight'] = $menuBestLeftWeight;
                    $menuBestRightVolume = $menu->recordContents->sortByDesc('right_volume')->first();
                    $menuBestLeftVolume = $menu->recordContents->sortByDesc('left_volume')->first();
                    $bestContents['menuBestRightVolume'] = $menuBestRightVolume;
                    $bestContents['menuBestLeftVolume'] = $menuBestLeftVolume;
                }else{
                    $menuBestWeight = $menu->recordContents->sortByDesc('weight')->first()->weight;
                    $bestContents['bestWeight'] = $menuBestWeight;
                    $menuBestVolume = $menu->recordContents->sortByDesc('volume')->first();
                    $bestContents['menuBestVolume'] = $menuBestVolume;
                }
                $bestContents['menu']= $menu;
                $bestContents['category']= $menu->category;
            }
            $dispContents[] = $bestContents;
        }
        return response()->json(["status_code" => 200, "message" => "MAX記録を取得しました。", "dispContents"=>$dispContents ]);
    }

    // 全ユーザーのBIG3、MAXランキングTOP3
    public function show(RankingRecord $rankingRecord){
        $rankingRecord = $rankingRecord->get();
        if(isset($rankingRecord)){
            return response()->json(["status_code" => 200, "message" => "ランキングを表示します。", "rankingRecord"=>$rankingRecord]);
        }else{
            return response()->json(["status_code" => 200, "message" => "表示内容がありません。"]);
        }
    }
}
