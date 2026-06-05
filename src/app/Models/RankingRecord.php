<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RankingRecord extends Model
{
    use HasFactory;

    //配列でデータを格納するものを定義
    protected $casts = [
        'bench_weight' => 'array',
        'squat_weight' => 'array',
        'deadlift_weight' => 'array',
        'bench_volume' => 'array',
        'squat_volume' => 'array',
        'deadlift_volume' => 'array'
    ];
}
