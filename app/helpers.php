<?php

use App\Models\Shipment;
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

function mergePDF($files)
{    
    $pdf = new PDFMerger();
    foreach ($files as $file) {
        $path = 'aramex/'.md5(time()).'.pdf';
        Storage::disk('local')->put($path,file_get_contents($file));
        $pdf->addPDF(Storage::path($path,'all'));
    }
    $pathForTheMergedPdf = Storage::path("aramex/result.pdf");
    $pdf->merge('file', $pathForTheMergedPdf);

    Storage::disk('s3')->put($path, file_get_contents(Storage::path("aramex/result.pdf")));

    Storage::deleteDirectory('aramex');
    return Storage::disk('s3')->url($path);
}


function exportXLSX($data,$path,$disk = 's3')
{
    Excel::store($data,$path,$disk);
    return Storage::disk('s3')->url($path);
}

function generateBarcodeNumber() {
    $number = mt_rand(1000000000, 9999999999);

    if (InternalAWBExists($number)) {
        return generateBarcodeNumber();
    }

    // otherwise, it's valid and can be used
    return $number;
}

function InternalAWBExists($number) {
    return Shipment::where('internal_awb',$number)->exists();
}