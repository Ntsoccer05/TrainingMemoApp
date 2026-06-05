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
        Mail::to($email)->send( New InquiryToMail($contents) );
        Mail::to(config('mail.from.address'))->send( New InquiryFromMail($contents) );
        
        return response()->json(["status"=> 200, "message"=> "ユーザと管理者にお問い合わせ内容が伝えられました。"]);
    }
}
