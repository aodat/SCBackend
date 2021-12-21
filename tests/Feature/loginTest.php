<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class loginTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_valiation_error_login()
    {
        $response = $this->post('api/auth/login', [

            "email" => "adminw@email.com",
            "password" => "password"
        ]);
        $response->assertOk();
        $response->assertStatus(200)->assertJson([
            "meta" => [
                "code" => 400,
            ]
        ]);

    }



    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_Successfully_login()
    {
        $response = $this->post('api/auth/login', [

            "email" => "tareq.fw@shipcash.net",
            "password" => "tareqfw"
        ]);
        $response->assertOk();
        $response->assertStatus(200)->assertJson([
            "meta" => [
                "code" => 200,
            ]
        ]);

    }
}
