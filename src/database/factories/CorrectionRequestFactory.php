<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\CorrectionRequest;
use App\Models\User;
use App\Models\Attendance;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CorrectionRequest>
 */
class CorrectionRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    protected $model = CorrectionRequest::class;

    public function definition(): array
    {
        return [
            'user_id'       => User::factory(),
            'attendance_id' => Attendance::factory(),
            'status'        => 'pending',   // 'pending' / 'approved' 等、実装に合わせて
            'approved_at'   => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn() => [
            'status'      => 'pending',
            'approved_at' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn() => [
            'status'      => 'approved',
            'approved_at' => now(),
        ]);
    }
}
