<?php

namespace App\Http\Requests\Transaction;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Request;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $path = Request()->route()->uri;
        if($this->getMethod() == 'GET' && strpos($path, 'transactions/{id}') !== false)
            return Transaction::where('id', Request::instance()->id)->where('merchant_id', Request()->user()->merchant_id)->exists();
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $path = Request()->route()->uri;
        if (
            ($this->getMethod() == 'PUT' && strpos($path, 'transactions/withdraw') !== false)
        )
            return [
                'amount' => 'required',
                'payment_method_id' => 'required'
            ];
        return [];
    }
}