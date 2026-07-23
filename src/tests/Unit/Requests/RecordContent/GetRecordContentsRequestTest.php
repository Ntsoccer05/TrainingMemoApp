<?php

namespace Tests\Unit\Requests\RecordContent;

use App\Http\Requests\RecordContent\GetRecordContentsRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class GetRecordContentsRequestTest extends TestCase
{
    private function rules(): array
    {
        return (new GetRecordContentsRequest())->rules();
    }

    public function test_passes_when_both_from_and_to_are_omitted()
    {
        $validator = Validator::make([], $this->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_passes_when_both_from_and_to_are_valid()
    {
        $validator = Validator::make(['from' => '2026-01-01', 'to' => '2026-01-31'], $this->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_fails_when_only_from_is_present()
    {
        $validator = Validator::make(['from' => '2026-01-01'], $this->rules());

        $this->assertTrue($validator->fails());
    }

    public function test_fails_when_only_to_is_present()
    {
        $validator = Validator::make(['to' => '2026-01-31'], $this->rules());

        $this->assertTrue($validator->fails());
    }

    public function test_fails_when_to_is_before_from()
    {
        $validator = Validator::make(['from' => '2026-02-01', 'to' => '2026-01-01'], $this->rules());

        $this->assertTrue($validator->fails());
    }

    public function test_fails_when_date_format_is_invalid()
    {
        $validator = Validator::make(['from' => '2026/01/01', 'to' => '2026/01/31'], $this->rules());

        $this->assertTrue($validator->fails());
    }

    public function test_resolved_from_defaults_to_two_months_before_start_of_month_when_omitted()
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 23));
        $request = new GetRecordContentsRequest();

        $this->assertTrue($request->resolvedFrom()->isSameDay(Carbon::create(2026, 5, 1)));

        Carbon::setTestNow();
    }

    public function test_resolved_to_defaults_to_today_when_omitted()
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 23));
        $request = new GetRecordContentsRequest();

        $this->assertTrue($request->resolvedTo()->isSameDay(Carbon::create(2026, 7, 23)));

        Carbon::setTestNow();
    }

    public function test_resolved_from_uses_given_from_when_present()
    {
        $request = new GetRecordContentsRequest();
        $request->merge(['from' => '2026-01-15', 'to' => '2026-01-20']);

        $this->assertTrue($request->resolvedFrom()->isSameDay(Carbon::create(2026, 1, 15)));
        $this->assertTrue($request->resolvedTo()->isSameDay(Carbon::create(2026, 1, 20)));
    }

    public function test_resolved_from_correctly_handles_month_overflow_on_day_31()
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 31));
        $request = new GetRecordContentsRequest();

        $this->assertTrue($request->resolvedFrom()->isSameDay(Carbon::create(2025, 11, 1)));

        Carbon::setTestNow();
    }
}
