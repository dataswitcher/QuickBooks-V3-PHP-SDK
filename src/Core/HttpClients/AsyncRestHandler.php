<?php

namespace QuickBooksOnline\API\Core\HttpClients;

use QuickBooksOnline\API\Core\CoreConstants;
use QuickBooksOnline\API\Core\Http\AsyncRequest;
use QuickBooksOnline\API\Core\Http\AsyncResponse;
use QuickBooksOnline\API\Core\ServiceContext;
use QuickBooksOnline\API\Exception\SdkException;

class AsyncRestHandler extends SyncRestHandler
{
    /** @var array */
    private $scheduledRequests = [];

    /** @var CurlMultiHttpClient|null */
    private $curlMultiClient;

    /**
     * Initializes a new instance of the SyncRestHandler class.
     *
     * @param ServiceContext   $context    The service context used for the request
     * @param CurlMultiHttpClient $client  The http client used for the request
     */
    public function __construct($context, CurlMultiHttpClient $client = null)
    {
        parent::__construct($context);
        $this->curlMultiClient = $client;
    }

    /**
     * @param string $batchId
     * @param RequestParameters $requestParameters
     * @param string|false $requestBody
     * @param string|null $specifiedRequestUri
     *
     * @throws SdkException
     */
    public function scheduleAsyncRequest($batchId, $requestParameters, $requestBody, $specifiedRequestUri)
    {
        // This step is required since the configuration settings might have been changed.
        $this->resetCompressorAndSerializer();

        // Get the OAuth Authorization Mode for the request, OAuth 1 or OAuth 2.
        $oMode = $this->context->IppConfiguration->OAuthMode;

        // Determine dest URI
        $requestUri = $this->getDestinationURL($requestParameters, $oMode, $specifiedRequestUri);

        // Minor version support
        $requestUri = $this->appendMinorVersionToRequestURI($requestUri);

        // Check for the HTTP method
        $HttpMethod = $this->checkHTTPMethod($requestParameters);
        $queryParameters = $this->parseURL($requestUri);
        $baseURL = $this->getBaseURL($requestUri);

        if ($oMode == CoreConstants::OAUTH1) {
            $headers = $this->buildOAuth1Headers($baseURL, $queryParameters, $HttpMethod, $requestUri, $requestParameters, $requestBody);
        } elseif ($oMode == CoreConstants::OAUTH2) {
            $headers = $this->buildOAuth2Headers($requestUri, $requestParameters, $requestBody);
        } else {
            throw new SdkException('OAuth Mode not supported.');
        }

        $this->LogAPIRequestToLog($requestBody, $requestUri, $headers);

        $request = new AsyncRequest(
            $batchId,
            $requestUri,
            $HttpMethod,
            $headers,
            $requestBody,
            60,
            false
        );

        $this->scheduledRequests[] = $request;
    }

    /**
     * @return AsyncResponse[]
     */
    public function triggerScheduledRequests()
    {
        $result = $this->curlMultiClient->process($this->scheduledRequests);

        $this->scheduledRequests = [];

        return $result;
    }
}
