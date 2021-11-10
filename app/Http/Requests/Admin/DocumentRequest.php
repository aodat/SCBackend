<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DocumentRequest extends FormRequest
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
    private function checkMethodVerified(){
         $path = Request()->route()->uri;
         if ($this->getMethod() == 'PUT' && strpos($path, '{merchant_id}/document/{id}') !== false)
          return true;
         
        return false;  
    }

    private function checkMethodCreate(){
        $path = Request()->route()->uri;
        if ($this->getMethod() == 'POST' && strpos($path, 'merchant/{merchant_id}/document') !== false)
         return true;
        
       return false;  
    }
    public function all($keys = null)
    {
        
        $data = parent::all($keys);
        $data['merchant_id'] = $this->route('merchant_id');
        if($this->checkMethodVerified())
          $data['id'] = $this->route('id');

        return $data;
    }
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $path = Request()->route()->uri;

        if ($this->checkMethodCreate())
            return [
                "url" => "required|file",
                "type" => "required|string",
            ];
        
        if($this->checkMethodVerified())
            return [
                "status" => "required|in:verified,rejected",
              
            ];    
        
        return [];
    }
}
