<?php

namespace QuickBooksOnline\API\Core\HttpClients;

use QuickBooksOnline\API\Core\HttpClients\Traits\CurlHttpTrait;
use QuickBooksOnline\API\Exception\SdkException;
use QuickBooksOnline\API\Core\CoreConstants;

/**
 * Class CurlHttpClient
 *
 * A Http Client using PHP cURL extension to send HTTP/HTTPS request to QuickBooks Online
 * @package QuickBooksOnline
 *
 */
class CurlHttpClient implements HttpClientInterface{
    use CurlHttpTrait;

    /**
    * @var BaseCurl The basecURL instance will be used for performing Http/Https client request.
    */
    private $basecURL = null;

    /**
    * @var IntuitResponse | False The parsed response from curl client to Intuit customized response
    */
    private $intuitResponse = false;

    /**
     * The constructor for constructing the cURL http client for making API calls
     * @param BaseCurl $curl    A predefined BaseCurl instance to be used in this client
     */
    public function __construct(BaseCurl $curl = null)
    {
        if(isset($curl)){
              $this->basecURL = $curl;
        }else{
              $this->basecURL = new BaseCurl();
        }
    }

    /**
     * @inheritdoc
     */
    public function makeAPICall($url, $method, array $headers, $body, $timeOut, $verifySSL){
        $this->clearResponse();
        $this->prepareRequest($url, $method, $headers, $body, $timeOut, $verifySSL);
        $rawResponse = $this->executeRequest();
        $this->handleErrors();
        $this->setIntuitResponse($rawResponse);
        $this->closeConnection();
        return $this->getLastResponse();
    }

    /**
     * @inheritdoc
     */
    public function prepareRequest($url, $method, array $headers, $body, $timeOut, $verifySSL){
        $curl_opt = $this->buildOptions($url, $method, $headers, $body, $timeOut, $verifySSL);

        $this->initializeCurl();
        $this->basecURL->setupCurlOptArray($curl_opt);
    }

    /**
     * Before making any API call, clear the stored response from previous request.
     */
    public function clearResponse(){
      $this->intuitResponse = false;
    }

    /**
     * Send a request and return the response
     * @return mixed <b>TRUE</b> on success or <b>FALSE</b> on failure. However, if the <b>CURLOPT_RETURNTRANSFER</b>
     */
    private function executeRequest(){
        return $this->basecURL->execute();
    }

    /**
     * The error during HTTP/HTTPS call. For example, the certificate expired. The server respond time out. It has nothing to do with the
     * status code returned from server.
     */
    private function handleErrors(){
        if($this->basecURL->errno() || $this->basecURL->error()){
           $errorMsg = $this->basecURL->error();
           $errorNumber = $this->basecURL->errno();
           throw new SdkException("cURL error during making API call. cURL Error Number:[" . $errorNumber . "] with error:[" . $errorMsg . "]");
        }
    }

    /**
     * @inheritdoc
     */
    public function setIntuitResponse($response){
        $headerSize = $this->basecURL->getInfo(CURLINFO_HEADER_SIZE);
        $rawHeaders = mb_substr($response, 0, $headerSize);
        $rawBody = mb_substr($response, $headerSize);
        $httpStatusCode = $this->basecURL->getInfo(CURLINFO_HTTP_CODE);
        $theIntuitResponse = new IntuitResponse($rawHeaders, $rawBody, $httpStatusCode, true);
        $this->intuitResponse = $theIntuitResponse;
    }

    /**
     * Check if the cURL instance exists. If not or closed, create a new BaseCurl instance for this Http client
     */
    private function initializeCurl(){
        if($this->basecURL->isCurlSet()){ return; }
        else {$this->basecURL->init();}
    }

    /**
     * close the connection of current http client
     */
    private function closeConnection(){
        $this->basecURL->close();
    }

    /**
     * @inheritdoc
     */
    public function getLastResponse(){
        return $this->intuitResponse;
    }
}
