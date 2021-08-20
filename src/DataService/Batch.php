<?php
/*******************************************************************************
 * Copyright (c) 2017 Intuit
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *******************************************************************************/
namespace QuickBooksOnline\API\DataService;

use QuickBooksOnline\API\Core\CoreConstants;
use QuickBooksOnline\API\Core\CoreHelper;
use QuickBooksOnline\API\Core\Http\Serialization\IEntitySerializer;
use QuickBooksOnline\API\Core\Http\Serialization\XmlObjectSerializer;
use QuickBooksOnline\API\Core\HttpClients\FaultHandler;
use QuickBooksOnline\API\Core\HttpClients\RequestParameters;
use QuickBooksOnline\API\Core\HttpClients\RestHandler;
use QuickBooksOnline\API\Data\IPPBatchItemRequest;
use QuickBooksOnline\API\Data\IPPIntuitBatchRequest;
use QuickBooksOnline\API\DataService\Traits\BatchTrait;
use QuickBooksOnline\API\Diagnostics\TraceLevel;
use QuickBooksOnline\API\Exception\IdsException;
use QuickBooksOnline\API\Exception\IdsExceptionManager;

/**
 * This class contains code for Batch Processing.
 */
class Batch
{
    use BatchTrait;

    /**
     * batch requests
     * @var array batchRequests
     */
    private $batchRequests;

    /**
     * batch responses
     * @deprecated
     * @var array batchResponses
     */
    private $batchResponses;

    /**
     * Intuit batch item responses list.
     * @var array batchResponses
     */
    public $intuitBatchItemResponses;

    /**
     * rest handler object.
     * @var RestHandler restHandler
     */
    private $restHandler;

    /**
     * serializer to be used.
     * @var IEntitySerializer responseSerializer
     */
    private $responseSerializer;

    /**
    * If not false, the request from last dataService did not return 2xx
    * @var FaultHandler
    */
    private $lastError = false;

    /**
    * Throw Exception on Error or not. Default is false.
    * @var boolean
    */
    private $isThrowExceptionOnError = false;

    /**
    * Get the error from last request
    * @return FaultHandler
    */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Initializes a new instance of the Batch class.
     * @param $serviceContext The service context.
     * @param $restHandler The rest handler.
     */
    public function __construct($serviceContext, $restHandler, $isThrowExceptionOnError)
    {
        $this->serviceContext = $serviceContext;
        $this->restHandler = $restHandler;
        $this->isThrowExceptionOnError = $isThrowExceptionOnError;
        $this->responseSerializer = CoreHelper::GetSerializer($this->serviceContext, false);
        $this->batchRequests = array();
        $this->batchResponses = array();
        $this->intuitBatchItemResponses = array();
    }

    /**
     * Gets the count.
     * @return int count
     */
    public function Count()
    {
        return count($this->batchRequests);
    }

    /**
     * Gets list of entites in case ResponseType is Report.
     */
    public function ReadOnlyCollection()
    {
        return $this->intuitBatchItemResponses;
    }

    /**
     * Gets the IntuitBatchResponse with the specified id.
     * @param string $id unique batchitem id
     */
    public function IntuitBatchResponse($id)
    {
        if(array_key_exists($id, $this->intuitBatchItemResponses)){
            return $this->intuitBatchItemResponses[$id];
        }else{
            return null;
        }
    }

    /**
     * Adds the specified query.
     * @param string $query IDS query.
     * @param string $id unique batchitem id.
     */
    public function AddQuery($query, $id)
    {
        if (!$query) {
            $exception = new IdsException('StringParameterNullOrEmpty: query');
            IdsExceptionManager::HandleException($exception);
        }

        if (!$id) {
            $exception = new IdsException('StringParameterNullOrEmpty: id');
            IdsExceptionManager::HandleException($exception);
        }

        if (count($this->batchRequests)>25) {
            $exception = new IdsException('BatchItemsExceededException');
            IdsExceptionManager::HandleException($exception);
        }

        $batchItem = new IPPBatchItemRequest();
        $batchItem->Query = $query;
        $batchItem->bId = $id;
        $batchItem->operationSpecified = true;
        //$batchItem->ItemElementName = ItemChoiceType6::Query;
        $this->batchRequests[] = $batchItem;
    }


    /**
     * Adds the specified query.
     * @param IEntity entity entitiy for the batch operation.
     * @param string id Unique batchitem id
     * @param OperationEnum operation operation to be performed for the entity.
     * @param string optionsdata to send with this specific batch item (example - allowduplicatedocnumber for invoices)
     */
     public function AddEntity($entity, $id, $operation, $optionsData = null)
     {
         if (!$entity) {
             $exception = new IdsException('StringParameterNullOrEmpty: entity');
             IdsExceptionManager::HandleException($exception);
         }

         if (!$id) {
             $exception = new IdsException('StringParameterNullOrEmpty: id');
             IdsExceptionManager::HandleException($exception);
         }

         if (!$operation) {
             $exception = new IdsException('StringParameterNullOrEmpty: operation');
             IdsExceptionManager::HandleException($exception);
         }

         foreach ($this->batchRequests as $oneBatchRequest) {
             if ($oneBatchRequest->bId == $id) {
                 $exception = new IdsException('BatchIdAlreadyUsed');
                 IdsExceptionManager::HandleException($exception);
             }
         }

         $batchItem = new IPPBatchItemRequest();
         $batchItem->IntuitObject = $entity;
         $batchItem->bId = $id;
         $batchItem->operation = $operation;
         $batchItem->operationSpecified = true;
         if ($optionsData !== null) {
             $batchItem->optionsData = $optionsData;
         }

         $this->batchRequests[] = $batchItem;
     }


    /**
     * Removes batchitem with the specified batchitem id.
     * @param string id unique batchitem id
     */
    public function Remove($id)
    {
        if (!$id) {
            $exception = new IdsException('BatchItemIdNotFound: id');
            IdsExceptionManager::HandleException($exception);
        }

        $revisedBatchRequests = array();
        foreach ($this->batchRequests as $oneBatchRequest) {
            if ($oneBatchRequest->bId == $id) {
                // Exclude
            } else {
                $revisedBatchRequests[] = $oneBatchRequest;
            }
        }
        $this->batchRequests = $revisedBatchRequests;
    }

    /**
     * Remove all the batchitem requests.
     */
    public function RemoveAll()
    {
        $this->batchRequests = array();
    }


    /**
     * This method executes the batch request.
     */
    public function Execute()
    {
       $requestID = rand() . rand();
       $this->sendRequest($requestID);
    }

    /**
     * Use this function to do Batch Request instead of Execute()
     */
    public function ExecuteWithRequestID($requestID)
    {
      if(isset($requestID) && !empty($requestID)){
          $this->sendRequest($requestID);
      }else{
        throw new \Exception("ExecuteWithRequestID called with Empty or Null request ID");
      }

    }

    private function sendRequest($requestID)
    {
      $this->serviceContext->IppConfiguration->Logger->CustomLogger->Log(TraceLevel::Info, "Started Executing Method Execute for Batch");

      // Create Intuit Batch Request
      $intuitBatchRequest = new IPPIntuitBatchRequest();
      $intuitBatchRequest->BatchItemRequest = $this->batchRequests;
      $uri = "company/{1}/batch?requestid=" . $requestID;
      $uri = str_replace('{1}', $this->serviceContext->realmId, $uri);

      // Creates request parameters
      $requestParameters = new RequestParameters($uri, 'POST', CoreConstants::CONTENTTYPE_APPLICATIONXML, null);

      $restRequestHandler = $this->getRestHandler();
      try {
          // Get literal XML representation of IntuitBatchRequest into a DOMDocument
          $httpsPostBodyPreProcessed = XmlObjectSerializer::getPostXmlFromArbitraryEntity($intuitBatchRequest, $urlResource);

          $httpsPostBody = $this->buildXmlBody($httpsPostBodyPreProcessed, $intuitBatchRequest);

          list($responseCode, $responseBody) = $restRequestHandler->sendRequest($requestParameters, $httpsPostBody, null, $this->isThrowExceptionOnError);
          $faultHandler = $restRequestHandler->getFaultHandler();
          if ($faultHandler) {
              $this->lastError = $faultHandler;
              return null;
          }else{
              $this->lastError = false;
          }
      } catch (\Exception $e) {
          IdsExceptionManager::HandleException($e);
      }

      try {
          // No JSON support here yet
          // de serialize object
          $responseXmlObj = simplexml_load_string($responseBody);
          foreach ($responseXmlObj as $oneXmlObj) {
              // process batch item
              $intuitBatchItemResponse = $this->ProcessBatchItemResponse($oneXmlObj);
              $this->intuitBatchItemResponses[$intuitBatchItemResponse->batchItemId] = $intuitBatchItemResponse;
          }
      } catch (\Exception $e) {
          $this->serviceContext->IppConfiguration->Logger->CustomLogger->Log(TraceLevel::Error, "Encountered an error parsing the batch response." . $e->getMessage());
          $this->serviceContext->IppConfiguration->Logger->CustomLogger->Log(TraceLevel::Error, "Stack Trace: " . $e->getTraceAsString());
          return null;
      }

      $this->serviceContext->IppConfiguration->Logger->CustomLogger->Log(TraceLevel::Info, "Finished Execute method for batch.");
    }


    /**
     * Returns handler to communicate with service
     * @return RestHandler
     */
    protected function getRestHandler()
    {
        return $this->restHandler;
    }
}
