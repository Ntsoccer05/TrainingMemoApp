<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use App\Http\Requests\Auth\RegisterRequest;

class RegisterController extends Controller
{

    //ユーザ登録処理
    public function register(RegisterRequest $request)
    {
        $user = User::Create([
            'name' => $request['name'],
            'email' => $request['email'],
            'password' => Hash::make($request['password'])
        ]);

        if(!is_null($user)) {
            Auth::guard()->login($user, true);
            return response()->json(["status_code" => 200, "message" => "登録しました", "user" => $user]);
        }
        else{
            return response()->json(["status_code" => 500, "message" => "登録失敗しました"]);
        }
    }

    //Googleユーザ登録処理
    public function registerProviderUser(RegisterRequest $request, string $provider)
    {
        $request->validate([
            'name' => ['nullable', 'string', 'unique:users'],
            'token' => ['required', 'string']
        ]);

        $token = $request->token;

        $providerUser = Socialite::driver($provider)->userFromToken($token);

        $user = User::create([
            'name' => $request->name,
            'email' => $providerUser->getEmail(),
            'password' => null,
        ]);

        Auth::guard()->login($user, true);

        return response()->json(["status_code" => 200, "message" => "登録しました", "user" => $user]);
    }
}
