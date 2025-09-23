<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Attendance::class;

    public function definition(): array
    {
        $date = Carbon::today('Asia/Tokyo');

        return [
            'user_id'   => User::factory(),
            'work_date'      => $date->toDateString(),     // '2025-09-22'
            'clock_in_at'  => $date->copy()->setTime(9, 0)->toDateTimeString(),  // 09:00
            'clock_out_at' => null,                      // 出勤中なので null
        ];
    }

    /**
     * 出勤中（clock_inあり / clock_outなし）
     */
    public function working(): self
    {
        return $this->state(fn () => [
            'clock_in_at'  => Carbon::today('Asia/Tokyo')->setTime(9, 0)->toDateTimeString(),
            'clock_out_at' => null,
        ]);
    }

    /**
     * 退勤済（clock_inあり / clock_outあり）
     */
    public function after(): self
    {
        return $this->state(fn () => [
            'clock_in_at'  => Carbon::today('Asia/Tokyo')->setTime(9, 0)->toDateTimeString(),
            'clock_out_at' => Carbon::today('Asia/Tokyo')->setTime(18, 0)->toDateTimeString(),
        ]);
    }
}
