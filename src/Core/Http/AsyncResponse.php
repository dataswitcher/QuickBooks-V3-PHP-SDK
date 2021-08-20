<?php

namespace QuickBooksOnline\API\Core\Http;

use QuickBooksOnline\API\Core\HttpClients\IntuitResponse;

class AsyncResponse
{
    /** @var string */
    private $id;

    /** @var IntuitResponse */
    private $intuitResponse;
    /** @var string */
    private $uri;

    /**
     * @param string $id
     * @param string $uri
     * @param IntuitResponse $intuitResponse
     */
    public function __construct($id, $uri, IntuitResponse $intuitResponse)
    {
        $this->id = $id;
        $this->uri = $uri;
        $this->intuitResponse = $intuitResponse;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function getIntuitResponse()
    {
        return $this->intuitResponse;
    }
}
