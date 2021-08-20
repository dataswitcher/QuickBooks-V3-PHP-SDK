<?php

namespace QuickBooksOnline\API\Core\HttpClients;

use QuickBooksOnline\API\Core\Http\AsyncRequest;
use QuickBooksOnline\API\Core\HttpClients\Traits\CurlHttpTrait;

class CurlMultiHttpClient
{
    use CurlHttpTrait;

    /** @var resource[] */
    private $curlHandlers = [];

    /**
     * @param AsyncRequest[] $asyncRequests
     */
    public function process(array $asyncRequests)
    {
        $mh = curl_multi_init();

        foreach ($asyncRequests as $request) {
            $curlHandler = curl_init($request->getUrl());

            $this->curlHandlers[$request->getId()] = $curlHandler;

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
            $handler = $this->curlHandlers[$request->getId()];

            $request->setResponse((string) curl_multi_getcontent($handler));

            curl_multi_remove_handle($mh, $handler);
            curl_close($handler);
        }

        curl_multi_close($mh);
    }
}
