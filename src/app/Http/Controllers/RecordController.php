<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RecordState;
use Carbon\Carbon;
use DateTime;

class RecordController extends Controller
{
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(\App\Services\Record\RecordService $recordService){
        $latestRecord = $recordService->getLatestRecordState(auth()->id());

        return response()->json(["status_code" => 200, "latestRecord" => $latestRecord]);
    }

    public function create(Request $request)
    {
        $recorded_at = Carbon::parse($request->recording_day);
        $recording_day =$recorded_at->toDateString();
        $hasRecord = RecordState::where('user_id', $request->user_id)->whereDate('recorded_at', $recording_day)->first();
        
        if(isset($hasRecord)){
            $now = Carbon::now();
            $hasRecord->updated_at = $now;
            $hasRecord->save();
            return response()->json(["status_code" => 200,"message" => "トレーニング日を更新しました", "hasrecord" => $hasRecord,"recorded_at"=>$recorded_at, "request"=>$request->recording_day,"record" => $recording_day]);
        }else{
            RecordState::create([
                'user_id' => $request->user_id,
                'recorded_at' => $recording_day
            ]);
            return response()->json(["status_code" => 200, "message" => "トレーニング日を記録しました", "session" => $request->session()->all()]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, RecordState $recordState)
    {
        $recorded_at = Carbon::parse($request->recording_day);
        $recording_day =$recorded_at->toDateString();
        $hasRecord = $recordState->where('user_id', $request->user_id)->whereDate('recorded_at', $recording_day)->first();
        if(isset($hasRecord)){
            $now = Carbon::now();
            $hasRecord->bodyWeight = $request->weight;
            $hasRecord->updated_at = $now;
            $hasRecord->save();
            return response()->json(["status_code" => 200,"recorded_at"=>$recorded_at, "request"=>$request->recording_day,"record" => $recording_day]);
        }else{
            return response()->json(["status_code" => 500, "message" => "記録日が存在しません。"]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, RecordState $recordState)
    {
        //
        $userId = $request->user_id;
        $recorded_at = $request->recorded_at;
        $TgtRecordState=$recordState->where('recorded_at', $recorded_at)->where('user_id', $userId)->first();
        if($TgtRecordState){
            $TgtRecordState->delete();
            return response()->json(["status"=>200,"message"=> "レコードを削除しました。"]);
        }else{
            return response()->json(["status"=>200,"message"=> "削除データは存在しませんでした。"]);
        }
    }
}
