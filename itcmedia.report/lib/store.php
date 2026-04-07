<?
namespace ITCMedia\Report;

use Bitrix;
use Bitrix\Main;
use Bitrix\Main\Type\DateTime;
use Bitrix\Sale\Internals\OrderTable;
use Bitrix\Sale\Internals\ShipmentTable;
use Bitrix\Sale\Internals\BasketTable;
use Bitrix\Sale\Internals\ShipmentItemStoreTable;
use Bitrix\Catalog\StoreTable;
use Bitrix\Catalog\StoreProductTable;
use Bitrix\Catalog\StoreDocumentTable;
use Bitrix\Catalog\StoreDocumentElementTable;
use Bitrix\Catalog\PriceTable;
use Bitrix\Catalog\ProductTable;

class Store 
{
    static $dateStart;
    static $dateEnd;
    static $cacheDb = [];
    static $priceId = 1;
    static $filters = [];

    public static function GetResult($filters = []) {
        self::$dateStart = new DateTime();
        self::$dateStart->add('-1 month');
        self::$dateEnd = new DateTime();

        self::validateFilter($filters);

        $arResult = [];
        $iblocks = self::getCatalogIblock();
        $arResult['IBLOCK_LIST'] = $iblocks ?? [];
        
        $sections = self::getSectionList($iblocks);
        $arResult['SECTION_LIST'] = $sections ?? [];

        $elements = self::getElements($iblocks, $sections);
        $arResult['ELEMENT_LIST'] = $elements ?? [];

        $stores = self::getStoreList();
        $arResult['STORE_LIST'] = $stores;

        $storesElement = self::getStoreProducts($elements);
        $arResult['STORE_ELEMENT_LIST'] = $storesElement;

        $orders = self::getOrders();
        $arResult['ORDER_LIST'] = $orders ?? [];

        $prices = self::getPriceList($elements);
        $arResult['PRICE_LIST'] = $prices ?? [];

        $basketBarcode = self::getBasketsBarcode($orders);
        $arResult['BASKET_BARCODE_LIST'] = $basketBarcode ?? [];

        $documentA = self::getDocument('A');
        $arResult['DOCUMENT_ARIVAL_LIST'] = $documentA ?? [];

        $documentD = self::getDocument('D');
        $arResult['DOCUMENT_DEDUCT_LIST'] = $documentD ?? [];

        $docs = array_merge($documentA, $documentD);
        $elementsDocument = self::getElementDocuments($docs);
        $arResult['DOCUMENT_ELEMENT_LIST'] = $elementsDocument ?? [];
        

        $arResult['COLUMNS'] = self::MakeColumn($arResult);

        $docsDeduct = array_column($arResult['DOCUMENT_DEDUCT_LIST'], 'ID');
        $docsArival = array_column($arResult['DOCUMENT_ARIVAL_LIST'], 'ID');
        $deducts = array_filter($arResult['DOCUMENT_ELEMENT_LIST'], function ($item) use ($docsDeduct, $store) {
            return in_array($item['DOC_ID'], $docsDeduct);
        });

        $arivals = array_filter($arResult['DOCUMENT_ELEMENT_LIST'], function ($item) use ($docsArival, $store) {
            return in_array($item['DOC_ID'], $docsArival);
        });

        $arResult['COLUMNS'][] = [
            'TYPE' => 'TOTAL',
            'NAME' => 'Всего:',
            'DEDUCT' => array_sum(array_column($deducts, 'AMOUNT')),
            'ARIVAL' => array_sum(array_column($arivals, 'AMOUNT')),
            'SALE' => array_sum(array_column($basketBarcode, 'QUANTITY')),
            'AVAILABLE' => array_sum(array_column($storesElement, 'AMOUNT')),
            'RESERVED' => array_sum(array_column($storesElement, 'QUANTITY_RESERVED')),
            'PRICE' => ''
        ];
        return $arResult;
    }

    protected static function validateFilter($filters) {
        if (isset($filters['INTERVAL_from'])) {
            self::$dateStart = new DateTime($filters['INTERVAL_from']);
            self::$filters['DATE'][0] = self::$dateStart;
        }
        if (isset($filters['INTERVAL_to'])) {
            self::$dateEnd = new DateTime($filters['INTERVAL_to']);
            self::$filters['DATE'][1] = self::$dateEnd;
        }
        if (isset($filters['STORE'])) {
            self::$filters['STORE'] = $filters['STORE'];
        }
        if (isset($filters['SECTIONS'])) {
            $sections = [];

            foreach ($filters['SECTIONS'] as $key => $value) {
                if (strpos($value, 'SECTION_') !== false) {
                    $sections[] = str_replace('SECTION_', '', $value);
                } else if (strpos($value, 'IBLOCK_') !== false) {
                    self::$filters['IBLOCK'][] = str_replace('IBLOCK_', '', $value);
                }
            }

            if ($sections) {
                $filterSections = [];
                foreach ($sections as $key => $value) {
                    $filterSections[] = $value;
                    self::getSectionChildList($value, $filterSections);
                }

                self::$filters['SECTION'] = $filterSections;
            }
        }
    }

    protected static function getElementsCatalog($in_stock) {
        return ProductTable::getList(['filter' => ['AVAILABLE' => $in_stock]])->fetchAll();
    }

    // -------------------- COLUMNS BUILD --------------------
    public static function MakeColumn($arResult) {
        $arColumns = [];
        $docsDeduct = array_column($arResult['DOCUMENT_DEDUCT_LIST'], 'ID');
        $docsArival = array_column($arResult['DOCUMENT_ARIVAL_LIST'], 'ID');
        foreach($arResult['STORE_LIST'] as $store) {

            $deducts = array_filter($arResult['DOCUMENT_ELEMENT_LIST'], function ($item) use ($docsDeduct, $store) {
                return in_array($item['DOC_ID'], $docsDeduct) && $item['STORE_FROM'] == $store['ID'];
            });

            $arivals = array_filter($arResult['DOCUMENT_ELEMENT_LIST'], function ($item) use ($docsArival, $store) {
                return in_array($item['DOC_ID'], $docsArival) && $item['STORE_FROM'] == $store['ID'];
            });

            $baskets = array_filter($arResult['BASKET_BARCODE_LIST'], function ($item) use ($store) {
                return $item['STORE_ID'] == $store['ID'];
            });

            $availables = array_filter($arResult['STORE_ELEMENT_LIST'], function ($item) use ($store) {
                return $item['STORE_ID'] == $store['ID'];
            });

            $arColumns[] = [
                'TYPE' => 'STORE',
                'NAME' => $store['TITLE'],
                'DEDUCT' => array_sum(array_column($deducts, 'AMOUNT')),
                'ARIVAL' => array_sum(array_column($arivals, 'AMOUNT')),
                'SALE' => array_sum(array_column($baskets, 'QUANTITY')),
                'AVAILABLE' => array_sum(array_column($availables, 'AMOUNT')),
                'RESERVED' => array_sum(array_column($availables, 'QUANTITY_RESERVED')),
                'PRICE' => ''
            ];
            self::getSectionColumn($store, $arResult, $arColumns);
        }

        return $arColumns;
    }

    protected static function getSectionColumn($store, $arResult, &$arColumns) {
        $sections = array_filter($arResult['SECTION_LIST'], function ($item) {
            return $item['IBLOCK_SECTION_ID'] == false;
        });
        
        $docsDeduct = array_column($arResult['DOCUMENT_DEDUCT_LIST'], 'ID');
        $docsArival = array_column($arResult['DOCUMENT_ARIVAL_LIST'], 'ID');

        foreach ($sections as $section) {
            $elements = array_filter($arResult['ELEMENT_LIST'], function ($item) use ($section) {
                return $item['IBLOCK_SECTION_ID'] == $section['ID'];
            });
            if ($elements) {
                $elementIds = array_column($elements, 'ID');

                $deducts = array_filter($arResult['DOCUMENT_ELEMENT_LIST'], function ($item) use ($docsDeduct, $store, $elementIds) {
                    return in_array($item['DOC_ID'], $docsDeduct) && in_array($item['ELEMENT_ID'], $elementIds) && $item['STORE_FROM'] == $store['ID'];
                });
    
                $arivals = array_filter($arResult['DOCUMENT_ELEMENT_LIST'], function ($item) use ($docsArival, $store, $elementIds) {
                    return in_array($item['DOC_ID'], $docsArival) && in_array($item['ELEMENT_ID'], $elementIds) && $item['STORE_FROM'] == $store['ID'];
                });

                $baskets = array_filter($arResult['BASKET_BARCODE_LIST'], function ($item) use ($store, $elementIds) {
                    return in_array($item['PRODUCT_ID'], $elementIds) && $item['STORE_ID'] == $store['ID'];
                });

                $availables = array_filter($arResult['STORE_ELEMENT_LIST'], function ($item) use ($store, $elementIds) {
                    return in_array($item['PRODUCT_ID'], $elementIds) && $item['STORE_ID'] == $store['ID'];
                });
    

                $arColumns[] = [
                    'TYPE' => 'SECTION',
                    'NAME' => $section['NAME_BREADCRUMB'],
                    'DEDUCT' => array_sum(array_column($deducts, 'AMOUNT')),
                    'ARIVAL' => array_sum(array_column($arivals, 'AMOUNT')),
                    'SALE' => array_sum(array_column($baskets, 'QUANTITY')),
                    'AVAILABLE' => array_sum(array_column($availables, 'AMOUNT')),
                    'RESERVED' => array_sum(array_column($availables, 'QUANTITY_RESERVED')),
                    'PRICE' => ''
                ];
            }
            self::getElementsColumn($arResult, $section['ID'], $store['ID'], $arColumns);
            self::getSectionChild($arResult, $section['ID'], $store['ID'], $arColumns);
        }
    }

    protected static function getSectionChild($arResult, $section_id, $store_id, &$arColumns) {
        $sections = array_filter($arResult['SECTION_LIST'], function ($item) use ($section_id) {
            return $item['IBLOCK_SECTION_ID'] == $section_id;
        });

        $docsDeduct = array_column($arResult['DOCUMENT_DEDUCT_LIST'], 'ID');
        $docsArival = array_column($arResult['DOCUMENT_ARIVAL_LIST'], 'ID');

        foreach ($sections as $section) {
            $elements = array_filter($arResult['ELEMENT_LIST'], function ($item) use ($section) {
                return $item['IBLOCK_SECTION_ID'] == $section['ID'];
            });
            if ($elements) {
                $elementIds = array_column($elements, 'ID');

                $deducts = array_filter($arResult['DOCUMENT_ELEMENT_LIST'], function ($item) use ($docsDeduct, $store_id, $elementIds) {
                    return in_array($item['DOC_ID'], $docsDeduct) && in_array($item['ELEMENT_ID'], $elementIds) && $item['STORE_FROM'] == $store_id;
                });
    
                $arivals = array_filter($arResult['DOCUMENT_ELEMENT_LIST'], function ($item) use ($docsArival, $store_id, $elementIds) {
                    return in_array($item['DOC_ID'], $docsArival) && in_array($item['ELEMENT_ID'], $elementIds) && $item['STORE_FROM'] == $store_id;
                });

                $baskets = array_filter($arResult['BASKET_BARCODE_LIST'], function ($item) use ($store_id, $elementIds) {
                    return in_array($item['PRODUCT_ID'], $elementIds) && $item['STORE_ID'] == $store_id;
                });
    
                $availables = array_filter($arResult['STORE_ELEMENT_LIST'], function ($item) use ($store_id, $elementIds) {
                    return in_array($item['PRODUCT_ID'], $elementIds) && $item['STORE_ID'] == $store_id;
                });
    

                $arColumns[] = [
                    'TYPE' => 'SECTION',
                    'NAME' => $section['NAME_BREADCRUMB'],
                    'DEDUCT' => array_sum(array_column($deducts, 'AMOUNT')),
                    'ARIVAL' => array_sum(array_column($arivals, 'AMOUNT')),
                    'SALE' => array_sum(array_column($baskets, 'QUANTITY')),
                    'AVAILABLE' => array_sum(array_column($availables, 'AMOUNT')),
                    'RESERVED' => array_sum(array_column($availables, 'QUANTITY_RESERVED')),
                    'PRICE' => ''
                ];
            }
            self::getElementsColumn($arResult, $section['ID'], $store_id, $arColumns);
            self::getSectionChild($arResult, $section['ID'], $store_id, $arColumns);
        }
    }

    protected static function getElementsColumn($arResult, $section_id, $store_id, &$arColumns) {
        $elements = array_filter($arResult['ELEMENT_LIST'], function ($item) use ($section_id) {
            return $item['IBLOCK_SECTION_ID'] == $section_id;
        });

        $docsDeduct = array_column($arResult['DOCUMENT_DEDUCT_LIST'], 'ID');
        $docsArival = array_column($arResult['DOCUMENT_ARIVAL_LIST'], 'ID');

        foreach ($elements as $element) {

            $deducts = array_filter($arResult['DOCUMENT_ELEMENT_LIST'], function ($item) use ($docsDeduct, $store_id, $element) {
                return in_array($item['DOC_ID'], $docsDeduct) && $item['ELEMENT_ID'] == $element['ID'] && $item['STORE_FROM'] == $store_id;
            });

            $arivals = array_filter($arResult['DOCUMENT_ELEMENT_LIST'], function ($item) use ($docsArival, $store_id, $element) {
                return in_array($item['DOC_ID'], $docsArival) && $item['ELEMENT_ID'] == $element['ID'] && $item['STORE_FROM'] == $store_id;
            });

            $baskets = array_filter($arResult['BASKET_BARCODE_LIST'], function ($item) use ($store_id, $element) {
                return $item['PRODUCT_ID'] == $element['ID'] && $item['STORE_ID'] == $store_id;
            });

            $availables = array_filter($arResult['STORE_ELEMENT_LIST'], function ($item) use ($store_id, $element) {
                return $item['PRODUCT_ID'] == $element['ID'] && $item['STORE_ID'] == $store_id;
            });
            if (isset($_GET['DEBUG']) && $baskets) {
                echo '<pre>'; print_r($baskets); echo '</pre>';
            }
            $sale = array_sum(array_column($baskets, 'QUANTITY'));

            $price = 0;
            $prices = array_filter($arResult['PRICE_LIST'], function ($item) use ($element) {
                return $item['PRODUCT_ID'] == $element['ID'];
            });

            if ($prices) {
                $prices = array_values($prices);
                $price = \CCurrencyLang::CurrencyFormat($prices[0]['PRICE'], $prices[0]['CURRENCY']);
            }

            $arColumns[] = [
                'TYPE' => 'ELEMENT',
                'NAME' => $element['NAME'] . ' [' . $element['ID'] . ']',
                'DEDUCT' => array_sum(array_column($deducts, 'AMOUNT')),
                'ARIVAL' => array_sum(array_column($arivals, 'AMOUNT')),
                'SALE' => $sale,
                'AVAILABLE' => array_sum(array_column($availables, 'AMOUNT')),
                'RESERVED' => array_sum(array_column($availables, 'QUANTITY_RESERVED')),
                'PRICE' => $price
            ];
        }
    }
    // ---------------------- DOCUMENT SECTIOIN --------------
    /**
     *  'A' = 'Приходная накладная'
     *  'S' = 'Оприходавание товара на склад'
     *  'M' = 'Перемещения товара между складами'
     *  'R' = 'Возврат товаров'
     *  'D' = 'Списание товара'
     *  'U' = 'Отмена резервирования'
     */
    protected static function getDocument($type) {
        return StoreDocumentTable::getList([
            'select' => ['ID', 'DATE_DOCUMENT', 'STATUS', 'WAS_CANCELLED', 'DOC_TYPE', 'TITLE'], 
            'filter' => [
                'STATUS' => 'Y', 
                'DOC_TYPE' => $type, 
                '>MAX_DATE_INSERT' => self::$dateStart->format(\Bitrix\Main\UserFieldTable::MULTIPLE_DATETIME_FORMAT),
                '<MAX_DATE_INSERT' => self::$dateEnd->format(\Bitrix\Main\UserFieldTable::MULTIPLE_DATETIME_FORMAT),
            ],
            'runtime' => [
                new \Bitrix\Main\Entity\ExpressionField('MAX_DATE_INSERT', 'MAX(%s)', 'DATE_DOCUMENT')
            ],
            'cache' => self::$cacheDb,
        ])->fetchAll();
    }

    protected static function getElementDocuments($docs) {
        $docIds = array_column($docs, "ID");
        if (empty($docIds)) return [];
        return StoreDocumentElementTable::getList([
            'filter' => ['@DOC_ID' => $docIds],
            'cache' => self::$cacheDb,
        ])->fetchAll();
    }

    // ---------------------- ORDER SECTION ------------------
    protected static function getOrders() {
        return OrderTable::getList([
            'select' => ['ID'],
            'filter' => [
                '>MAX_DATE_DEDUCTED' => self::$dateStart->format(\Bitrix\Main\UserFieldTable::MULTIPLE_DATETIME_FORMAT),
                '<MAX_DATE_DEDUCTED' => self::$dateEnd->format(\Bitrix\Main\UserFieldTable::MULTIPLE_DATETIME_FORMAT),
                'STATUS_ID' => 'F',
                'PAYED' => 'Y',
                'CANCELED' => 'N',
                // 'DEDUCTED' => 'Y'
            ],
            'runtime' => [
                new \Bitrix\Main\Entity\ExpressionField('MAX_DATE_DEDUCTED', 'MAX(%s)', 'DATE_INSERT')
            ],
            'cache' => self::$cacheDb,
        ])->fetchAll();
    }

    protected static function getBaskets($orders) {
        $orderIds = array_column($orders, "ID");

        return BasketTable::getList([
            'select' => ['ID', 'ORDER_ID', 'PRODUCT_ID', 'QUANTITY'],
            'filter' => ['@ORDER_ID' => $orderIds],
            'cache' => self::$cacheDb,
        ])->fetchAll();
    }

    protected static function getBasketsBarcode($orders) {
        $ordersIds = array_column($orders, 'ID');
        if (empty($ordersIds)) return [];

        return Table\ShipmentItemTable::getList([
            'select' => ['ID', 'BASKET_ID', 'STORE_ID', 'QUANTITY', 'PRODUCT_ID' => 'BASKET.PRODUCT_ID', 'ORDER_ID' => 'BASKET.ORDER_ID'],
            'filter' => ['@BASKET.ORDER_ID' => $ordersIds],
            'cache' => self::$cacheDb,
        ])->fetchAll();
    }
    
    // ---------------------- CATALOG SECTION -----------------
    protected static function getStoreList() {
        $filter = ['ACTIVE'>='Y'];
        if (self::$filters['STORE']) {
            $filter['@ID'] = self::$filters['STORE'];
        }
        return StoreTable::getList([
            'filter' => $filter,
            'cache' => self::$cacheDb,
        ])->fetchAll();
    }

    protected static function getStoreProducts($elements) {
        $elementIds = array_column($elements, 'ID');
        
        if (empty($elementIds)) return [];

        return  StoreProductTable::getList([
            'filter' => ['@PRODUCT_ID'=>$elementIds,'STORE.ACTIVE'=>'Y'],
            'cache' => self::$cacheDb, 
        ])->fetchAll();
    }

    // ---------------------- IBLOCK SECTION ------------------
    protected static function getElements($iblocks, $sections) {
        $sectionIds = array_column($sections, 'ID');
        $sectionIds[] = false;

        $iblockIds = array_column($iblocks, 'ID');
        $filter = ['@IBLOCK_ID' => $iblockIds, 'ACTIVE' => 'Y', '@IBLOCK_SECTION_ID' => $sectionIds];


        return Bitrix\Iblock\ElementTable::getList([
            'select' => ['ID', 'NAME', 'IBLOCK_SECTION_ID', 'IBLOCK_ID'],
            'filter' => $filter,
            'cache' => self::$cacheDb,
        ])->fetchAll();
    }

    protected static function getElementsFromSections($sections) {
        return Table\IblockSectionElementTable::getList([
            'filter' => ['@IBLOCK_SECTION_ID' => $sections],
            'cache' => self::$cacheDb,
        ])->fetchAll();
    }

    protected static function getCatalogIblock() {
        $iblockIds = Bitrix\Catalog\CatalogIblockTable::getList()->fetchAll();
        $ids = array_column($iblockIds, 'IBLOCK_ID');
        if (self::$filters['IBLOCK']) {
            $ids = self::$filters['IBLOCK'];
        }
        return Bitrix\Iblock\IblockTable::getList([
            'select' => ['ID', 'NAME'],
            'filter' => ['@ID' => $ids, 'ACTIVE' => 'Y'],
            'cache' => self::$cacheDb,
        ])->fetchAll();
    }

    protected static function getSectionList($iblocks) {
        $iblockIds = array_column($iblocks, 'ID');
        $filter = ['@IBLOCK_ID' => $iblockIds, 'ACTIVE' => 'Y'];
        if (self::$filters['SECTION']) {
            $filter['@ID'] = self::$filters['SECTION'];
        }
        $sectionList = Bitrix\Iblock\SectionTable::getList([
            'select' => ['ID', 'NAME', 'DEPTH_LEVEL', 'IBLOCK_SECTION_ID', 'IBLOCK_ID'],
            'filter' => $filter,
            'order' => ['DEPTH_LEVEL' => 'ASC'],
            'cache' => self::$cacheDb,
        ])->fetchAll();
        
        foreach ($sectionList as &$section) {
            $section['NAME_BREADCRUMB'] = self::buildSectionBreadcrumbs($section, $sectionList);
        }
        return $sectionList;
    }

    protected static function getSectionChildList($section_id, &$filterSections) {
        $sectionList = Bitrix\Iblock\SectionTable::getList([
            'select' => ['ID', 'IBLOCK_SECTION_ID'],
            'filter' => ['IBLOCK_SECTION_ID' => $section_id, 'ACTIVE' => 'Y'],
        ])->fetchAll();

        foreach ($sectionList as $key => $value) {
            $filterSections[] = $value['ID'];
            self::getSectionChildList($value['ID'], $filterSections);
        }
    }

    protected static function buildSectionBreadcrumbs($section, $sections) {
        $names = ['[' . $section['ID'] . '] ' . $section['NAME']];
        if ($section['IBLOCK_SECTION_ID']) {
            $parentSection = $section['IBLOCK_SECTION_ID'];
            while ($parentSection) {
                $search = array_filter($sections, function($item) use ($parentSection) {
                    return $item['ID'] == $parentSection;
                }, ARRAY_FILTER_USE_BOTH);

                if ($search) {
                    $search = array_shift($search);
                    $parentSection = $search['IBLOCK_SECTION_ID'];
                    $names[] = '[' . $search['ID'] . '] ' . $search['NAME'];
                } else {
                    break;
                }
            }
        }
        $names = array_reverse($names);
        return implode(' / ', $names);
    }

    protected static function getPriceList($elements) {
        $elementIds = array_column($elements, 'ID');
        if (empty($elementIds)) return [];

        return PriceTable::getList([
            'filter'=> ['CATALOG_GROUP_ID'=> self::$priceId, '@PRODUCT_ID' => $elementIds],
            'cache' => self::$cacheDb,
        ])->fetchAll();
    }
}