<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

use App\Models\Transaction;
use App\Models\Shipment;
use App\Models\Invoices;
use App\Models\Merchant;
use App\Rules\Phone;
use Illuminate\Support\Facades\App;

class MerchantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $path = Request()->route()->uri;
        if ($this->getMethod() == 'GET' && strpos($path, 'transactions/{id}') !== false)
            return Transaction::where('id', Request::instance()->id)->exists();
        else if ($this->getMethod() == 'GET' && strpos($path, 'shipments/{id}') !== false)
            return Shipment::where('id', Request::instance()->id)->exists();
        else if (
            ($this->getMethod() == 'DELETE' && strpos($path, 'invoice/{invoice_id}') !== false) ||
            ($this->getMethod() == 'GET' && strpos($path, 'invoice/finalize/{invoice_id}') !== false)
        )
            return Invoices::where('id', Request::instance()->invoice_id)->exists();
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {

        $path = Request()->path();
        if (strpos($path, 'merchant/user/update-password') !== false) {
            return [
                'current' => 'required',
                'new' => 'required|min:6|max:255',
            ];
        } else if (strpos($path, 'merchant/user/update-profile') !== false) {
            return [
                'name' => 'required|min:6|max:255',
                'email' => 'required|email|unique:users,email,' . Auth::id(),
                'phone' => 'required|unique:users,phone,' . Auth::id() . '|required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:14',
            ];
        } else if (strpos($path, 'merchant/update-info') !== false) {
            return [
                'type' => 'required|in:individual,corporate',
                'name' => 'required|min:6|max:255',
                'email' => 'required|unique:merchants,email,' . Request()->user()->merchant_id,
                'phone' =>
                [
                    'required',
                    'unique:merchants,phone,' . Request()->user()->merchant_id,
                    'regex:/^([0-9\s\-\+\(\)]*)$/',
                    'min:10',
                    'max:14',
                    new Phone(App::make('merchantInfo')->country_code)

                ]
            ];
        } else if (strpos($path, 'merchant/verify/phone') !== false) {
            return [
                'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:14'
            ];
        }
        return [];
    }
}
