<?php
namespace App\Http\Controllers\Utilities;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LynX39\LaraPdfMerger\Facades\PdfMerger;
use Maatwebsite\Excel\Facades\Excel as Excel;
use Mpdf\Mpdf;

class Documents
{
    public static function merge($files)
    {
        $pdfMerger = PDFMerger::init();

        $folder = time();
        $quotes = $folder . '/quotes.pdf';
        Storage::disk('local')->put($quotes, '');

        foreach ($files as $file) {
            $file = str_replace("https://shipcashcdn.s3.amazonaws.com/", '', $file);
            $path = $folder . '/' . Str::uuid() . '.pdf';
            Storage::disk('local')->put(
                $path,
                Storage::disk('s3')->get($file)
            );

            $pdfMerger->addPDF(Storage::path($path), 'all', 'P');
        }

        $pdfMerger->merge();
        $export = 'export/' . md5(time()) . '.pdf';
        Storage::disk('s3')->put(
            $export,
            $pdfMerger->save(Storage::path($quotes), "string")
        );

        Storage::deleteDirectory($folder);
        return Storage::disk('s3')->url($export);
    }

    public static function pdf($view, $path, $data)
    {
        $mpdf = new Mpdf();
        $html = view("pdf.$view", [$view => $data])->render();
        $mpdf->WriteHTML($html);

        Storage::disk('s3')->put($path, $mpdf->Output('filename.pdf', 'S'));
        return Storage::disk('s3')->url($path);
    }

    public static function xlsx($data, $path)
    {
        Excel::store($data, $path, 's3');
        return Storage::disk('s3')->url($path);
    }
}
