<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Request;

class TransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $path = Request()->route()->uri;
        if (($this->getMethod() == 'PUT' && strpos($path, 'withdraw') !== false)) {
            return [
                'id' => 'required|exists:transactions,id,type,CASHOUT,subtype,COD',
            ];
        }
        return [];
    }
}
