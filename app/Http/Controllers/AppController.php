<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppController extends Controller
{
    public function index() {
        $rooms = DB::table('room')->get();
        return response()->json(
            $rooms
        );
    }
}
