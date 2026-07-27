<?php

namespace Tests\Unit\Requests\Weight;

use App\Http\Requests\Weight\GetWeightHistoryRequest;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class GetWeightHistoryRequestTest extends TestCase
{
    public function test_resolved_from_defaults_to_start_of_month_two_months_ago(): void
    {
        Carbon::setTestNow('2026-07-27');
        $request = new GetWeightHistoryRequest();

        $result = $request->resolvedFrom();

        $this->assertEquals('2026-05-01', $result->toDateString());
        Carbon::setTestNow();
    }

    public function test_resolved_from_uses_provided_from_value(): void
    {
        $request = new GetWeightHistoryRequest();
        $request->merge(['from' => '2026-01-01']);

        $result = $request->resolvedFrom();

        $this->assertEquals('2026-01-01', $result->toDateString());
    }

    public function test_resolved_to_defaults_to_today(): void
    {
        Carbon::setTestNow('2026-07-27 15:00:00');
        $request = new GetWeightHistoryRequest();

        $result = $request->resolvedTo();

        $this->assertEquals('2026-07-27', $result->toDateString());
        Carbon::setTestNow();
    }
}
