<?php
use Maatwebsite\Excel\Facades\Excel as Excel;

use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

function uploadFiles($folder,$data)
{
    $path = $folder. "/" . md5(Carbon::now()) . '.' . $data->getClientOriginalExtension();
    Storage::disk('s3')->put($path, file_get_contents($data));
    
    return Storage::disk('s3')->url($path);
}

function exportPDF($view,$path,$data)
{
    $pdf = \PDF::loadView("pdf.$view", [$view => $data]);
    Storage::disk('s3')->put($path,$pdf->output());
    return Storage::disk('s3')->url($path);
}

function exportXLSX($data,$path,$disk = 's3')
{
    Excel::store($data,$path,$disk);
    return Storage::disk('s3')->url($path);
}