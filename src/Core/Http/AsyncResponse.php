<?php

namespace QuickBooksOnline\API\Core\Http;

class AsyncResponse
{
    private $id;
    private $statusCode;
    private $body;

    /**
     * @param string $id
     * @param int $statusCode
     * @param string $body
     */
    public function __construct($id, $statusCode, $body)
    {
        $this->id = $id;
        $this->statusCode = $statusCode;
        $this->body = $body;
    }

    public function id()
    {
        return $this->id;
    }

    public function statusCode()
    {
        return $this->statusCode;
    }

    public function body()
    {
        return $this->body;
    }
}
