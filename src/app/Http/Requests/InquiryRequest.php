<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class InquiryRequest extends FormRequest
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
            'name' => 'nullable',
            'email' => 'required|email:strict,dns,spoof',
            'content' => 'required',
        ];
    }

    public function messages()
    {
        // :attributeはemailはメールアドレス、contentはお問い合わせ内容となる
        return [
            'email.required' => ':attributeは必ず入力してください。',
            'email.email' => '正しい:attributeを入力してください。',
            'content.required' => ':attributeは必ず入力してください。',
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'お名前',
            'email' => 'メールアドレス',
            'content' => 'お問い合わせ内容'
        ];
    }

    // 関数名はリクエスト毎でユニークにする
    protected function failedResetPasswordValidation(Validator $validator) {
        $response['status_code']  = 401;
        $response['statusText'] = 'Failed validation.';
        $response['errors']  = $validator->errors()->toArray();

        throw new HttpResponseException(
            response()->json( $response, 401 )
        );
    }
}