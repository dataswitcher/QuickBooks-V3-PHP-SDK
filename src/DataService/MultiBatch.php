<?php

namespace QuickBooksOnline\API\DataService;

use Exception;
use QuickBooksOnline\API\Core\CoreConstants;
use QuickBooksOnline\API\Core\Http\Serialization\XmlObjectSerializer;
use QuickBooksOnline\API\Core\HttpClients\AsyncRestHandler;
use QuickBooksOnline\API\Core\HttpClients\RequestParameters;
use QuickBooksOnline\API\Core\ServiceContext;
use QuickBooksOnline\API\Data\IPPBatchItemRequest;
use QuickBooksOnline\API\Data\IPPIntuitBatchRequest;
use QuickBooksOnline\API\DataService\Traits\BatchTrait;

class MultiBatch
{
    use BatchTrait;

    /** @var AsyncRestHandler */
    private $asyncRestHandler;

    /** @var IPPBatchItemRequest[] */
    private $multiBatches = [];

    /**
     * @param ServiceContext $serviceContext
     * @param AsyncRestHandler $asyncRestHandler
     */
    public function __construct(ServiceContext $serviceContext, AsyncRestHandler $asyncRestHandler)
    {
        $this->serviceContext = $serviceContext;
        $this->asyncRestHandler = $asyncRestHandler;
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

            $this->asyncRestHandler->scheduleAsyncRequest((string) $batchId, $requestParameters, $httpsPostBody, null);
        }

        $this->asyncRestHandler->triggerAsyncRequests();
    }
}
