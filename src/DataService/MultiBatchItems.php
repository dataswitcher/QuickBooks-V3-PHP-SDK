<?php

namespace QuickBooksOnline\API\DataService;

use QuickBooksOnline\API\Data\IPPBatchItemRequest;
use QuickBooksOnline\API\Exception\IdsException;
use QuickBooksOnline\API\Exception\IdsExceptionManager;

class MultiBatchItems
{
    /** @var array */
    private $assignedItemIds = [];

    /** @var IPPBatchItemRequest[] batchRequests */
    private $batchRequests;

    /** @var string */
    private $id;

    /**
     * @param string $id
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * @param IEntity $item            Entity for the batch operation.
     * @param string $id               Unique batch item id
     * @param OperationEnum $operation Operation to be performed for the entity.
     * @param string $optionsData      To send with this specific batch item (example - allowduplicatedocnumber for invoices)
     *
     * @throws IdsException
     */
    public function AddItem($item, $id, $operation, $optionsData = null)
    {
        if (!$item) {
            IdsExceptionManager::HandleException(new IdsException('StringParameterNullOrEmpty: entity'));
        }

        if (!$id) {
            IdsExceptionManager::HandleException(new IdsException('StringParameterNullOrEmpty: id'));
        }

        if (!$operation) {
            IdsExceptionManager::HandleException(new IdsException('StringParameterNullOrEmpty: operation'));
        }

        if (in_array($id, $this->assignedItemIds)) {
            IdsExceptionManager::HandleException(new IdsException('BatchIdAlreadyUsed'));
        }

        $batchItem = new IPPBatchItemRequest();
        $batchItem->IntuitObject = $item;
        $batchItem->bId = $id;
        $batchItem->operation = $operation;
        $batchItem->operationSpecified = true;

        if ($optionsData !== null) {
            $batchItem->optionsData = $optionsData;
        }

        $this->assignedItemIds[] = $id;
        $this->batchRequests[] = $batchItem;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return IPPBatchItemRequest[]
     */
    public function getItems()
    {
        return $this->batchRequests;
    }
}
