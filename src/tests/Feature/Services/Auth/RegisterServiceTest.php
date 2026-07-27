<?php

namespace Tests\Feature\Services\Auth;

use App\Models\User;
use App\Services\Auth\RegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_default_data_creates_five_categories(): void
    {
        $user = User::factory()->create();
        $service = new RegisterService();

        $service->setupDefaultData($user->id);

        $this->assertDatabaseHas('categories', ['user_id' => $user->id, 'content' => '胸']);
        $this->assertDatabaseHas('categories', ['user_id' => $user->id, 'content' => '背中']);
        $this->assertDatabaseHas('categories', ['user_id' => $user->id, 'content' => '足']);
        $this->assertDatabaseHas('categories', ['user_id' => $user->id, 'content' => '腕']);
        $this->assertDatabaseHas('categories', ['user_id' => $user->id, 'content' => '腹筋']);
        $this->assertEquals(5, \App\Models\Category::where('user_id', $user->id)->count());
    }

    public function test_setup_default_data_does_not_create_any_menus(): void
    {
        $user = User::factory()->create();
        $service = new RegisterService();

        $service->setupDefaultData($user->id);

        $this->assertEquals(0, \App\Models\Menu::where('user_id', $user->id)->count());
    }

    public function test_setup_default_data_creates_four_weight_tags(): void
    {
        $user = User::factory()->create();
        $service = new RegisterService();

        $service->setupDefaultData($user->id);

        $this->assertDatabaseHas('weight_tags', ['user_id' => $user->id, 'content' => '食べすぎ']);
        $this->assertDatabaseHas('weight_tags', ['user_id' => $user->id, 'content' => '飲みすぎ']);
        $this->assertDatabaseHas('weight_tags', ['user_id' => $user->id, 'content' => '体調不良']);
        $this->assertDatabaseHas('weight_tags', ['user_id' => $user->id, 'content' => '運動']);
        $this->assertEquals(4, \App\Models\WeightTag::where('user_id', $user->id)->count());
    }
}
