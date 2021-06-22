<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
global $APPLICATION;
?>
<div class="mindbox-product-price">
    <?if (!empty($arResult['MINDBOX_OLD_PRICE'])):?>
        <span class="mindbox-product-price__discount"><?=$arResult['MINDBOX_OLD_PRICE']?></span>
    <?endif;?>
    <?if (!empty($arResult['MINDBOX_PRICE'])):?>
        <span class="mindbox-product-price__price"><?=$arResult['MINDBOX_PRICE']?></span>
        <?if (isset($arParams['CURRENCY']) && !empty($arParams['CURRENCY'])):?>
            <?=$arParams['CURRENCY']?>
        <?endif;?>
    <?endif;?>
</div>
