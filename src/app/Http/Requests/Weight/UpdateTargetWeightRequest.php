<?php

namespace App\Http\Requests\Weight;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTargetWeightRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'target_weight' => 'required|numeric|min:0|max:999.9',
        ];
    }
}
