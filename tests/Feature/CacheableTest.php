<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CacheableTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    public function test_the_database_gets_seeded()
    {
        $this->assertDatabaseCount('users', 10);
    }

    /**
     * @return void
     */
    public function test_cacheable_is_working()
    {
        $user = User::query()->first();
        $username = $user->name;
        $this->assertIsString($username);

        $user->name = 'Testing';
        $userFresh = User::query()->first();
        $this->assertEquals('Testing', $user->name);
        $this->assertNotEquals($username, $user->name);
        $this->assertNotEquals($userFresh->name, $user->name);

        $this->assertTrue($user->save());
        $userFresh = User::query()->first();
        $this->assertNotEquals($username, $userFresh->name);
        $this->assertEquals('Testing', $userFresh->name);
    }

    /**
     * @return void
     */
    public function test_cacheable_within_subquery_is_working()
    {
        $user = User::query()->whereIn('id', User::query()->select('id'))->first();
        $username = $user->name;
        $this->assertIsString($username);

        $user->name = 'Testing';
        $userFresh = User::query()->first();
        $this->assertEquals('Testing', $user->name);
        $this->assertNotEquals($username, $user->name);
        $this->assertNotEquals($userFresh->name, $user->name);

        $this->assertTrue($user->save());
        $userFresh = User::query()->first();
        $this->assertNotEquals($username, $userFresh->name);
        $this->assertEquals('Testing', $userFresh->name);
    }

    /**
     * @return void
     */
    public function test_without_cache_is_working()
    {
        $user = User::query()->first();
        $username = $user->name;
        $this->assertIsString($username);

        DB::table('users')->where('id', $user->id)->update([
            'name' => 'Testing'
        ]);

        $userFresh = User::query()->first();
        $this->assertEquals($username, $userFresh->name);
        $this->assertNotEquals('Testing', $userFresh->name);

        $userWithoutCache = User::query()->withoutCache()->first();
        $this->assertNotEquals($username, $userWithoutCache->name);
        $this->assertEquals('Testing', $userWithoutCache->name);
    }

    /**
     * @return void
     */
    public function test_flush_is_working()
    {
        $user = User::query()->first();
        $username = $user->name;
        $this->assertIsString($username);

        DB::table('users')->where('id', $user->id)->update([
            'name' => 'Testing'
        ]);

        $userFresh = User::query()->first();
        $this->assertEquals($username, $userFresh->name);
        $this->assertNotEquals('Testing', $userFresh->name);

        User::query()->flushCache();

        $userFresh = User::query()->first();
        $this->assertNotEquals($username, $userFresh->name);
        $this->assertEquals('Testing', $userFresh->name);
    }
}
