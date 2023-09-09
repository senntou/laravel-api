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
        DB::table('room')->insert([
            'name' => 'ROOM_ONE',
            'user1' => 'bocchi'
        ]);
        DB::table('room')->insert([
            'name' => '部屋2',
            'password' => 'watarukun_kakko11',
            'user1' => 'tanaka',
            'user2' => '小笠原'
        ]);
        DB::table('room')->insert([
            'name' => 'ルーム３',
            'password' => '2525',
            'user1' => 'ユニコーン',
            'user2'=> 'nitech'
        ]);
    }
}
