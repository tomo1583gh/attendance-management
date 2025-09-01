<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = \App\Models\User::where('email', 'user@example.com')->first();
        foreach (range(0, 10) as $i) {
            $date = now()->subDays($i)->toDateString();
            $a = \App\Models\Attendance::updateOrCreate(
                ['user_id' => $user->id, 'work_date' => $date],
                [
                    'clock_in_at'  => now()->subDays($i)->setTime(9, 0),
                    'clock_out_at' => now()->subDays($i)->setTime(18, 0),
                    'note' => 'ダミー',
                ]
            );
            \App\Models\BreakTime::create([
                'attendance_id' => $a->id,
                'start_at' => now()->subDays($i)->setTime(12, 0),
                'end_at'  => now()->subDays($i)->setTime(13, 0),
            ]);
        }
    }
}
