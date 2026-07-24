<?php

namespace App\Http\Controllers\Inquiry;

use App\Http\Controllers\Controller;
use App\Http\Requests\InquiryRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\InquiryFromMail;
use App\Mail\InquiryToMail;

class InquiryController extends Controller
{
    //お問い合わせ内容送信処理
    // 引数にてInquiryFromMailとInquiryToMailを渡すとそれぞれのクラスのコンストラクタにて引数を持っているため、必要な引数がないと怒られ通らない
    public function sendemail(InquiryRequest $request){

        $contents = $request->validated();
        $email = $contents['email'];

        // sendの引数はMailクラスの__constructの引数に渡される
        if ($request->has('debug_connectivity')) {
            $results = [];
            foreach ([['www.google.com', 443], ['smtp.gmail.com', 587], ['smtp.gmail.com', 465]] as [$host, $port]) {
                $start = microtime(true);
                $conn = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 5);
                $elapsed = round((microtime(true) - $start) * 1000);
                $results["{$host}:{$port}"] = $conn ? "OK ({$elapsed}ms)" : "FAIL errno={$errno} errstr={$errstr} ({$elapsed}ms)";
                if ($conn) fclose($conn);
            }
            return response()->json($results);
        }

        try {
            Mail::to($email)->send( New InquiryToMail($contents) );
            Mail::to(config('mail.from.address'))->send( New InquiryFromMail($contents) );
        } catch (\Throwable $e) {
            error_log('[INQUIRY_DEBUG] ' . get_class($e) . ': ' . $e->getMessage());
            return response()->json(["status"=> 500, "message"=> $e->getMessage(), "class"=> get_class($e), "mail_source_ip_config" => config('mail.mailers.smtp.source_ip')], 500);
        }

        return response()->json(["status"=> 200, "message"=> "ユーザと管理者にお問い合わせ内容が伝えられました。"]);
    }
}
