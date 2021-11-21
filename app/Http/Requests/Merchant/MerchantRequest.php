<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

use App\Models\Transaction;
use App\Models\Shipment;
use App\Models\Invoices;
use Carbon\Carbon;
use Illuminate\Support\Facades\Request;

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
            return Transaction::where('id', Request::instance()->id)->where('merchant_id', Request()->user()->merchant_id)->exists();
        else if ($this->getMethod() == 'GET' && strpos($path, 'shipments/{id}') !== false)
            return Shipment::where('id', Request::instance()->id)->where('merchant_id', Request()->user()->merchant_id)->exists();
        else if (
            ($this->getMethod() == 'DELETE' && strpos($path, 'invoice/{invoice_id}') !== false) ||
            ($this->getMethod() == 'GET' && strpos($path, 'invoice/finalize/{invoice_id}') !== false)
        )
            return Invoices::where('id', Request::instance()->invoice_id)->where('merchant_id', Request()->user()->merchant_id)->exists();
        return true;
    }
    public function all($keys = null)
    {
        $path = Request()->route()->uri;
        $data = parent::all($keys);
        if ($this->method() == 'POST' && strpos($path, 'merchant/dashboard') !== false) {
            if ($data['since_at'] !== null && $data['until'] !== null) {
                $data['since_at'] = date("Y-m-d H:i:s", strtotime($data['since_at']));
                $data['until'] = date("Y-m-d H:i:s", strtotime($data['until']));
            } else {
                $data['since_at'] = Carbon::now()->subDays(7);
                $data['until'] = Carbon::now();
            }
        }
        return $data;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $path = Request()->path();

        ;
        if (strpos($path, 'merchant/user/update-password') !== false) {
            return [
                'current' => 'required',
                'new' => 'required|min:6|max:255',
            ];
        } else if (strpos($path, 'merchant/user/update-profile') !== false) {
            return [
                'name' => 'required|min:6|max:255',
                'email' => 'required|email|unique:users,email,' . Auth::id(),
                'phone' => 'required|unique:users,phone,' . Auth::id()
            ];
        } else if (strpos($path, 'merchant/update-info') !== false) {
            return [
                'type' => 'required|in:individual,corporate',
                'name' => 'required|min:6|max:255',
                'email' => 'required|unique:merchants,email,' . Request()->user()->merchant_id,
                'phone' => 'required|unique:merchants,phone,' . Request()->user()->merchant_id
            ];
        } else if (strpos($path, 'merchant/verify/phone') !== false) {
            return [
                'phone' => 'required'
            ];
        } else if (strpos($path, 'merchant/dashboard') !== false) {
            return [
                'since_at' => 'date',
                'until' => 'date|after:since_at'
            ];
        }
        return [];
    }
}
