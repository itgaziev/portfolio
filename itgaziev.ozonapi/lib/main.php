<?php

namespace ITGaziev\OzonAPI;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\CIBlock;
use Bitrix\Main\Sale;
use Bitrix\Highloadblock as HL; 
use Bitrix\Main\Entity;

class Main {

    public static function getModuleName() {
        return 'itgaziev.ozonapi';
    }

    public static function getSettingsList() {
        \Bitrix\Main\Loader::includeModule('itgaziev.ozonapi');


        $rsData = Table\OzonSettingsTable::getList(array(
            'select' => array('ID', 'NAME'),
        ));
        $arID = [];
        while($arRes = $rsData->Fetch()) {
            $arID["REFERENCE"][] = '['.$arRes['ID'].'] ' . $arRes['NAME'];
            $arID["REFERENCE_ID"][] = $arRes['ID'];
        }
        return $arID;
    }

    public static function getIBlockList() {
        if(!Loader::includeModule('iblock'))
            return array();


        $arrIBlockTypes = array();
        $res = \CIBlock::GetList(array(), array(), true);
        while($ar_res = $res->Fetch())
        {
            $arrIBlockTypes["REFERENCE"][] = '['.$ar_res['ID'].'] ' . $ar_res['NAME'];
            $arrIBlockTypes["REFERENCE_ID"][] = $ar_res['ID'];
        }

        return $arrIBlockTypes;
    }

    public static function getOzonSections($settings_id = 0) {
        Loader::includeModule('itgaziev.ozonapi');

        $registry = new Api\Engine\Registry();

        $loader = new Api\Engine\Loader($registry);
        $registry->set('loader', $loader);
    
        $route = 'category/tree';
    
        $result = $loader->controller($route, ['settings_id' => $settings_id]);
        $arResult = json_decode($result, true);
        $arSections = [];
        self::buildOzonSections($arResult['result'], $arSections);
        return array('json' => $result, 'search' => json_encode($arSections));
    }

    public static function genTimeAgentList() {
        $times = array(
            0 => 'Не выгружать',
            1 => 'каждый 1 день',
            2 => 'каждые 2 дня',
            3 => 'каждые 4 дня',
            4 => '1 раз неделю',
        );
        $arTimes = [];
        foreach($times as $id => $name) {
            $arTimes["REFERENCE"][] = $name;
            $arTimes["REFERENCE_ID"][] = $id;
        }

        return $arTimes;
    }

    public static function getProperties($iblock) {
        Loader::includeModule('iblock');
        $res = \CIBlock::GetProperties($iblock, array(), array());
        $result = [];

        $result['price'][] = ['id' => 'self', 'text' => "Свое значение"];
        //$result['price'][] = array('id' => 'CATALOG_PRICE_MIN_SALE', 'text' => 'Цена со скидкой');
        //$result['price'][] = array('id' => 'CATALOG_PRICE_BASE', 'text' => 'Базовая: Цена');

        $dbPriceType = \CCatalogGroup::GetList(array("SORT" => "ASC"), array());
        while ($arPriceType = $dbPriceType->Fetch())
        {
            $result['price'][] = array('id' => $arPriceType['NAME'], 'text' => $arPriceType['NAME_LANG']);
        }
        $result['price'][] = array('id' => 'PRICE_SALE', 'text' => 'Цена со скидкой');


        //TODO: unit weight
        $result['unit'][] = array('id' => 'g', 'text' => 'грамм');
        $result['unit'][] = array('id' => 'kg', 'text' => 'килограмм');
        $result['unit'][] = array('id' => 'lb', 'text' => 'фунты');

        //TODO: vat
        $result['vat'][] = array('id' => '0', 'text' => 'Не облагается');
        $result['vat'][] = array('id' => '0.1', 'text' => '10%');
        $result['vat'][] = array('id' => '0.2', 'text' => '20%');

        //TODO: unit dimension
        $result['dimension'][] = array('id' => 'mm', 'text' => 'мм');
        $result['dimension'][] = array('id' => 'cm', 'text' => 'см');
        $result['dimension'][] = array('id' => 'in', 'text' => 'дюймы');


        $result['attribute'][0]['text'] = 'Параметры элемента';
        $result['attribute'][0]['children'] = array(
            ['id' => 'self', 'text' => "Свое значение"],
            ['id' => 'PREVIEW_PICTURE', 'text' => "Изображения анонса"],
            ['id' => 'DETAIL_PICTURE', 'text' => "Детальное изображения"],
            ['id' => 'PREVIEW_TEXT', 'text' => "Короткое описание"],
            ['id' => 'DETAIL_TEXT', 'text' => "Детальное описание"],
        );
        $result['attribute'][1]['text'] = 'Свойство элемента';
        $result['attribute'][1]['children'][] = ['id' => 'self', 'text' => "Свое значение"];

        while($res_arr = $res->Fetch()) {
            $result['attribute'][1]['children'][] = array('id' => 'PROPERTY[' . $res_arr['CODE'].']', 'text' => '[' . $res_arr['ID'] . '] ' . $res_arr['NAME']);
        }
        return $result;
    }

    public static function getAttributeCategory($category_id, $settings_id = 0) {
        Loader::includeModule('itgaziev.ozonapi');

        $registry = new Api\Engine\Registry();

        $loader = new Api\Engine\Loader($registry);
        $registry->set('loader', $loader);
    
        $route = 'attributes/category';
        
        $resultRequired = $loader->controller($route, ['category_id' => $category_id, 'attribute_type' => 'required', 'settings_id' => $settings_id]);
        $resultOptional = $loader->controller($route, ['category_id' => $category_id, 'attribute_type' => 'optional', 'settings_id' => $settings_id]);
        $arResult['required'] = json_decode($resultRequired, true)['result'];
        $arResult['optional'] = json_decode($resultOptional, true)['result'];
        return array('json' => $arResult);
    }

    public static function getConditionRuleSelect($iblock) {
        $condition = ['list' => [], 'json' => ''];

        $boolean = ['result' => [['id' => 'Y', 'text' => 'Да'], ['id' => 'N', 'text' => 'Нет']]];
        $select[0]['id'] = 'product';
        $select[0]['text'] = 'Поля и характеристики товара';
        $select[0]['children'] = [
            [   
                'id' => 'ID', 
                'text' => 'Товар', 
                'selected' => true,
                'condition' => ['type' => 'product-list', 'compare' => self::getComapreByType('product-list'), 'controll' => 'product-search'],
                'group' => 'product',
            ], [   
                'id' => 'NAME', 
                'text' => 'Название',
                'selected' => false,
                'condition' => ['type' => 'string', 'compare' => self::getComapreByType('string'), 'controll' => 'none'],
                'group' => 'product',
            ], [   
                'id' => 'ACTIVE', 
                'text' => 'Активность',
                'selected' => false,
                'condition' => ['type' => 'list', 'compare' => self::getComapreByType('boolean'), 'controll' => 'none', 'data' => $boolean ],
                'group' => 'product',
            ], [
                'id' => 'SECTION_ID', 
                'text' => 'Раздел',
                'condition' => ['type' => 'product-section', 'compare' => self::getComapreByType('product-section'), 'controll' => 'product-section'],
                'group' => 'product',
            ], [   
                'id' => 'CODE', 
                'text' => 'Символьный код',
                'selected' => false,
                'condition' => ['type' => 'string', 'compare' => self::getComapreByType('string'), 'controll' => 'none'],
                'group' => 'product',
            ], [   
                'id' => 'PREVIEW_PICTURE', 
                'text' => 'Картинка для анонса',
                'selected' => false,
                'condition' => ['type' => 'files', 'compare' => self::getComapreByType('files'), 'controll' => 'none'],
                'group' => 'product',
            ], [
                'id' => 'PREVIEW_TEXT', 
                'text' => 'Описание для анонса',
                'selected' => false,
                'condition' => ['type' => 'files', 'compare' => self::getComapreByType('files'), 'controll' => 'none'],
                'group' => 'product',
            ], [   
                'id' => 'DETAIL_PICTURE', 
                'text' => 'Детальная картинка',
                'selected' => false,
                'condition' => ['type' => 'files', 'compare' => self::getComapreByType('files'), 'controll' => 'none'],
                'group' => 'product',
            ], [
                'id' => 'DETAIL_TEXT', 
                'text' => 'Детальное описание',
                'selected' => false,
                'condition' => ['type' => 'files', 'compare' => self::getComapreByType('files'), 'controll' => 'none'],
                'group' => 'product',
            ], [
                'id' => 'EXTERNAL_ID', 
                'text' => 'Внешний код',
                'selected' => false,
                'condition' => ['type' => 'string', 'compare' => self::getComapreByType('string'), 'controll' => 'none'],
            ]
        ];
        $select[1]['id'] = 'price';
        $select[1]['text'] = 'Цена';

        $dbPriceType = \CCatalogGroup::GetList(array("SORT" => "ASC"));

        while ($ar_res = $dbPriceType->Fetch()) {
            $select[1]['children'][] = [
                'id' => 'CATALOG_PRICE_SCALE_' . $ar_res['ID'], 
                'text' => $ar_res['NAME_LANG'],
                'selected' => false,
                'condition' => ['type' => 'integer', 'compare' => self::getComapreByType('integer'), 'controll' => 'none'],
            ];
        }

        $select[2]['id'] = 'catalog';
        $select[2]['text'] = 'Продукт';
        $select[2]['children'] = [
            [
                'id' => 'CATALOG_AVAILABLE', 
                'text' => 'Доступность',
                'selected' => false,
                'condition' => ['type' => 'list', 'compare' => self::getComapreByType('boolean'), 'controll' => 'none', 'data' => $boolean],
            ],
            [
                'id' => 'CATALOG_QUANTITY', 
                'text' => 'Доступное количество',
                'selected' => false,
                'condition' => ['type' => 'integer', 'compare' => self::getComapreByType('integer'), 'controll' => 'none'],
            ],
        ];

        Loader::includeModule('iblock');
        Loader::includeModule("highloadblock");
        $res = \CIBlock::GetProperties($iblock, array(), array());
        $select[3]['id'] = 'attribute';
        $select[3]['text'] = 'Свойство инфоблока';

        while($res_arr = $res->Fetch()) {
            if($res_arr['PROPERTY_TYPE'] == 'E' || $res_arr['PROPERTY_TYPE'] == 'G') continue;

            $typeCondition = 'string';
            $data = array();
            $data['property'] = $res_arr;
            $code = 'PROPERTY_' . $res_arr['CODE'];

            if($res_arr['PROPERTY_TYPE'] == 'N') $typeCondition = 'integer';
            elseif($res_arr['PROPERTY_TYPE'] == 'S' && $res_arr['USER_TYPE'] == 'directory' && !empty($res_arr['USER_TYPE_SETTINGS']['TABLE_NAME'])) {
                $typeCondition = 'list';    
                $code = 'PROPERTY_' . $res_arr['CODE'] . '.ID';

                $hlblock = HL\HighloadBlockTable::getList(['filter' => ['TABLE_NAME' => $res_arr['USER_TYPE_SETTINGS']['TABLE_NAME']]])->fetch();
                if($hlblock){
                    $hlClassName = (HL\HighloadBlockTable::compileEntity($hlblock))->getDataClass();

                    $result = $hlClassName::getList(array(
                        "select" => array("*"),
                        "order" => array("ID"=>"DESC"),                
                    ));
                    
                    while ($arRow = $result->Fetch()) $data['result'][] = ['id' => $arRow["ID"], 'text' => $arRow["UF_NAME"]];
                }
            } elseif($res_arr['PROPERTY_TYPE'] == 'L') {
                $typeCondition = 'list'; 
                $code = 'PROPERTY_' . $res_arr['CODE'] . '.ID';

                $property_enums = \CIBlockPropertyEnum::GetList(Array("DEF"=>"DESC", "SORT"=>"ASC"), Array("IBLOCK_ID"=>$iblock, "CODE"=> $res_arr['CODE']));
                while($enum_fields = $property_enums->GetNext())
                {
                  $data['result'][] = ['id' => $enum_fields["ID"], 'text' => $enum_fields["VALUE"]];
                }
            }
            elseif($res_arr['PROPERTY_TYPE'] == 'F') $typeCondition = 'files'; 

            $select[3]['children'][] = array(
                'id' => $code, 
                'text' => '[' . $res_arr['ID'] . '] ' . $res_arr['NAME'],
                'selected' => false,
                'condition' => ['type' => $typeCondition, 'compare' => self::getComapreByType($typeCondition), 'controll' => 'attribute', 'data' => $data],
            );


        }

        foreach($select as $group) {
            foreach($group['children'] as $item) { 
                $item['option-group'] = $group['id']; 
                $condition['list'][$item['id']] = $item; 
            }
        }
        $condition['json'] = $select;
        return $condition;
    }

    private static function getComapreByType($type) {
        $list = [
            ['id' => 'mask', 'text' => 'Содержит'],
            ['id' => 'equals', 'text' => 'Равно'],
            ['id' => 'not-equals', 'text' => 'Не равно'],
            ['id' => 'empty', 'text' => 'Пусто'],
            ['id' => 'not-empty', 'text' => 'Не пусто'],
        ];
        switch($type) {
            case 'product-list':
                $list = [
                    ['id' => 'in', 'text' => 'Один из'],
                    ['id' => 'not-in', 'text' => 'Не один из'],
                ];
                break;
            case 'product-section':
                $list = [
                    ['id' => 'in', 'text' => 'Один из'],
                    ['id' => 'not-in', 'text' => 'Не один из'],
                ];
                break;
            case 'list':
                $list = [
                    ['id' => 'in', 'text' => 'Один из'],
                    ['id' => 'not-in', 'text' => 'Не один из'],
                    ['id' => 'empty', 'text' => 'Пусто'],
                    ['id' => 'not-empty', 'text' => 'Не пусто'],
                ];
                break;
            case 'string':
                $list = [
                    ['id' => 'mask', 'text' => 'Содержит'],
                    ['id' => 'equals', 'text' => 'Равно'],
                    ['id' => 'not-equals', 'text' => 'Не равно'],
                    ['id' => 'empty', 'text' => 'Пусто'],
                    ['id' => 'not-empty', 'text' => 'Не пусто'],
                ];
                break;
            case 'integer':
                $list = [
                    ['id' => 'equals', 'text' => 'Равно'],
                    ['id' => 'not-equals', 'text' => 'Не равно'],
                    ['id' => 'over', 'text' => 'Больше'],
                    ['id' => 'less', 'text' => 'Меньше'],
                    ['id' => 'empty', 'text' => 'Пусто'],
                    ['id' => 'not-empty', 'text' => 'Не пусто'],
                ];
                break;
            case 'files':
                $list = [
                    ['id' => 'empty', 'text' => 'Пусто'],
                    ['id' => 'not-empty', 'text' => 'Не пусто'],
                ];
                break;
            case 'boolean':
                $list = [
                    ['id' => 'bool-equal', 'text' => 'Равно'],
                    ['id' => 'bool-not-equal', 'text' => 'Не равно'],
                ];
                break;
        }

        return $list;
    }

    public static function convertSavedFilter($condition, $saved) {
        $result = [];
        $boolean = ['result' => [['id' => 'Y', 'text' => 'Да'], ['id' => 'N', 'text' => 'Нет']]];

        foreach($saved['RULE'] as $groupID => $group) {
            $rules = [];
            foreach($group as $index => $item) {
                $data = $condition[$item['type']];
                $logic = ['type' => $item['type'], 'compare' => $item['compare'], 'value' => '', 'condition' => $data['condition'], 'data' => $data];
                
                foreach($data['condition']['compare'] as $compare) {
                    if($compare['id'] == $item['compare']) $logic['compare'] = $compare;
                }
                $logic['condition'] = $data['condition'];
                switch($item['compare']) {
                    case 'in': 
                        if($item['type'] == 'SECTION_ID') $logic['value'] = self::getSectionList($item['value'], $saved['IBLOCK']);
                        else if($item['type'] == 'ID') $logic['value'] = self::getElementList($item['value'], $saved['IBLOCK']);
                        else $logic['value'] = self::getAttributeEnum($item['value'], $data);
                        break;
                    case 'not-in': 
                        if($item['type'] == 'SECTION_ID') $logic['value'] = self::getSectionList($item['value'], $saved['IBLOCK']);
                        else if($item['type'] == 'ID') $logic['value'] = self::getElementList($item['value'], $saved['IBLOCK']);
                        else $logic['value'] = self::getAttributeEnum($item['value'], $data);
                        break;
                    case 'bool-equal': 
                        if($item['value'] == 'Y') $logic['value'] = ['id' => 'Y', 'text' => 'Да'];
                        else if($item['value'] == 'N') $logic['value'] = ['id' => 'N', 'text' => 'Нет'];
                        break;
                    case 'bool-not-equal': 
                        if($item['value'] == 'Y') $logic['value'] = ['id' => 'Y', 'text' => 'Да'];
                        else if($item['value'] == 'N') $logic['value'] = ['id' => 'N', 'text' => 'Нет'];                        
                        break;
                    default: $logic['value'] = $item['value'];
                }
                $rules[$index] = $logic;
            }
            $result[] = ['group_id' => $groupID, 'rule' => $rules];
        }

        return $result;
    }

    private static function getSectionList($values, $iblock) {
        $arFilter = array(
            "IBLOCK_ID" => $iblock,
            "ID" =>  $values,
        );
        $arSelect = array("ID", "NAME");

        $res = \CIBlockSection::GetList(array(), $arFilter, false, $arSelect);

        while($ar_fields = $res->GetNext()) $result[] = ['id' => $ar_fields['ID'], 'text' => '[' . $ar_fields['ID'] . '] ' . $ar_fields['NAME']];

        return $result;
    }

    private static function getElementList($values, $iblock) {
        $arFilter = array(
            "IBLOCK_ID" => $iblock,
            "ID" => $values,
        );
        $arSelect = array("ID", "NAME");
        $res = \CIBlockElement::GetList(array(), $arFilter, false, array(), $arSelect);

        while($ar_fields = $res->GetNext()) $result[] = ['id' => $ar_fields['ID'], 'text' => '[' . $ar_fields['ID'] . '] ' . $ar_fields['NAME']];

        return $result;
    }

    private static function getAttributeEnum($values, $data) {
        $result = [];
        foreach($data['condition']['data']['result'] as $item) {
            if(in_array($item['id'], $values)) $result[] = $item;
        }
    }

    private static function buildOzonSections($sections, &$arSections) {
        foreach($sections as $section) {
            if(!empty($section['children'])) {
                $route = array();
                $route['name'] = $section['title'];
                $route['id'][0] = $section['category_id'];
                $arSections['options'][] = $route;
                $arSections['tags'][] = $route['name'];
                foreach($section['children'] as $level2) {
                    $route = array();
                    $route['name'] = $section['title'] . '/' . $level2['title'];
                    $route['id'][0] = $section['category_id'];
                    $route['id'][1] = $level2['category_id'];
                    $arSections['options'][] = $route;
                    $arSections['tags'][] = $route['name'];
                    foreach($level2['children'] as $level3) {
                        $route = array();
                        $route['name'] = $section['title'] . '/' . $level2['title'] . '/' . $level3['title'];
                        $route['id'][0] = $section['category_id'];
                        $route['id'][1] = $level2['category_id'];
                        $route['id'][2] = $level3['category_id'];
                        $arSections['options'][] = $route;
                        $arSections['tags'][] = $route['name'];
                    }
                }
            }
        }
    }

    public static function convertRuleToFilter($rules) {
        $arPost = [];
        foreach($rules['RULE'] as $rule) {
            $arLogic = [];
            foreach($rule as $item) {
                
                //TODO: Event Rigistry convert simple value to new
                if($item['type'] == 'PROPERTY_BRAND.ID') {
                    
                }

                switch($item['compare']) {
                    case 'in': 
                        $arLogic[$item['type']] = $item['value'];
                        break;
                    case 'not-in': 
                        $arLogic['!' . $item['type']] = $item['value'];
                        break;
                    case 'mask': 
                        $arLogic[$item['type']] = '%' . $item['value'] . '%';
                        break;
                    case 'bool-equal': 
                        $arLogic[$item['type']] = $item['value'];
                        break;
                    case 'bool-not-equal': 
                        $arLogic['!' . $item['type']] = $item['value'];
                        break;
                    case 'empty': 
                        $arLogic[$item['type']] = false;
                        break;
                    case 'not-empty': 
                        $arLogic['!' . $item['type']] = false;
                        break;
                    case 'over': 
                        $arLogic['>' . $item['type']] = $item['value'];
                        break;
                    case 'equals': 
                        $arLogic['=' . $item['type']] = $item['value'];
                        break;
                    case 'not-equals': 
                        $arLogic['!' . $item['type']] = $item['value'];
                        break;
                    case 'less': 
                        $arLogic['<' . $item['type']] = $item['value'];
                        break;
                }
            }
            $arLogic['INCLUDE_SUBSECTIONS'] = 'Y';
            $arLogic['ACTIVE'] = 'Y';
            $arPost[] = $arLogic;
        }
        $arCondition = ['IBLOCK' => $rules['IBLOCK'], ['LOGIC' => 'OR', $arPost]];

        return $arCondition;
    }

    public static function getCountProductFilter($arFilter) {
        \CModule::IncludeModule("iblock");

        $res = \CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "NAME"));
        $count = $res->SelectedRowsCount();

        return $count;
    }

    public static function convertFieldsToOzon($arFields, $condition) {
        $arSelected = [];
        $arFieldsElement = ['PREVIEW_PICTURE', 'DETAIL_PICTURE', 'PREVIEW_TEXT', 'DETAIL_TEXT'];

        $arSelected['offer_id'] = $arFields['ID'];
        $arSelected['name'] = $arFields['NAME']; //attribute
        if($condition['price']['select']) {
            $arSelected['price'] = self::getPriceOzon($condition['price']['select']); //price
        }

        if($condition['old_price']['select']) {
            $arSelected['old_price'] = self::getPriceOzon($condition['old_price']['select']);; //price
        }

        if($condition['premium_price']['select']) {
            $arSelected['premium_price'] = self::getPriceOzon($condition['premium_price']['select']);; //price
        }    

        $arSelected['vat'] = $condition['vat']['select'];

        if(in_array($condition['description']['select'], $arFieldsElement)) { //FIELDS ELEMENT
            $arSelected['description'] = $arFields[$condition['description']['select']];
        } else if($condition['description']['select'] == 'self') { //TEXT INPUT
            $arSelected['description'] = $condition['description']['input'];
        } else if($condition['description']['select']) { //PROPERTY
            $arSelected['description'] = self::getPropertiesValue($condition['description']['select'], $arFields, false);
        }

        if(in_array($condition['file_name']['select'], $arFieldsElement)) { //FIELDS ELEMENT
            $arSelected['file_name'] = $arFields[$condition['file_name']['select']];
        } else if($condition['file_name']['select'] == 'self') { //TEXT INPUT
            $arSelected['file_name'] = $condition['file_name']['input'];
        } else if($condition['file_name']['select']) { //PROPERTY
            $arSelected['file_name'] = self::getPropertiesValue($condition['file_name']['select'], $arFields, false);
        }

        if(in_array($condition['images']['select'], $arFieldsElement) && $arFields[$condition['images']['select']]) { //FIELDS ELEMENT
            $arSelected['images'] = [ 
                \CFile::GetPath($arFields[$condition['images']['select']]) 
            ];
        } else if($condition['images']['select'] == 'self') { //TEXT INPUT
            $arSelected['images'] = $condition['images']['input'];
        } else if($arFields[$condition['images']['select']]) { //PROPERTY
            $arSelected['images'] = self::getPropertiesValue($condition['images']['select'], $arFields, true);
        }

        if(in_array($condition['depth']['select'], $arFieldsElement)) { //FIELDS ELEMENT
            $arSelected['depth'] = $arFields[$condition['depth']['select']];
        } else if($condition['depth']['select'] == 'self') { //TEXT INPUT
            $arSelected['depth'] = $condition['depth']['input'];
        } else if($condition['depth']['select']) { //PROPERTY
            $arSelected['depth'] = self::getPropertiesValue($condition['depth']['select'], $arFields, false);
        }

        if(in_array($condition['width']['select'], $arFieldsElement)) { //FIELDS ELEMENT
            $arSelected['width'] = $arFields[$condition['width']['select']];
        } else if($condition['width']['select'] == 'self') { //TEXT INPUT
            $arSelected['width'] = $condition['width']['input'];
        } else if($condition['width']['select']) { //PROPERTY
            $arSelected['width'] = self::getPropertiesValue($condition['width']['select'], $arFields, false);
        }

        if(in_array($condition['height']['select'], $arFieldsElement)) { //FIELDS ELEMENT
            $arSelected['height'] = $arFields[$condition['height']['select']];
        } else if($condition['height']['select'] == 'self') { //TEXT INPUT
            $arSelected['height'] = $condition['height']['input'];
        } else if($condition['height']['select']) { //PROPERTY
            $arSelected['height'] = self::getPropertiesValue($condition['height']['select'], $arFields, false);
        }

        if(in_array($condition['weight']['select'], $arFieldsElement)) { //FIELDS ELEMENT
            $arSelected['weight'] = $arFields[$condition['weight']['select']];
        } else if($condition['weight']['select'] == 'self') { //TEXT INPUT
            $arSelected['weight'] = $condition['weight']['input'];
        } else if($condition['weight']['select']) { //PROPERTY
            $arSelected['weight'] = self::getPropertiesValue($condition['weight']['select'], $arFields, false);
        }

        $arSelected['dimension_unit'] = $condition['dimension_unit']['select'];
        $arSelected['weight_unit'] = $condition['weight_unit']['select'];

        foreach($condition['attr'] as $O_ID => $PROP) {

            if($PROP['select'] == 'self') {
                $arSelected['attributes'][] = [
                    'id' => $O_ID, 
                    'values' => [['value' => $PROP['input']]]
                ]; 
            } else if(strpos($PROP['select'], 'PROPERTY[') !== false) {
                $arSelected['attributes'][] = [
                    'id' => $O_ID,
                    'values' => self::getPropertiesValue($PROP['select'], $arFields, true)
                ];
            }

        }

        foreach($condition['optional'] as $O_ID => $PROP) {

            if($PROP['select'] == 'self') {
                $arSelected['attributes'][] = [
                    'id' => $O_ID, 
                    'values' => [['value' => $PROP['input']]]
                ]; 
            } else if(strpos($PROP['select'], 'PROPERTY[') !== false) {
                $arSelected['attributes'][] = [
                    'id' => $O_ID,
                    'values' => self::getPropertiesValue($PROP['select'], $arFields, true)
                ];
            }

        }

        return $arSelected;
    }

    private static function getPriceOzon($CODE, $ID) {
        $price = ['VALUE' => 0, 'CURRENCY' => 'RUB'];

        if($CODE == 'PRICE_SALE') {
            $price = self::GetPrice($ID);
        } else {
            $dbPriceType = \CCatalogGroup::GetList(array("SORT" => "ASC"), array());
            while ($arPriceType = $dbPriceType->Fetch()) {
                if($CODE == $arPriceType['NAME']) {
                    $ar_res = $db_res = \CPrice::GetList(array(), array("PRODUCT_ID" => $ID, "CATALOG_GROUP_ID" => $arPriceType['ID']));
                    if ($ar_res = $db_res->Fetch()) {
                        $price['VALUE'] = $ar_res["PRICE"];
                        $price['CURRENCY'] = $ar_res["CURRENCY"];
                        break;
                    }
                }
            }
        }

        return $price['VALUE'];
    }

    public static function getPropertiesValue($CODE, $FIELDS, $IS_MULTIPLE = false) {
        $CODE_PROP = str_replace(array('PROPERTY[', ']'), array('', ''), $code['select']);
        if(isset($FIELDS['PROPERTY'][$CODE_PROP])) {
            $PROP = $FIELDS['PROPERTY'][$CODE_PROP];
            $arResult = [];
            if($PROP['PROPERTY_TYPE'] == 'S' && !empty($PROP['USER_TYPE_SETTINGS']['TABLE_NAME'])) {
                $d_value = \CIBlockFormatProperties::GetDisplayValue(array(), $PROP);
                return is_array($d_value) ? $d_value : [$d_value];
            } else if($PROP['PROPERTY_TYPE'] == 'S') {
                return is_array($PROP['VALUE']) ? $PROP['VALUE'] : [$PROP['VALUE']];
            } else if($PROP['PROPERTY_TYPE'] == 'L') {
                return is_array($PROP['VALUE']) ? $PROP['VALUE'] : [$PROP['VALUE']];
            } else if($PROP['PROPERTY_TYPE'] == 'E') {
                $d_value = \CIBlockFormatProperties::GetDisplayValue(array(), $PROP);
                return is_array($d_value) ? $d_value : [$d_value];
            } else if($PROP['PROPERTY_TYPE'] == 'F') {

            }
        }
    }

    public static function getListProduct($arFilter) {
        //$arFilter = $arCondition;
        $res = \CIBlockElement::GetList(array(), $arFilter, false, array(), $arSelect);

        while($ar_fields = $res->GetNextElement()) {

            $arProps = $ar_fields->GetProperties();
            $arFields = $ar_fields->GetFields();
            $arFields['PROPERTY'] = $arProps;
            $arPrice = [];

            $allPrices = \CPrice::GetList(array(), array("PRODUCT_ID" => $arFields["ID"]));
            while ($ar_price = $allPrices->Fetch()) {

                $arDiscounts = \CCatalogDiscount::GetDiscountByPrice($ar_price["ID"], [2], "N", 's1');
                $discountPrice = \CCatalogProduct::CountPriceWithDiscount($ar_price["PRICE"], $ar_price["CURRENCY"], $arDiscounts);
                $ar_price["DISCOUNT_PRICE"] = self::GetPrice($arFields['ID']);
                $arPrice = $ar_price;

            }

            $arSelected = [];
            $arSelected['offer_id'] = $arFields["ID"];
            $arSelected['name'] = $arFields["NAME"];
            $arSelected['price'] = $arPrice['DISCOUNT_PRICE'];
            $arSelected['old_price'] = $arPrice['PRICE'];
            $arSelected['premium_price'] = '';
            $arSelected['vat'] = $selected['vat']['select'];
            if(!empty($selected['description']['select']) && $selected['description']['select'] != 'self') {
                
                if(strpos($selected['description']['select'], 'PROPERTY[') !== false) {
                    $CODE_PROP = str_replace(array('PROPERTY[', ']'), array('', ''), $selected['description']['select']);
                    $arSelected['description'] = $arFields['PROPERTY'][$CODE_PROP]['VALUE'];
                } else if(isset($arFields[$selected['description']['select']])) {
                    $arSelected['description'] = $arFields[$selected['description']['select']];
                }

            } else if($selected['description']['select'] == 'self') $arSelected['description'] = $selected['description']['input'];

            if(!empty($selected['file_name']['select']) && $selected['file_name']['select'] != 'self') {

                if(strpos($selected['file_name']['select'], 'PROPERTY[') !== false) {
                    $CODE_PROP = str_replace(array('PROPERTY[', ']'), array('', ''), $selected['file_name']['select']);
                    $PROP = $arFields['PROPERTY'][$CODE_PROP];
                    $VALUE = '';
                    if($PROP['PROPERTY_TYPE'] == 'F' && !is_array($PROP['VALUE']) && !empty($PROP['VALUE'])) {
                        $VALUE = \CFile::GetPath($PROP['VALUE']);
                    } else if($PROP['PROPERTY_TYPE'] == 'F' && !empty($PROP['VALUE'])) {
                        $VALUE = \CFile::GetPath($PROP['VALUE'][0]);
                    } else {
                        $VALUE = $PROP['VALUE'];
                    }
                    $arSelected['file_name'] = $VALUE;
                } else if(isset($arFields[$selected['file_name']['select']])) {
                    $field = $selected['file_name']['select'];
                    if(in_array($field, array('DETAIL_PICTURE', 'PREVIEW_PICTURE'))) {
                        $arSelected['file_name'] = \CFile::GetPath($arFields[$selected['file_name']['select']]);
                    }
                }

            } else if($selected['file_name']['select'] == 'self') $arSelected['file_name'] = $selected['file_name']['input'];

            if(!empty($selected['images']['select']) && $selected['images']['select'] != 'self') {
                
                if(strpos($selected['images']['select'], 'PROPERTY[') !== false) {
                    $CODE_PROP = str_replace(array('PROPERTY[', ']'), array('', ''), $selected['images']['select']);
                    $PROP = $arFields['PROPERTY'][$CODE_PROP];
                    $VALUE = [];
                    if($PROP['PROPERTY_TYPE'] == 'F' && !is_array($PROP['VALUE']) && !empty($PROP['VALUE'])) {
                        $VALUE[] = \CFile::GetPath($PROP['VALUE']);
                    } else if($PROP['PROPERTY_TYPE'] == 'F' && !empty($PROP['VALUE'])) {
                        foreach($PROP['VALUE'] as $imageID) $VALUE[] = \CFile::GetPath($imageID); 
                    } else {
                        $VALUE[] = $PROP['VALUE'];
                    }
                    $arSelected['images'] = $VALUE;
                } else if(isset($arFields[$selected['images']['select']])) {
                    $field = $selected['images']['select'];
                    if(in_array($field, array('DETAIL_PICTURE', 'PREVIEW_PICTURE'))) {
                        $arSelected['images'][] = \CFile::GetPath($arFields[$selected['images']['select']]);
                    }
                }

            } else if($selected['images']['select'] == 'self') $arSelected['images'][] = $selected['images']['input'];

            if(!empty($selected['depth']['select']) && $selected['depth']['select'] != 'self') {
                
                if(strpos($selected['depth']['select'], 'PROPERTY[') !== false) {
                    $CODE_PROP = str_replace(array('PROPERTY[', ']'), array('', ''), $selected['depth']['select']);
                    if(is_array($arFields['PROPERTY'][$CODE_PROP]['VALUE'])) $arSelected['depth'] = $arFields['PROPERTY'][$CODE_PROP]['VALUE'][0];
                    else $arSelected['depth'] = $arFields['PROPERTY'][$CODE_PROP]['VALUE'];
                } else if(isset($arFields[$selected['depth']['select']])) {
                    $arSelected['depth'] = $arFields[$selected['depth']['select']];
                }

            } else if($selected['depth']['select'] == 'self') $arSelected['depth'] = $selected['depth']['input'];

            if(!empty($selected['width']['select']) && $selected['width']['select'] != 'self') {
                
                if(strpos($selected['width']['select'], 'PROPERTY[') !== false) {
                    $CODE_PROP = str_replace(array('PROPERTY[', ']'), array('', ''), $selected['width']['select']);
                    if(is_array($arFields['PROPERTY'][$CODE_PROP]['VALUE'])) $arSelected['width'] = $arFields['PROPERTY'][$CODE_PROP]['VALUE'][0];
                    else $arSelected['width'] = $arFields['PROPERTY'][$CODE_PROP]['VALUE'];
                } else if(isset($arFields[$selected['width']['select']])) {
                    $arSelected['width'] = $arFields[$selected['width']['select']];
                }

            } else if($selected['width']['select'] == 'self') $arSelected['width'] = $selected['width']['input'];

            if(!empty($selected['height']['select']) && $selected['height']['select'] != 'self') {

                if(strpos($selected['height']['select'], 'PROPERTY[') !== false) {
                    $CODE_PROP = str_replace(array('PROPERTY[', ']'), array('', ''), $selected['height']['select']);
                    if(is_array($arFields['PROPERTY'][$CODE_PROP]['VALUE'])) $arSelected['height'] = $arFields['PROPERTY'][$CODE_PROP]['VALUE'][0];
                    else $arSelected['height'] = $arFields['PROPERTY'][$CODE_PROP]['VALUE'];
                } else if(isset($arFields[$selected['height']['select']])) {
                    $arSelected['height'] = $arFields[$selected['height']['select']];
                }

            } else if($selected['height']['select'] == 'self') $arSelected['height'] = $selected['height']['input'];

            $arSelected['dimension'] = $selected['dimension']['select'];

            if(!empty($selected['weight']['select']) && $selected['weight']['select'] != 'self') {

                if(strpos($selected['weight']['select'], 'PROPERTY[') !== false) {
                    $CODE_PROP = str_replace(array('PROPERTY[', ']'), array('', ''), $selected['weight']['select']);
                    if(is_array($arFields['PROPERTY'][$CODE_PROP]['VALUE'])) $arSelected['weight'] = $arFields['PROPERTY'][$CODE_PROP]['VALUE'][0];
                    else $arSelected['weight'] = $arFields['PROPERTY'][$CODE_PROP]['VALUE'];
                } else if(isset($arFields[$selected['weight']['select']])) {
                    $arSelected['weight'] = $arFields[$selected['weight']['select']];
                }

            } else if($selected['weight']['select'] == 'self') $arSelected['weight'] = $selected['weight']['input'];

            $arSelected['unit'] = $selected['unit']['select'];

            foreach($selected['attr'] as $idAttr => $attribute) {

                if($attribute['select'] == 'self') {
                    $arSelected['attributes'][] = [
                        'id' => $idAttr, 
                        'values' => [['value' => $attribute['input']]]
                    ]; 
                } else if(strpos($attribute['select'], 'PROPERTY[') !== false) {
                    $arSelected['attributes'][] = [
                        'id' => $idAttr,
                        'values' => self::getPropertyValue($attribute, $arFields['PROPERTY'])
                    ];
                }

            }
            foreach($selected['optional'] as $idAttr => $attribute) {

                if($attribute['select'] == 'self') {
                    $arSelected['attributes'][] = [
                        'id' => $idAttr, 
                        'values' => [['value' => $attribute['input']]]
                    ]; 
                } else if(strpos($attribute['select'], 'PROPERTY[') !== false) {
                    $arSelected['attributes'][] = [
                        'id' => $idAttr,
                        'values' => self::getPropertyValue($attribute, $arFields['PROPERTY'])
                    ];
                }

            }
            
            $arFields['OZON_PARAMS'] = $arSelected;
            $result[] = $arFields;
        }
    }

    private static function getPropertyValue($code, $props) {
        $result = null;
        $CODE_PROP = str_replace(array('PROPERTY[', ']'), array('', ''), $code['select']);
        if(!isset($props[$CODE_PROP])) return null;
        
        $property = $props[$CODE_PROP];

        $property_type = $property['PROPERTY_TYPE'];
        $property_multiple = ($property['MULTIPLE'] == 'Y' || is_array($property['VALUE']));

        if($property_multiple && !empty($property['VALUE'])) {

            if($property_type == 'L') {

                foreach($property['VALUE'] as $value) $result[] = ['value' => $value];

            } else if($property_type == 'F') {

                foreach($property['VALUE'] as $value) $result[] = ['value' => \CFile::GetPath($value)];

            } else if($property_type == 'S') {

                if($property['USER_TYPE'] == 'directory' && !empty($property['USER_TYPE_SETTINGS']['TABLE_NAME'])) {
                    
                    $display = \CIBlockFormatProperties::GetDisplayValue(array(), $property);
                    
                    if(is_array($display['DISPLAY_VALUE'])) {
                        foreach($display['DISPLAY_VALUE'] as $value) $result[] = ['value' => $value];
                    } else {
                        $result[] = ['value' => $display['DISPLAY_VALUE']];
                    }

                } else {

                    foreach($property['VALUE'] as $value) $result[] = ['value' => $value];
                    
                }

            }

        } else if(!empty($property['VALUE'])) {

            if($property_type == 'L') {

                $result[] = ['value' => $property['VALUE']];

            } else if($property_type == 'F') {

                $result[] = ['value' => \CFile::GetPath($property['VALUE'])];

            } else if($property_type == 'S') {

                if($property['USER_TYPE'] == 'directory' && !empty($property['USER_TYPE_SETTINGS']['TABLE_NAME'])) {
                    $display = \CIBlockFormatProperties::GetDisplayValue(array(), $property);
                    if(is_array($display['DISPLAY_VALUE'])) {
                        foreach($display['DISPLAY_VALUE'] as $value) $result[] = ['value' => $value];
                    } else {
                        $result[] = ['value' => $display['DISPLAY_VALUE']];
                    }
                } else {
                    $result[] = ['value' => $property['VALUE']];
                }

            }

        }

        return $result;
    }

	public static function GetPrice($product_id) {
		$arDiscounts = \CCatalogDiscount::GetDiscountByProduct($product_id, [2], "N", 1, "s1");
		$useDiscount = [];
		foreach($arDiscounts as $discount)
		{

			if(empty($useDiscount)) {

				$useDiscount['PRIORITY'] = $discount['PRIORITY'];
				$useDiscount['VALUE'] = $discount['VALUE'];
				$useDiscount['VALUE_TYPE'] = $discount['VALUE_TYPE'];
			
            } else if($useDiscount['PRIORITY'] < $discount['PRIORITY']) {
			
                $useDiscount['PRIORITY'] = $discount['PRIORITY'];
				$useDiscount['VALUE'] = $discount['VALUE'];
				$useDiscount['VALUE_TYPE'] = $discount['VALUE_TYPE'];

            }

		}

        $base_price = \CPrice::GetBasePrice($product_id);

        if(!empty($useDiscount)) {
            if($useDiscount['VALUE_TYPE'] == 'P') $newPrice = $base_price['PRICE'] - (($base_price['PRICE'] / 100) * intval($useDiscount['VALUE']));
            if($useDiscount['VALUE_TYPE'] == 'F') $newPrice = floatval($base_price['PRICE']) - floatval($useDiscount['VALUE']);
		} else {
			$newPrice = $base_price['PRICE'];
		}

		return ['VALUE' => $newPrice, 'CURRENCY' => 'RUB'];
	}

    private static function getListHL($TABLE_NAME, $filter = false) {
        Loader::includeModule("highloadblock");
        $hlblock = HL\HighloadBlockTable::getList(['filter' => ['TABLE_NAME' => $TABLE_NAME]])->fetch();
        if($hlblock){
            $hlClassName = (HL\HighloadBlockTable::compileEntity($hlblock))->getDataClass();
            $args = array(
                "select" => array("*"),
                "order" => array("ID"=>"DESC"),                
            );

            if($filter) $args['filter'] = $filter;

            $result = $hlClassName::getList($args);
            
            while ($arRow = $result->Fetch()) $data['result'][] = ['value' => $arRow["UF_NAME"]];
        }
    }

    public static function getFilePath($fileID) {
        $rsSites = \CSite::GetByID('s1');
        $arSite = $rsSites->Fetch();
        $site= 'https://'.$arSite['DOMAINS'];
        $file = \CFile::GetPath($fileID);
        if($file == null) return false;

        return $site . $file;
    }

    public static function getStatusName($CODE) {
        $status = [
            'is_new' => 'Не выгружался',
            'processing' => 'информация о товаре добавляется в систему, ожидайте',
            'moderating' => 'товар проходит модерацию, ожидайте',
            'processed' => 'информация обновлена',
            'failed_moderation' => 'товар не прошел модерацию',
            'failed_validation' => 'товар не прошел валидацию',
            'failed' => 'возникла ошибка'
        ];

        return $status[$CODE];
    }

    public static function get_offer_url($ID) {

    }

    public static function convertFiledParams($attributes, $attribute) {
    }
}