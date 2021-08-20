<?php

namespace QuickBooksOnline\API\Core\HttpClients\Traits;

use QuickBooksOnline\API\Core\CoreConstants;
use QuickBooksOnline\API\Exception\SdkException;

trait CurlHttpTrait
{
    /**
     * Convert an Array to Curl Headers
     * @param array $headerArray The request headers
     * @return array Curl Headers
     */
    public function convertHeaderArrayToHeaders(array $headerArray){
        $headers = array();
        foreach($headerArray as $k => $v){
            $headers[] = $k . ":" . $v;
        }
        return $headers;
    }

    protected function buildOptions($url, $method, array $headers, $body, $timeOut, $verifySSL)
    {
        if (defined('QUICKBOOKS_API_TIMEOUT')) {
            // if the timeout constant is set, use it for the timeout
            $timeOut = (int)QUICKBOOKS_API_TIMEOUT;
        }

        //Set basic Curl Info
        $curl_opt = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => trim($method),
            //Set return transfer to true so the curl_exec will return the result
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->getHeaders($headers),
            //10 seconds is allowed to make the connection to the server
            CURLOPT_CONNECTTIMEOUT => isset($timeOut) ? $timeOut : 60,
            CURLOPT_TIMEOUT => isset($timeOut) ? $timeOut : 60,
            //When CURLOPT_HEADER is set to 0 the only effect is that header info from the response is excluded from the output.
            //So if you don't need it that's a few less KBs that curl will return to you.
            //In our case, header is required
            CURLOPT_HEADER => true
        ];

        if ($method !== "GET" && isset($body)) {
            $curl_opt[CURLOPT_POSTFIELDS] = $body;
        }

        //Set SSL. Only Enabled for OAuth 2 Request
        $this->setSSL($curl_opt, $verifySSL);

        return $curl_opt;
    }

    /**
     * Set the SSL certifcate path and corresponding varaibles for cURL
     */
    protected function setSSL(&$curl_opt, $verifySSL){
        $curl_opt[CURLOPT_SSL_VERIFYPEER] = true;
        if($verifySSL){
            $curl_opt[CURLOPT_SSL_VERIFYHOST] = 2;
            //based on spec, if TLS 1.2 is supported, it will use the TLS 1.2 or latest version by default
            //$curl_opt[CURLOPT_SSLVERSION] = 6;
            $curl_opt[CURLOPT_CAINFO] = CoreConstants::getCertPath(); //Pem certification Key Path
        } else {
            $curl_opt[CURLOPT_SSL_VERIFYHOST] = 0;
        }
    }

    /**
     * Get headers for the Quickbooks Online Response
     */
    protected function getHeaders($headers){
        if(!isset($headers) || empty($headers)){
            throw new SdkException("Error. The headers set for cURL are either NULL or Empty");
        }else{
            return $this->convertHeaderArrayToHeaders($headers);
        }
    }
}
