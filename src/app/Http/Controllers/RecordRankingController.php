<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\RankingRecord;
use App\Models\RecordContent;
use App\Models\RecordMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecordRankingController extends Controller
{
    // 個人のメニュー別MAX表示
    public function index(Request $request, Menu $menu,RecordMenu $recordMenu, RecordContent $recordContent){
        $user_id = $request->user_id;

        $dispContents=[];

        $menus = $menu->where('user_id', $user_id)->distinct()->select('id','category_id','content', 'oneSide')->orderBy('category_id','asc')->get()->load('category');

        // メニューごとの最大重量(スカラー値)はDB側のMAX集計で取得する(全件をPHPへ転送しない)。
        // 1クエリでweight/right_weight/left_weightを同時にMAX集計すると、
        // (user_id, menu_id, 対象カラム)の複合インデックスが1つしか活かせず一時テーブルを伴う集計になるため、
        // カラムごとに専用インデックスを使う単純なGROUP BYクエリへ分割している。
        $maxWeightsByMenu = [];
        foreach([
            'weight' => 'max_weight',
            'right_weight' => 'max_right_weight',
            'left_weight' => 'max_left_weight',
        ] as $column => $alias){
            $rows = $recordContent->where('user_id', $user_id)
                ->selectRaw("menu_id, MAX({$column}) as {$alias}")
                ->groupBy('menu_id')
                ->get();
            foreach($rows as $row){
                $maxWeightsByMenu[$row->menu_id][$alias] = $row->{$alias};
            }
        }

        // volume/right_volume/left_volumeが最大となるレコード(argmax)をDB側で求める。
        // MySQLのウィンドウ関数(ROW_NUMBER)はインデックスの順序を活かせずフルソートになってしまうため、
        // 「GROUP BYでの最大値算出」→「その最大値を持つ行をJOINで引く」という古典的な方法に変更している。
        // (user_id, menu_id, 対象カラム)の複合インデックスと組み合わせることで、
        // 対象ユーザーの行数に比例したコストで済み、テーブル全体のスキャンを避けられる。
        $bestVolumeByMenu = [];
        foreach(['volume', 'right_volume', 'left_volume'] as $column){
            // STRAIGHT_JOINで結合順序を固定する。
            // 付けない場合、オプティマイザがrc側(対象ユーザーの全行)を先に読んでしまう実行計画を選ぶことがあり、
            // その場合テーブル全体スキャンに近いコストになってしまう(EXPLAINで確認済み)。
            // best(メニュー数件のみ)を先に評価させることで、対象ユーザーの行数に関わらず高速なインデックスルックアップになる。
            $rows = DB::select("
                SELECT STRAIGHT_JOIN rc.* FROM (
                    SELECT menu_id, MAX({$column}) as best_value
                    FROM record_contents
                    WHERE user_id = ?
                    GROUP BY menu_id
                ) best
                INNER JOIN record_contents rc ON best.menu_id = rc.menu_id AND best.best_value = rc.{$column}
                WHERE rc.user_id = ?
            ", [$user_id, $user_id]);

            foreach($rows as $row){
                // 同点(タイ)の場合は最初に見つかったものを採用する
                if(isset($bestVolumeByMenu[$row->menu_id][$column])){
                    continue;
                }
                // fillable制限を経由せず生の属性をそのまま復元する(newInstance()はfillableでガードされ列が欠落するため)
                $bestVolumeByMenu[$row->menu_id][$column] = $recordContent->newFromBuilder((array) $row);
            }
        }

        foreach($menus as $menu){
            $bestContents=[];
            $maxWeights = $maxWeightsByMenu[$menu->id] ?? null;
            if(is_null($maxWeights)){
                $bestContents['menu']= $menu;
                $bestContents['category']= $menu->category;
                $bestContents['emptyData']= 1;
            }else{
                $bestContents['emptyData']= 0;
                $bestVolume = $bestVolumeByMenu[$menu->id] ?? [];
                if($menu->oneSide === 1){
                    $bestContents['menuBestRightWeight'] = $maxWeights['max_right_weight'] ?? null;
                    $bestContents['menuBestLeftWeight'] = $maxWeights['max_left_weight'] ?? null;
                    $bestContents['menuBestRightVolume'] = $bestVolume['right_volume'] ?? null;
                    $bestContents['menuBestLeftVolume'] = $bestVolume['left_volume'] ?? null;
                }else{
                    $bestContents['bestWeight'] = $maxWeights['max_weight'] ?? null;
                    $bestContents['menuBestVolume'] = $bestVolume['volume'] ?? null;
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
