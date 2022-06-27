<?php
namespace QuickBooksOnline\API\Core\Http;

/**
 * A throttled response (429)
 */
class ThrottledResponse
{
    /**
     * @var
     */
    private $batchId;

    /**
     * @param $batchId
     */
    public function __construct($batchId)
    {
        $this->batchId = $batchId;
    }

    /**
     * Get the batch id
     */
    public function getBatchId()
    {
        return $this->batchId;
    }
}
