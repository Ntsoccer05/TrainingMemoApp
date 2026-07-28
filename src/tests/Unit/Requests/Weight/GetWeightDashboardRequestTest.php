<?php

namespace Tests\Unit\Requests\Weight;

use App\Http\Requests\Weight\GetWeightDashboardRequest;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class GetWeightDashboardRequestTest extends TestCase
{
    public function test_resolved_from_defaults_to_start_of_month_two_months_ago(): void
    {
        Carbon::setTestNow('2026-07-27');
        $request = new GetWeightDashboardRequest();

        $result = $request->resolvedFrom();

        $this->assertEquals('2026-05-01', $result->toDateString());
        Carbon::setTestNow();
    }

    public function test_resolved_from_uses_provided_from_value(): void
    {
        $request = new GetWeightDashboardRequest();
        $request->merge(['from' => '2026-01-01']);

        $result = $request->resolvedFrom();

        $this->assertEquals('2026-01-01', $result->toDateString());
    }

    public function test_resolved_to_defaults_to_today(): void
    {
        Carbon::setTestNow('2026-07-27 15:00:00');
        $request = new GetWeightDashboardRequest();

        $result = $request->resolvedTo();

        $this->assertEquals('2026-07-27', $result->toDateString());
        Carbon::setTestNow();
    }

    public function test_resolved_selected_date_defaults_to_today(): void
    {
        Carbon::setTestNow('2026-07-27 15:00:00');
        $request = new GetWeightDashboardRequest();

        $result = $request->resolvedSelectedDate();

        $this->assertEquals('2026-07-27', $result->toDateString());
        Carbon::setTestNow();
    }

    public function test_resolved_selected_date_uses_provided_value(): void
    {
        $request = new GetWeightDashboardRequest();
        $request->merge(['selected_date' => '2026-01-15']);

        $result = $request->resolvedSelectedDate();

        $this->assertEquals('2026-01-15', $result->toDateString());
    }
}
