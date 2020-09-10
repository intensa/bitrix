<?php


namespace Mindbox\DTO\V3;

/**
 * Trait SkuDTO
 *
 * @package Mindbox\DTO\V3
 * @property string $productId
 * @property string $basePricePerItem
 * @property string $skuId
 **/
trait SkuDTO
{
    /**
     * @return string
     */
    public function getProductId()
    {
        return $this->getField('productId');
    }

    /**
     * @return string
     */
    public function getBasePricePerItem()
    {
        return $this->getField('basePricePerItem');
    }

    /**
     * @return string
     */
    public function getSkuId()
    {
        return $this->getField('skuId');
    }
}
