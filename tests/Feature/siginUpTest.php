<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class siginUpTest extends TestCase
{

    public function test_valiation_error_signup()
    {
         
        $response = $this->post('api/auth/register', [

            "name"               => "Tareq Fawakhiri",
            "type"               => "individual",
            "phone"              => "+966721703725",
            "country_code"       => "KSA",
            "email"              => "tareq.fw@shipcash.net",
            "password"           => "tareqfw"
        ]);
        $response->assertOk();
        $response->assertStatus(200)->assertJson([
            "meta" => [
                "code" => 400,
                "msg" => "Valiation error"
            ]
        ]);

        $response->assertSessionHasNoErrors(["error"]);

     
    }
}
