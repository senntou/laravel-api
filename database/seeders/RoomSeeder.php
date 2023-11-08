<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for($i = 0;$i < 10;$i++){
            DB::table('rooms')->insert([
                'user1' => null
            ]);
        }
        // DB::table('rooms')->insert([
        //     'name' => '部屋2',
        //     'user1' => 'tanaka',
        //     'user2' => '小笠原'
        // ]);
    }
}
