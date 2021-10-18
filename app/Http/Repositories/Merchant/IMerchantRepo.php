<?php 
namespace App\Http\Repositories\Merchant;

interface IMerchantRepo
{
    public function create($data);

    public function update($update , $where);
}
