<?php

namespace QuickBooksOnline\API\Core\HttpClients;

use QuickBooksOnline\API\Core\Http\AsyncRequest;
use QuickBooksOnline\API\Core\Http\AsyncResponse;
use QuickBooksOnline\API\Core\HttpClients\Traits\CurlHttpTrait;
use QuickBooksOnline\API\Exception\SdkException;

class CurlMultiHttpClient
{
    use CurlHttpTrait;

    /**
     * @param AsyncRequest[] $asyncRequests
     *
     * @return AsyncResponse[]
     * @throws SdkException
     */
    public function process(array $asyncRequests)
    {
        $curlHandlers = [];
        $responses = [];

        $mh = curl_multi_init();

        foreach ($asyncRequests as $request) {
            $curlHandler = curl_init($request->getUrl());

            $curlHandlers[$request->getId()] = $curlHandler;

            curl_setopt_array(
                $curlHandler,
                $this->buildOptions(
                    $request->getUrl(),
                    $request->getMethod(),
                    $request->getHeaders(),
                    $request->getBody(),
                    $request->getTimeout(),
                    $request->verifySsl()
                )
            );

            curl_multi_add_handle($mh, $curlHandler);
        }

        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh); // Wait a short time for more activity
            }
        } while ($running && $status === CURLM_OK);

        foreach ($asyncRequests as $request) {
            $handler = $curlHandlers[$request->getId()];

            $responses[] = $this->buildResponse($request->getId(), $handler);

            curl_multi_remove_handle($mh, $handler);
            curl_close($handler);
        }

        curl_multi_close($mh);

        return $responses;
    }

    /**
     * @throws SdkException
     */
    private function buildResponse($id, $handler)
    {
        $statusCode = curl_getinfo($handler, CURLINFO_RESPONSE_CODE);
        $url = curl_getinfo($handler,  CURLINFO_EFFECTIVE_URL);
        $response = curl_multi_getcontent($handler);

        if (!$statusCode && !$response) {
            $code = curl_errno($handler);
            $msg = curl_error($handler);

            throw new SdkException("cURL error during making API call to [$url]. cURL Error Number:[$code] with error:[$msg]");
        }

        $headerSize = curl_getinfo($handler, CURLINFO_HEADER_SIZE);
        $rawHeaders = mb_substr($response, 0, $headerSize);
        $rawBody = mb_substr($response, $headerSize);

        return new AsyncResponse(
            $id,
            $url,
            new IntuitResponse($rawHeaders, $rawBody, $statusCode, true)
        );
    }
}
