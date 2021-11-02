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

function randomNumber($length = 16) {
    $result = '';

    for($i = 0; $i < $length; $i++) {
        $result .= mt_rand(0, 9);
    }

    if (InternalAWBExists($result)) {
        return randomNumber($length);
    }

    return $result;
}

function InternalAWBExists($number) {
    return Shipment::where('internal_awb',$number)->exists();
}

function removeNamespaceFromXML( $xml )
{
    // Because I know all of the the namespaces that will possibly appear in 
    // in the XML string I can just hard code them and check for 
    // them to remove them
    $toRemove = ['rap', 'turss', 'crim', 'cred', 'j', 'rap-code', 'evic'];
    // This is part of a regex I will use to remove the namespace declaration from string
    $nameSpaceDefRegEx = '(\S+)=["\']?((?:.(?!["\']?\s+(?:\S+)=|[>"\']))+.)["\']?';

    // Cycle through each namespace and remove it from the XML string
   foreach( $toRemove as $remove ) {
        // First remove the namespace from the opening of the tag
        $xml = str_replace('<' . $remove . ':', '<', $xml);
        // Now remove the namespace from the closing of the tag
        $xml = str_replace('</' . $remove . ':', '</', $xml);
        // This XML uses the name space with CommentText, so remove that too
        $xml = str_replace($remove . ':commentText', 'commentText', $xml);
        // Complete the pattern for RegEx to remove this namespace declaration
        $pattern = "/xmlns:{$remove}{$nameSpaceDefRegEx}/";
        // Remove the actual namespace declaration using the Pattern
        $xml = preg_replace($pattern, '', $xml, 1);
    }

    // Return sanitized and cleaned up XML with no namespaces
    return $xml;
}

function XMLToArray($xml)
{
    // One function to both clean the XML string and return an array
    return json_decode(json_encode(simplexml_load_string(removeNamespaceFromXML($xml))), true);
}
