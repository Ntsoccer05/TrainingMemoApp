<?php

namespace App\Http\Requests\RecordContent;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class GetRecordContentsRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * fromとtoは両方指定するか、両方省略するかのどちらか。
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'from' => 'nullable|date_format:Y-m-d|required_with:to',
            'to' => 'nullable|date_format:Y-m-d|required_with:from|after_or_equal:from',
        ];
    }

    public function messages()
    {
        return [
            'from.required_with' => 'fromとtoは両方指定するか、両方省略してください。',
            'to.required_with' => 'fromとtoは両方指定するか、両方省略してください。',
            'from.date_format' => 'fromはYYYY-MM-DD形式で指定してください。',
            'to.date_format' => 'toはYYYY-MM-DD形式で指定してください。',
            'to.after_or_equal' => 'toはfrom以降の日付を指定してください。',
        ];
    }

    /**
     * 絞り込み開始日を返す。省略時は「当月を含む直近3ヶ月」の月初とする。
     *
     * @return Carbon
     */
    public function resolvedFrom(): Carbon
    {
        if ($this->filled('from')) {
            return Carbon::createFromFormat('Y-m-d', $this->input('from'))->startOfDay();
        }

        return Carbon::now()->subMonthsNoOverflow(2)->startOfMonth();
    }

    /**
     * 絞り込み終了日を返す。省略時は今日とする。
     *
     * @return Carbon
     */
    public function resolvedTo(): Carbon
    {
        if ($this->filled('to')) {
            return Carbon::createFromFormat('Y-m-d', $this->input('to'))->endOfDay();
        }

        return Carbon::now()->endOfDay();
    }
}
