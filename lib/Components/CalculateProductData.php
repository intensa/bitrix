<?php


namespace Mindbox\Components;


use Intensa\Logger\ILog;
use Mindbox\DTO\DTO;
use Mindbox\Helper;
use Mindbox\Options;
use \Bitrix\Main\Data\Cache;

class CalculateProductData
{
    const CACHE_TIME = 600;
    const CACHE_PREFIX = '';
    const MAX_CHUNK_VALUE = 50;
    protected $mindbox = null;
    protected $optionExternalSystem = '';
    protected $operationUnauthorized = '';
    protected $placeholderRegEx = '/{{(MINDBOX_BONUS|MINDBOX_PRICE|MINDBOX_OLD_PRICE)\|(\d+)\|(\d+)}}/m';
    protected $placeholdersList = [];

    public function __construct()
    {
        $this->mindbox = Options::getConfig();
        $this->optionExternalSystem = Options::getModuleOption('EXTERNAL_SYSTEM');
        $this->operationUnauthorized = Options::getOperationName('calculateUnauthorizedProduct');
    }

    public function handle(&$content)
    {
        $searchItems = $this->searchPlaceholder($content);

        if (\CModule::IncludeModule('intensa.logger')) {
            $logger = new ILog('mb_matches2');
        }

        $logger->log('$matches', $searchItems);
        if (!empty($searchItems) && is_array($searchItems)) {
            $requestProductList = [];

            foreach ($searchItems as $item) {
                $requestProductList[$item['id']] = [
                    'id' => $item['id'],
                    'price' => $item['price']
                ];
            }

            $mindboxResponse = [];

            if (count($requestProductList) > self::MAX_CHUNK_VALUE) {
                $requestChunk = array_chunk($requestProductList, self::MAX_CHUNK_VALUE);

                foreach ($requestChunk as $chunk) {
                    $mindboxResponse = array_merge($mindboxResponse, $this->requestOperation($chunk));
                }
            } else {
                $mindboxResponse = $this->requestOperation($requestProductList);
            }

            if (!empty($mindboxResponse)) {
                foreach ($mindboxResponse as $productId => $responseItem) {

                    if (!empty($responseItem['priceForCustomer'])) {
                        $requestProductList[$productId]['MINDBOX_PRICE'] = $responseItem['priceForCustomer'];
                        $requestProductList[$productId]['MINDBOX_OLD_PRICE'] = $responseItem['basePricePerItem'];
                    } else {
                        $requestProductList[$productId]['MINDBOX_PRICE'] = $responseItem['basePricePerItem'];
                    }

                    if (!empty($responseItem['appliedPromotions'])) {
                        foreach ($responseItem['appliedPromotions'] as $promotion) {
                            if ($promotion['type'] === 'earnedBonusPoints' && !empty($promotion['amount'])) {
                                $requestProductList[$productId]['MINDBOX_BONUS'] = $promotion['amount'];
                            }
                        }
                    }

                    $this->createProductCache($productId, $requestProductList[$productId]);
                }
            }

            $logger->log('$requestProductList', $requestProductList);

            // получили данные и делаем замену в контенте

            foreach ($searchItems as $placeholderKey => $replaceItem) {
                $replaceValue = '';

                if (
                    array_key_exists($replaceItem['id'], $requestProductList)
                && isset($requestProductList[$replaceItem['id']][$replaceItem['type']])
                ) {
                    $replaceValue = $requestProductList[$replaceItem['id']][$replaceItem['type']];
                }

                $content = str_replace($placeholderKey, $replaceValue, $content);
            }
        }
    }

    protected static function getCacheId($productId)
    {
        return self::CACHE_PREFIX . $productId;
    }

    public function createProductCache($productId, $data)
    {
        $cache = Cache::createInstance();
        $cache->initCache(self::CACHE_TIME, self::getCacheId($productId));
        $cache->startDataCache();
        $cache->endDataCache(['data' => $data]);
    }

    public static function getProductCache($productId)
    {
        $return = false;
        $cache = Cache::createInstance();

        if ($cache->initCache(self::CACHE_TIME, self::getCacheId($productId))) {
            $cacheVars = $cache->getVars();

            if (!empty($cacheVars['data'])) {
                $return = $cacheVars['data'];
            }
        }

        return $return;
    }

    public function searchPlaceholder($content)
    {
        $matches = [];
        preg_match_all($this->placeholderRegEx, $content, $matches, PREG_SET_ORDER, 0);

        $return = [];

        if (!empty($matches)) {
            foreach ($matches as $item) {
                $return[$item[0]] = [
                    'placeholder' => $item[0],
                    'type' => $item[1],
                    'id' => $item[2],
                    'price' => $item[3],
                ];
            }

            $this->placeholdersList = $return;
        }

        return $return;
    }

    protected function prepareDtoData($productList)
    {
        $return = [
            'productList' => [
                'items' => []
            ]
        ];

        foreach ($productList as $item) {

            if (!empty($item['id']) && !empty($item['price'])) {
                $return['productList']['items'][] = [
                    'product' => [
                        'ids' => [
                            $this->optionExternalSystem => $item['id']
                        ]
                    ],
                    'basePricePerItem' => $item['price']
                ];
            }
        }

        return $return;
    }

    protected function requestOperation($items)
    {
        $return = [];
        $prepareDtoData = $this->prepareDtoData($items);
        $dto = new DTO($prepareDtoData);

        try {
            $response = $this->mindbox->getClientV3()
                ->prepareRequest('POST', $this->operationUnauthorized, $dto, '', [], true)
                ->sendRequest()->getResult();
            if ($response) {
                $iconvResponse = Helper::iconvDTO($response, false);
                $responseStatus = $iconvResponse->getStatus();

                if ($responseStatus === 'Success') {
                    $responseProductList = $iconvResponse->getProductList()->getFieldsAsArray()[1];

                    foreach ($responseProductList as $item) {
                        $return[$item['product']['ids'][$this->optionExternalSystem]] = $item;
                    }
                }

            }

            return $return;
        } catch (\Exception $e) {
            //var_dump($e->getMessage());
        }
    }

    protected function __clone()
    {
    }

    public function __destruct()
    {
    }
}