<?php

namespace QuickBooksOnline\API\DataService\Traits;

use QuickBooksOnline\API\Core\Http\Serialization\IEntitySerializer;
use QuickBooksOnline\API\Core\Http\Serialization\XmlObjectSerializer;
use QuickBooksOnline\API\Core\HttpClients\AsyncRestHandler;
use QuickBooksOnline\API\Core\HttpClients\RestHandler;
use QuickBooksOnline\API\Core\ServiceContext;
use QuickBooksOnline\API\DataService\IntuitBatchResponse;
use QuickBooksOnline\API\DataService\MultiBatch;
use QuickBooksOnline\API\Exception\IdsException;
use QuickBooksOnline\API\Exception\SecurityException;
use QuickBooksOnline\API\Exception\ServiceException;
use QuickBooksOnline\API\Exception\ValidationException;
use QuickBooksOnline\API\Utility\UtilityConstants;

trait BatchTrait
{
    /**
     * service context object.
     * @var ServiceContext serviceContext
     */
    protected $serviceContext;

    /**
     * Enable debug mode to get more information inside the exception like intuit-tid
     * @var boolean
     */
    protected $debugMode = false;

    /**
     * rest handler object.
     * @var RestHandler|AsyncRestHandler
     */
    protected $restHandler;

    /**
     * serializer to be used.
     * @var IEntitySerializer responseSerializer
     */
    protected $responseSerializer;

    /**
     * Set the debug mode
     * @param $mode
     */
    public function setDebug($mode)
    {
        $this->debugMode = $mode;
    }

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

    /**
     * process batch item response
     * @param BatchItemResponse oneXmlObj The batchitem response.
     * @return IntuitBatchResponse IntuitBatchResponse object.
     */
    protected function ProcessBatchItemResponse($oneXmlObj)
    {
        $result = new IntuitBatchResponse();
        if (null==$oneXmlObj) {
            return $result;
        }

        if(isset($oneXmlObj["bId"])){
            $bid = (String)$oneXmlObj["bId"];
            $result->batchItemId = $bid;
        }else{
            throw new \Exception("No bid Found on the Batch Response.");
        }

        $firstChild = null;
        foreach ($oneXmlObj->children() as $oneChild) {
            $firstChild = $oneChild;
            break;
        }
        if (!$firstChild) {
            return $result;
        }

        $firstChildName = (string)$firstChild->getName();

        switch ($firstChildName) {
            //For batch query result
            case "QueryResponse":
                $result->responseType = UtilityConstants::Query;
                $queryResult = array();
                foreach ($oneXmlObj->QueryResponse->children() as $oneResponse) {
                    $oneEntity = $this->responseSerializer->Deserialize('<RestResponse>'.$oneResponse->asXML().'</RestResponse>');
                    $queryResult = array_merge($queryResult, $oneEntity);
                }
                $result->setEntities($queryResult);
                $result->successFlagOn();
                break;
            //For batch failure result
            case "Fault":
                $result->responseType = UtilityConstants::Exception;
                $idsException = $this->IterateFaultAndPrepareException($firstChild);
                if ($this->debugMode) {
                    if ($this instanceof MultiBatch) {
                        $debugInfo = [];

                        foreach ($this->asyncResponse as $response) {
                            $intuitResponse = $response->getIntuitResponse();

                            $debugInfo[] = [
                                'intuit_tid' => $intuitResponse->getIntuitTid(),
                                'body' => $intuitResponse->getBody(),
                                'headers' => $intuitResponse->getHeaders(),
                            ];
                        }
                    } else {
                        $interface = $this->restHandler->getHttpClientInterface();
                        $responseInterface = $interface->getLastResponse();
                        $debugInfo = [
                            'intuit_tid' => $responseInterface->getIntuitTid(),
                            'body' => $responseInterface->getBody(),
                            'headers' => $responseInterface->getHeaders(),
                        ];
                    }

                    $idsException->setDebug($debugInfo);
                }
                $result->exception = $idsException;
                break;
            //For batch Entity Result
            default:
                $result->responseType = UtilityConstants::Entity;
                $oneEntityArray = $this->responseSerializer->Deserialize('<RestResponse>'.$firstChild->asXML().'</RestResponse>');
                $oneEntity = $oneEntityArray[0];
                $result->entity = $oneEntity;
                $result->successFlagOn();
                break;
        }

        return $result;
    }

    /**
     * Prepare IdsException out of Fault object.
     * @param Fault fault Fault object.
     * @return IdsException IdsException object.
     */
    public function IterateFaultAndPrepareException($fault)
    {
        if (!$this->verifyFault($fault)) {
            return null;
        }
        // Collect information from XML entity
        $type = (string)$fault->attributes()->type;
        list($message, $code) = $this->arrayToMessageAndCode($this->collectErrors($fault));
        if (is_null($message)) {
            return new IdsException("Fault Exception of type: " . $type . " has been generated.");
        }
        $idsException = null;


        // Fault types can be of Validation, Service, Authentication and Authorization. Run them through the switch case.
        switch ($type) {
            // If Validation errors iterate the Errors and add them to the list of exceptions.
            case "Validation":
            case "ValidationFault":
                // Throw specific exception like ValidationException.
                $idsException = new ValidationException($message, $code);
                break;
            // If Validation errors iterate the Errors and add them to the list of exceptions.
            case "Service":
            case "ServiceFault":
                // Throw specific exception like ServiceException.
                $idsException = new ServiceException($message, $code);
                break;
            // If Validation errors iterate the Errors and add them to the list of exceptions.
            case "Authentication":
            case "AuthenticationFault":
            case "Authorization":
            case "AuthorizationFault":
                $idsException = new SecurityException($message, $code);
                break;
            // Use this as default if there was some other type of Fault
            default:
                $idsException = new IdsException($message, $code);

        }

        // Return idsException which will be of type Validation, Service or Security.
        return $idsException;
    }

    protected function verifyFault($fault)
    {
        if ($fault == null) {
            return null;
        }
        if (empty($fault)) {
            return null;
        }
        if (!$fault instanceof \SimpleXMLElement) {
            return null;
        }
        if (!$fault->attributes() instanceof \SimpleXMLElement) {
            return null;
        }
        if (!isset($fault->attributes()->type)) {
            return null;
        }

        return true;
    }

    /**
     * Helper function for store the error message and code from Fault returned from QuickBooks Online
     */
    protected function arrayToMessageAndCode(array $array)
    {
        if (empty($array)) {
            return array(null,null);
        }
        if (1 == count($array)) {
            $item = array_pop($array);
            return array($item->message,$item->code);
        }

        $message = "";
        $code = "";
        foreach ($array as $item) {
            $message .= "Exception: ".$item->message . "\n";
            if (empty($code) && !empty($item->code)) {
                $code = $item->code;
            }
        }
        return array($message,$code);
    }

    protected function collectErrors($fault)
    {
        $errors = array();
        if (isset($fault->Error)
            && ($fault->Error instanceof \SimpleXMLElement)
            && $fault->Error->count()) {
            foreach ($fault->Error as $item) {
                if (!isset($item->Message)) {
                    continue;
                }
                if (!$item->Message instanceof \SimpleXMLElement) {
                    continue;
                }
                $error = new \stdClass();
                $detail = (string)$item->Detail;
                if($detail) {
                    $error->message = (string)$item->Message . ' - ' . $detail;
                } else {
                    $error->message = (string)$item->Message;
                }
                $error->code = null;
                if ($item->attributes() instanceof \SimpleXMLElement
                    && isset($item->attributes()->code)) {
                    $error->code = (string)$item->attributes()->code;
                }
                $errors[] =$error;
            }
        }
        return $errors;
    }
}
