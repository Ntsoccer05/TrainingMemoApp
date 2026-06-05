<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authrized()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'nullable|exists:users',
            'email' => 'required|email|exists:users',
            'password' => 'nullable'
        ];
    }

    public function messages()
    {
        return [
            'name.exists' => '入力された:attributeは登録されていません。',
            'email.required' => ':attributeは必須項目です。',
            'email.email' => '正しいメールアドレスを指定してください。',
            'email.exists' => '入力された:attributeは登録されていません。',
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'ユーザ名',
            'email' => 'メールアドレス',
            'password' => 'パスワード'
        ];
    }

    // 関数名はリクエスト毎でユニークにする
    protected function failedLoginValidation(Validator $validator) {
        $response['status_code']  = 401;
        $response['statusText'] = 'Failed validation.';
        $response['errors']  = $validator->errors()->toArray();

        throw new HttpResponseException(
            response()->json( $response, 401 )
        );
    }
}
