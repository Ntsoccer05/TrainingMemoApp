<?php

namespace App\Http\Requests\Weight;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWeightRecordRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'recorded_at' => 'required|date_format:Y-m-d',
            'body_weight' => 'nullable|numeric|min:0|max:999.9',
            'memo' => 'nullable|string|max:2000',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => [
                'integer',
                Rule::exists('weight_tags', 'id')->where('user_id', auth()->id()),
            ],
        ];
    }
}
