<?php

namespace App\Http\Requests\Menus;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class MenuRequest extends FormRequest
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
            'user_id' => 'exists:users',
            'category_content' => 'nullable|max:30',
            'menu' => 'nullable|max:50',
        ];
    }

    public function messages()
    {
        return [
            'user_id.exists' => ':attributeは登録されていません。',
            'category_content.max' => ':attributeは３０文字以内で入力してください。',
            'menu.max' => ':attributeは５０文字以内で入力してください。',
        ];
    }

    public function attributes()
    {
        return [
            'user_id' => 'ユーザ',
            'category_content' => '部位',
            'menu' => '種目'
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
