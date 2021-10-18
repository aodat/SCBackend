<?php 
namespace App\Http\Repositories\User;

interface IUserRepo
{
    public function login($email,$password);

    public function register($data);

    public function logout($data);

    public function profile();
}
