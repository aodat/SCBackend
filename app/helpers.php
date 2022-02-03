<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LynX39\LaraPdfMerger\Facades\PdfMerger;
use Maatwebsite\Excel\Facades\Excel as Excel;
use Mpdf\Mpdf;

if (!function_exists('exportPDF')) {
    function exportPDF($view, $path, $data)
    {
        $mpdf = new Mpdf();
        $html = view("pdf.$view", [$view => $data])->render();
        $mpdf->WriteHTML($html);
        Storage::disk('s3')->put($path, $mpdf->Output('filename.pdf', 'S'));
        return Storage::disk('s3')->url($path);
    }
}

if (!function_exists('mergePDF')) {
    function mergePDF($files)
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
}

if (!function_exists('mergePDF')) {
    function exportXLSX($data, $path, $disk = 's3')
    {
        Excel::store($data, $path, $disk);
        return Storage::disk('s3')->url($path);
    }
}

function currency_exchange($amount, $from, $to = 'USD')
{
    $rates = [
        'JOD' => 1.41,
        'SAR' => 0.27,
    ];
    return $rates[$from] * $amount;
}

function nestedLowercase($value)
{
    if (is_array($value)) {
        return array_map('nestedLowercase', $value);
    }
    return strtolower($value);
}

function array_to_xml(array $arr, SimpleXMLElement $xml)
{
    foreach ($arr as $k => $v) {
        is_array($v)
        ? array_to_xml($v, $xml->addChild($k))
        : $xml->addChild($k, $v);
    }
    return $xml;
}

function removeNamespaceFromXML($xml)
{
    // Because I know all of the the namespaces that will possibly appear in
    // in the XML string I can just hard code them and check for
    // them to remove them
    $toRemove = ['rap', 'turss', 'crim', 'cred', 'j', 'rap-code', 'evic'];
    // This is part of a regex I will use to remove the namespace declaration from string
    $nameSpaceDefRegEx = '(\S+)=["\']?((?:.(?!["\']?\s+(?:\S+)=|[>"\']))+.)["\']?';

    // Cycle through each namespace and remove it from the XML string
    foreach ($toRemove as $remove) {
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
