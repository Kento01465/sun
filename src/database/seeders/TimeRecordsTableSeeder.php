<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TimeRecordsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('time_records')->insert([
            'user_id' => 1,
            'clock_in' => Carbon::now()->subHours(9),
            'clock_out' => Carbon::now(),
            'break_duration' => 60,
            'notes' => '初期データ',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }
}
