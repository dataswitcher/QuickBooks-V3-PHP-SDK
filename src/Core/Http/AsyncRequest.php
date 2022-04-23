<?php

namespace QuickBooksOnline\API\Core\Http;

class AsyncRequest
{
    /** @var string */
    private $id;

    /** @var string */
    private $url;

    /** @var string */
    private $method;

    /** @var array */
    private $headers;

    /** @var string */
    private $body;

    /** @var int */
    private $timeout;

    /** @var bool */
    private $verifySsl;

    /**
     * @param string $id
     * @param string $url
     * @param string $method
     * @param array $headers
     * @param string $body
     * @param int $timeout
     * @param bool $verifySsl
     */
    public function __construct(
        $id,
        $url,
        $method,
        array $headers,
        $body,
        $timeout,
        $verifySsl
    ) {
        $this->id = $id;
        $this->url = $url;
        $this->method = strtoupper($method);
        $this->headers = $headers;
        $this->body = $body;
        $this->timeout = $timeout;
        $this->verifySsl = $verifySsl;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @return bool
     */
    public function verifySsl()
    {
        return $this->verifySsl;
    }
}
