<?php

namespace ITGaziev\WbPrice;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\CIBlock;
use Bitrix\Main\Sale;
use Bitrix\Highloadblock as HL; 
use Bitrix\Main\Entity;
use Bitrix\Catalog;
use Bitrix\Iblock;

use ITGaziev\WbPrice\Rules;

class ParseRule {
    static $module_id = 'itgaziev.wbprice';

    public static function initCondition($condition) {
        $all = $condition['values']['All'];
        $true = $condition['values']['True'];
        $filter['RULE'] = [
            'LOGIC' => $all,
        ];
        
        foreach ($condition['children'] as $children) {
            if ($children['controlId'] == 'CondGroupSub') {
                self::condSubGroup($children, $filter);
            } else {
                $field = self::parseControlId($children['controlId'], $filter);
                if ($field)
                    $filter['RULE'][] = self::getLogic($children['values']['logic'], $field, $children['values']['value']);
            }
        }
        return $filter;
    }
    
    public static function children($condition, &$filter) {

    }

    public static function condSubGroup($condition, &$filter) {
        $all = $condition['values']['All'];
        $true = $condition['values']['True'];
        $subGroup = [
            'LOGIC' => $all,
        ];

        foreach ($condition['children'] as $children) {
            $field = self::parseControlId($children['controlId'], $filter);
            if ($field) {
                $subGroup[] = self::getLogic($children['values']['logic'], $field, $children['values']['value']);
            }
        }

        $filter['RULE'][] = $subGroup;
    }

    public static function parseControlId($controlId, &$filter) {
        if ($controlId == 'CondIBElement') {
            return 'ID';
        } else if ($controlId == 'CondIBSection') {
            return 'IBLOCK_SECTION_ID';
        } else if ($controlId == 'CondIBXmlID') {
            return 'CODE';
        } else if ($controlId == 'CondIBCode') {
            return 'XML_ID';
        } else if ($controlId == 'CondIBName') {
            return 'NAME';
        } else if ($controlId == 'CondIBDateCreate') {
            return 'DATE_CREATE';
        } else if ($controlId == 'CondCatQuantity') {
            if (!isset($filter['REF']['CATALOG_PRODUCT'])) {
                $filter['REF']['CATALOG_PRODUCT'] = ['TABLE' => 'CATALOG_PRODUCT', 'KEY' => 'CATALOG_PRODUCT'];
            }
            return 'CATALOG_PRODUCT.QUANTITY';
        } else if (strpos($controlId, 'CondStore') !== false) {
            $idStore = str_replace('CondStore:', '', $controlId);
            if (!isset($filter['REF']['STORE_'. $idStore])) {
                $filter['REF']['STORE_'. $idStore] = ['TABLE' => 'STORE', 'KEY' => 'STORE_'. $idStore, 'ID' => $idStore];
            }
            return 'STORE_'. $idStore . '.AMOUNT';
        } else if (strpos($controlId, 'CondPrice') !== false) {
            $idPrice = str_replace('CondPrice:', '', $controlId);
            if (!isset($filter['REF']['PRICE_'. $idPrice])) {
                $filter['REF']['PRICE_'. $idPrice] = ['TABLE' => 'PRICE', 'KEY' => 'PRICE_'. $idPrice, 'ID' => $idPrice];
            }
            return 'PRICE_'. $idPrice . '.PRICE';
        } else if (strpos($controlId, 'CondProp') !== false) {
            $parse = explode(':', $controlId);
            if (!isset($filter['REF']['PROPERTY_'. end($parse)])) {
                $filter['REF']['PROPERTY_'. end($parse)] = ['TABLE' => 'PROPERTY', 'KEY' => 'PROPERTY_'. end($parse), 'ID' => end($parse)];
            }
            return 'PROPERTY_'. end($parse) . '.VALUE';
        }

        return false;
    }

    public static function getLogic($logic, $field, $value) {
        
        if ($logic == 'Equal') 
            return ['=' . $field => $value];

        if ($logic == 'Not')            
            return ['!' . $field => $value];

        if ($logic == 'Contain')
            return [$field => '%' . $value . '%'];

        if ($logic == 'NotCont')
            return ['!' . $field => '%' . $value . '%'];

        if ($logic == 'NotEmpty')
            return ['!' . $field => false];

        if ($logic == 'Empty')
            return [$field => false];

        if ($logic == 'Great')
            return ['>' . $field => $value];

        if ($logic == 'Less')
            return ['<' . $field => $value];

        if ($logic == 'EqGr')
            return ['>=' . $field => $value];
        
        if ($logic == 'EqLs')
            return ['<=' . $field => $value];

        return [$field => $value];
    }
}
