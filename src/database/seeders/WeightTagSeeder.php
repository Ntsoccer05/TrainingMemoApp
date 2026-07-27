<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WeightTag;
use Illuminate\Database\Seeder;

class WeightTagSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@gmail.com')->first();

        if (! $user) {
            return;
        }

        $tags = ['食べすぎ', '飲みすぎ', '体調不良', '運動'];

        foreach ($tags as $tag) {
            WeightTag::firstOrCreate([
                'user_id' => $user->id,
                'content' => $tag,
            ]);
        }
    }
}
