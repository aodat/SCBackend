<?php 
namespace App\Http\Repositories\Merchant;

use App\Models\Merchant;

class DBMerchantRepo implements IMerchantRepo
{    
    public function create($data)
    {
        return Merchant::create($data);
    } 
}