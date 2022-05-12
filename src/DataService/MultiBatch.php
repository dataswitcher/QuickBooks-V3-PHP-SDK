<?php

namespace QuickBooksOnline\API\DataService;

use QuickBooksOnline\API\Core\CoreConstants;
use QuickBooksOnline\API\Core\CoreHelper;
use QuickBooksOnline\API\Core\Http\AsyncResponse;
use QuickBooksOnline\API\Core\Http\Serialization\XmlObjectSerializer;
use QuickBooksOnline\API\Core\HttpClients\AsyncRestHandler;
use QuickBooksOnline\API\Core\HttpClients\RequestParameters;
use QuickBooksOnline\API\Core\ServiceContext;
use QuickBooksOnline\API\Data\IPPBatchItemRequest;
use QuickBooksOnline\API\Data\IPPIntuitBatchRequest;
use QuickBooksOnline\API\DataService\Traits\BatchTrait;
use QuickBooksOnline\API\Diagnostics\TraceLevel;

class MultiBatch
{
    use BatchTrait;

    /** @var IPPBatchItemRequest[] */
    private $multiBatches = [];

    /** @var AsyncResponse[] */
    private $asyncResponse = [];

    /** @var callable */
    private $accessTokenCallback;

    /**
     * @param ServiceContext $serviceContext
     * @param AsyncRestHandler $restHandler
     */
    public function __construct(ServiceContext $serviceContext, AsyncRestHandler $restHandler)
    {
        $this->serviceContext = $serviceContext;
        $this->restHandler = $restHandler;
        $this->responseSerializer = CoreHelper::GetSerializer($this->serviceContext, false);
    }

    /**
     * @param MultiBatchItems $multiBatch
     *
     * @return $this
     */
    public function addMultiBatch(MultiBatchItems $multiBatch)
    {
        $this->multiBatches[$multiBatch->getId()] = $multiBatch->getItems();

        return $this;
    }

    /**
     * @param callable $callback
     */
    public function setAccessTokenCallback(callable $callback)
    {
        $this->accessTokenCallback = $callback;
    }

    /**
     * @return IntuitBatchResponse[][]
     *
     * @throws \QuickBooksOnline\API\Exception\SdkException
     */
    public function process()
    {
        /** @var IPPBatchItemRequest[] $batchItems */
        foreach ($this->multiBatches as $batchId => $batchItems) {
            $intuitBatchRequest = new IPPIntuitBatchRequest();
            $intuitBatchRequest->BatchItemRequest = $batchItems;

            $uri = sprintf('company/%s/batch?requestid=%s', $this->serviceContext->realmId, $batchId);

            // Creates request parameters
            $requestParameters = new RequestParameters($uri, 'POST', CoreConstants::CONTENTTYPE_APPLICATIONXML, null);

            // Get literal XML representation of IntuitBatchRequest into a DOMDocument
            $postBodyPreProcessed = XmlObjectSerializer::getPostXmlFromArbitraryEntity($intuitBatchRequest, $urlResource);

            $httpsPostBody = $this->buildXmlBody($postBodyPreProcessed, $intuitBatchRequest);

            $this->restHandler->scheduleAsyncRequest((string) $batchId, $requestParameters, $httpsPostBody, null);
        }

        $results = $this->restHandler->triggerScheduledRequests($this->accessTokenCallback);

        return $this->buildResponse($results);
    }

    /**
     * @param array $results
     *
     * @return IntuitBatchResponse[][]
     */
    private function buildResponse(array $results)
    {
        $this->asyncResponse = $results;
        $response = [];

        /** @var AsyncResponse $result */
        foreach ($results as $result) {
            $batchId = $result->getId();
            $body = $result->getIntuitResponse()->getBody();

            try {
                $responseXmlObj = simplexml_load_string($body);

                foreach ($responseXmlObj as $oneXmlObj) {
                    $response[$batchId][] = $this->ProcessBatchItemResponse($oneXmlObj);
                }
            } catch (\Exception $e) {
                $this->serviceContext->IppConfiguration->Logger->CustomLogger->Log(TraceLevel::Error, "Encountered an error while parsing batch {$batchId}: " . $e->getMessage());
                $this->serviceContext->IppConfiguration->Logger->CustomLogger->Log(TraceLevel::Error, "Stack Trace: " . $e->getTraceAsString());
            }

            $this->serviceContext->IppConfiguration->Logger->CustomLogger->Log(TraceLevel::Info, "Finished Execute method for batch {$batchId}");
        }

        return $response;
    }
}
