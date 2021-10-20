<?php

namespace App\Http\Controllers\Utilities;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class uploadController
{
    public static function uploadFiles($folder,$data)
    {
        $path = $folder. "/" . md5(Carbon::now()) . '.' . $data->getClientOriginalExtension();
        Storage::disk('s3')->put($path, file_get_contents($data));
            

        return Storage::disk('s3')->url($path);
    }
}
