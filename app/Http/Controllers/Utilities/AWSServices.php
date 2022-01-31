<?php

namespace App\Http\Controllers\Utilities;

use Aws\Rekognition\RekognitionClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AWSServices
{
    private static $rekognition;

    public function __construct()
    {
        $this->rekognition = new RekognitionClient([
            'region' => 'us-west-2',
            'version' => 'latest',
        ]);
    }

    public static function uploadToS3($folder, $file, $type = '', $isOutput = false)
    {
        $path = $folder . "/" . Str::uuid();
        if (!$isOutput) {
            $path .= $file->getClientOriginalExtension();
            $data = file_get_contents($file);
        } else {
            $data = $file;
            $path .= ".$type";
        }

        Storage::disk('s3')->put($path, $data);

        return Storage::disk('s3')->url($path);
    }

    public static function compareFace($photo1, $photo2)
    {
        return self::$rekognition->compareFaces([
            'SimilarityThreshold' => 80.0,
            'SourceImage' => [
                'Bytes' => self::curl_get_file_size($photo1),
            ],
            'TargetImage' => [
                'Bytes' => self::curl_get_file_size($photo2),
            ],
        ]);
    }

    public static function detectFaces($photo)
    {
        // Call DetectFaces
        $result = self::$rekognition->DetectFaces([
            'Image' => array(
                'Bytes' => self::curl_get_file_size($photo),
            ),
            'Attributes' => array('ALL'),
        ]
        );
    }

    public static function curl_get_file_size($url)
    {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }
}
