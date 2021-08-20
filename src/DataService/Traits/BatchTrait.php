<?php

namespace QuickBooksOnline\API\DataService\Traits;

use QuickBooksOnline\API\Core\Http\Serialization\XmlObjectSerializer;
use QuickBooksOnline\API\Core\ServiceContext;

trait BatchTrait
{
    /**
     * service context object.
     * @var ServiceContext serviceContext
     */
    protected $serviceContext;

    protected function buildXmlBody($httpsPostBodyPreProcessed, $intuitBatchRequest)
    {
        $doc = new \DOMDocument();
        $doc->loadXML($httpsPostBodyPreProcessed);
        $xpath = new \DOMXPath($doc);

        // Replace generically-named IntuitObject nodes with tags that describe contained objects
        $objectIndex = 0;
        while (1) {
            $matchingElementArray = $xpath->query("//IntuitObject");
            if (is_null($matchingElementArray)) {
                break;
            }

            if ($objectIndex>=count($intuitBatchRequest->BatchItemRequest)) {
                break;
            }

            foreach ($matchingElementArray as $oneNode) {
                // Found a DOMNode currently named "IntuitObject".  Need to rename to
                // entity that describes it's contents, like "ns0:Customer" (determine correct
                // name by inspecting IntuitObject's class).
                if ($intuitBatchRequest->BatchItemRequest[$objectIndex]->IntuitObject) {
                    // Determine entity name to use
                    $entityClassName = get_class($intuitBatchRequest->BatchItemRequest[$objectIndex]->IntuitObject);
                    $entityTransferName = XmlObjectSerializer::cleanPhpClassNameToIntuitEntityName($entityClassName);
                    $entityTransferName = 'ns0:'.$entityTransferName;

                    // Replace old-named DOMNode with new-named DOMNode
                    $newNode = $oneNode->ownerDocument->createElement($entityTransferName);
                    if ($oneNode->attributes->length) {
                        foreach ($oneNode->attributes as $attribute) {
                            $newNode->setAttribute($attribute->nodeName, $attribute->nodeValue);
                        }
                    }
                    while ($oneNode->firstChild) {
                        $newNode->appendChild($oneNode->firstChild);
                    }
                    $oneNode->parentNode->replaceChild($newNode, $oneNode);
                }
                break;
            }

            $objectIndex++;
        }

        return $doc->saveXML();
    }
}
