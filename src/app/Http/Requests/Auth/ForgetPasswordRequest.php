<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class ForgetPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
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
            'email' => 'required|email|exists:users',
        ];
    }

    public function messages()
    {
        return [
            'email.required' => 'メールアドレスは必ず指定してください。',
            'email.email' => '正しいメールアドレスを指定してください。',
            'email.exists' => '入力された:attributeは登録されていません。',
        ];
    }

    public function attributes()
    {
        return [
            'email' => 'メールアドレス',
        ];
    }

    // 関数名はリクエスト毎でユニークにする
    protected function failedForgetPasswordValidation(Validator $validator) {
        $response['status_code']  = 401;
        $response['statusText'] = 'Failed validation.';
        $response['errors']  = $validator->errors()->toArray();

        throw new HttpResponseException(
            response()->json( $response, 401 )
        );
    }
}
