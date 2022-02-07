<?php

namespace App\Http\Controllers\Utilities;

use SimpleXMLElement;

class XML
{

    public static function toXML(array $arr, SimpleXMLElement $xml)
    {

        foreach ($arr as $k => $v) {
            is_array($v)
            ? self::toXML($v, $xml->addChild($k))
            : $xml->addChild($k, $v);
        }
        return $xml;
    }

    public static function toArray($xml)
    {
        return json_decode(json_encode(simplexml_load_string(self::removeNamespaceFromXML($xml))), true);
    }

    private static function removeNamespaceFromXML($xml)
    {
        $toRemove = ['rap', 'turss', 'crim', 'cred', 'j', 'rap-code', 'evic'];
        $nameSpaceDefRegEx = '(\S+)=["\']?((?:.(?!["\']?\s+(?:\S+)=|[>"\']))+.)["\']?';

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
}
