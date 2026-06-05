<?php

namespace App\Console\Commands;

use App\Models\Menu;
use App\Models\RankingRecord;
use App\Models\RecordContent;
use App\Models\RecordMenu;
use App\Models\User;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RecordRanking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:updateRanking';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(Menu $menu, RankingRecord $rankingRecord, User $user, RecordMenu $recordMenu ,RecordContent $recordContent)
    {
        //データを空にする。
        $rankingRecord->truncate();

        $dataUnit = 3;

        $TopWeightBench = [];
        $TopVolumeBench = [];
        $TopWeightSquat = [];
        $TopVolumeSquat = [];
        $TopWeightDeadlift = [];
        $TopVolumeDeadlift = [];
        $TopWeightBenchUsers = [];
        $TopVolumeBenchUsers = [];
        $TopWeightSquatUsers = [];
        $TopVolumeSquatUsers = [];
        $TopWeightDeadliftUsers = [];
        $TopVolumeDeadliftUsers = [];

        $now = new DateTime();
        $lastweekDay = $now->modify('-1 week')->format('Y-m-d');;

        //親テーブルがあると子供のデータ取得時に呼び出した親のデータも一緒に取得できる。
        //load()によってN+1問題対応 load()の引数はModels/RecordState.phpの関数名
        $benchMenuRecords = $menu->where('content', 'ベンチプレス')->get()->load([("recordMenus")=>function($query) use($lastweekDay){
            $query->where('recorded_at','>=' ,$lastweekDay);
        }]);
        $squatMenuRecords = $menu->where('content', 'スクワット')->get()->load(["recordMenus"=>function($query) use($lastweekDay){
            $query->where('recorded_at','>=' ,$lastweekDay);
        }]);
        $deadliftMenuRecords = $menu->where('content', 'デッドリフト')->get()->load(["recordMenus"=>function($query) use($lastweekDay){
            $query->where('recorded_at','>=' ,$lastweekDay);
        }]);
        // $squatMenuRecords = $squatMenus->recordMenus->where('recorded_at','>=' ,$lastweekDay)->get();
        // $deadliftMenuRecords = $deadliftMenus->recordMenus->where('recorded_at','>=' ,$lastweekDay)->get();
        if(!empty($benchMenuRecords[0]->recordMenus)){
            foreach($benchMenuRecords[0]->recordMenus as $record) {
                $TopWeightBench[] = $recordContent->where('record_menu_id', $record->id)->first();
            };
            usort($TopWeightBench, function($f1, $f2) {
                return $f2['weight'] - $f1['weight'];
            });
            // $sortTopWeightBench = array_column($TopWeightBench, 'weight');
            // array_multisort($sortTopWeightBench, SORT_DESC, $TopWeightBench, SORT_NUMERIC);
            dd($TopWeightBench);
            $TopWeightBench = $benchMenuRecords[0]->recordMenus->sortbyDesc('weight');
            $TopVolumeBench = $benchMenuRecords[0]->recordMenus->sortbyDesc('volume');
            $recordTopWeightBench = array_slice($TopWeightBench->toArray(), 0, $dataUnit);
            $recordTopVolumeBench = array_slice($TopVolumeBench->toArray(), 0, $dataUnit);
            foreach($recordTopWeightBench as $record) {
                $TopWeightBenchUsers[] = $user->where('id', $record["user_id"])->first();
            };
            foreach($recordTopVolumeBench as $record) {
                $TopVolumeBenchUsers[] = $user->where('id', $record["user_id"])->first();
            };
            // $TopWeightBenchUsers = $TopWeightBench->toArray();
            // $TopVolumeBenchUsers = $TopVolumeBench->toArray();
            $rankingRecord->bench_weight = $recordTopWeightBench;
            $rankingRecord->bench_volume = $recordTopVolumeBench;
            $rankingRecord->bench_weight_users = json_encode($TopWeightBenchUsers);
            $rankingRecord->bench_volume_users = json_encode($TopVolumeBenchUsers);
        }
        if(!empty($squatMenuRecords[0]->recordMenus)){
            $TopWeightSquat = $squatMenuRecords[0]->recordMenus->sortbyDesc('weight');
            $TopVolumeSquat = $squatMenuRecords[0]->recordMenus->sortbyDesc('volume');
            $recordTopWeightSquat = array_slice($TopWeightSquat->toArray(), 0, $dataUnit);
            $recordTopVolumeSquat = array_slice($TopVolumeSquat->toArray(), 0, $dataUnit);
            foreach($recordTopWeightSquat as $record) {
                $TopWeightSquatUsers[] = $user->where('id', $record["user_id"])->first();
            };
            foreach($recordTopVolumeSquat as $record) {
                $TopVolumeSquatUsers[] = $user->where('id', $record["user_id"])->first();
            };
            $rankingRecord->squat_weight = $recordTopWeightSquat;
            $rankingRecord->squat_volume = $recordTopVolumeSquat;
            $rankingRecord->squat_weight_users = json_encode($TopWeightSquatUsers);
            $rankingRecord->squat_volume_users = json_encode($TopVolumeSquatUsers);
        }
        if(!empty($deadliftMenuRecords[0]->recordMenus)){
            $TopWeightDeadlift = $deadliftMenuRecords[0]->recordMenus->sortbyDesc('weight');
            $TopVolumeDeadlift = $deadliftMenuRecords[0]->recordMenus->sortbyDesc('volume');
            $recordTopWeightDeadlift = array_slice($TopWeightDeadlift->toArray(), 0, $dataUnit);
            $recordTopVolumeDeadlift = array_slice($TopVolumeDeadlift->toArray(), 0, $dataUnit);
            foreach($recordTopWeightDeadlift as $record) {
                $TopWeightDeadliftUsers[] = $user->where('id', $record["user_id"])->first();
            };
            foreach($recordTopVolumeDeadlift as $record) {
                $TopVolumeDeadliftUsers[] = $user->where('id', $record["user_id"])->first();
            };
            $rankingRecord->deadlift_weight = $TopWeightDeadlift;
            $rankingRecord->deadlift_volume = $TopVolumeDeadlift;
            $rankingRecord->deadlift_weight_users = json_encode($TopWeightDeadliftUsers);
            $rankingRecord->deadlift_volume_users = json_encode($TopVolumeDeadliftUsers);
        }
        $rankingRecord->save();

        return Command::SUCCESS;
    }
}
