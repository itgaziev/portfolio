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
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\ORM\Query\Query;
use Bitrix\Catalog\PriceTable;
use Bitrix\Catalog\ProductTable;
use Bitrix\Sale\Internals\StoreProductTable;


class Exchange
{
    static $module_id = 'itgaziev.wbprice';

    public static function getElements($condition)
    {
        Loader::includeModule('iblock');
        Loader::includeModule('catalog');

        $query = ElementTable::query();
        foreach ($condition['FILTER']['REF'] as $key => $value) {
            if ($value['TABLE'] == 'PROPERTY') {
                $query->registerRuntimeField(
                    $value['KEY'],
                    [
                        'data_type' => Table\ElementPropTable::class,
                        'reference' => [
                            '=this.ID' => 'ref.IBLOCK_ELEMENT_ID',
                            '=ref.IBLOCK_PROPERTY_ID' => new \Bitrix\Main\DB\SqlExpression('?i', $value['ID']),
                        ],
                        'join_type' => 'LEFT',
                    ]
                );
            } else if ($value['TABLE'] == 'PRICE') {
                $query->registerRuntimeField(
                    $value['KEY'],
                    [
                        'data_type' => PriceTable::class,
                        'reference' => [
                            '=this.ID' => 'ref.PRODUCT_ID',
                            '=ref.CATALOG_GROUP_ID' => new \Bitrix\Main\DB\SqlExpression('?i', $value['ID']),
                        ],
                        'join_type' => 'LEFT',
                    ]
                );
            } else if ($value['TABLE'] == 'CATALOG_PRODUCT') {
                $query->registerRuntimeField(
                    'CATALOG_PRODUCT',
                    [
                        'data_type' => ProductTable::class,
                        'reference' => [
                            '=this.ID' => 'ref.ID',
                        ],
                        'join_type' => 'LEFT',
                    ]
                );
            } else if ($value['TABLE'] == 'STORE') {
                $query->registerRuntimeField(
                    $value['KEY'],
                    [
                        'data_type' => StoreProductTable::class,
                        'reference' => [
                            '=this.ID' => 'ref.PRODUCT_ID',
                            '=ref.STORE_ID' => new \Bitrix\Main\DB\SqlExpression('?i', $value['ID']),
                        ],
                        'join_type' => 'LEFT',
                    ]
                );
            }
        }

        $query
            ->setSelect(['ID'])
            ->setFilter($condition['FILTER']['RULE']);

        // Выполнение запроса
        $result = $query->exec();

        $rows = $result->fetchAll();

        return array_column($rows, 'ID');
    }

    public static function saveJson($priceId)
    {
        $cachePath = $_SERVER['DOCUMENT_ROOT'] . '/upload/' . self::$module_id . '/cache_price_' . $priceId . '.json';
        $savePath = $_SERVER['DOCUMENT_ROOT'] . '/upload/' . self::$module_id . '/save_price_' . $priceId . '.json';

        unlink($cachePath);
        unlink($savePath);

        $condition = self::getPrice($priceId);
        file_put_contents(__DIR__ . '/data.log', print_r($condition, true));

        $ids = self::getElements($condition);

        $columns = self::getColumn($condition['PARAMETERS']);
        if ($ids) {
            $elements = ElementTable::getList(['filter' => ['@ID' => $ids], 'select' => array_merge(['ID'], $columns['ELEMENT'])])->fetchAll();

            foreach ($elements as &$element) {
                if ($columns['PROPERTY']) {
                    $props = Table\ElementPropTable::getList([
                        'filter' => ['@IBLOCK_PROPERTY_ID' => $columns['PROPERTY'], 'IBLOCK_ELEMENT_ID' => $element['ID']],
                        'select' => [
                            "PROP_ID" => 'IBLOCK_PROPERTY_ID',
                            "VALUE",
                            "USER_TYPE_SETTINGS" => "PROPERTY.USER_TYPE_SETTINGS",
                            "PROPERTY_NAME" => "PROPERTY.NAME",
                            "PROPERTY_CODE" => "PROPERTY.CODE",
                            "TYPE" => "PROPERTY.PROPERTY_TYPE",
                            "ENUM_VALUE" => "ENUM.VALUE",
                            "ENUM_XML_ID" => "ENUM.XML_ID"
                        ]
                    ])->fetchAll();

                    $element['PROPERTY'] = $props;
                }
                if ($columns['STORE']) {
                    $element['STORE'] = StoreProductTable::getList(['filter' => ['@STORE_ID' => $columns['STORE'], 'PRODUCT_ID' => $element['ID']]])->fetchAll();
                }
                if ($columns['CATALOG']) {
                    $element['CATALOG'] = ProductTable::getList(['filter' => ['ID' => $element['ID']], 'select' => ['QUANTITY', 'AVAILABLE']])->fetch();
                }
                if ($columns['PRICE']) {
                    $element['PRICE'] = PriceTable::getList(['filter' => ['PRODUCT_ID' => $element['ID'], '@CATALOG_GROUP_ID' => $columns['PRICE']], 'select' => ['PRICE_ID' => 'CATALOG_GROUP_ID', 'PRICE']])->fetchAll();
                }
            }

            file_put_contents($cachePath, json_encode($elements));
        } else {
            return ['action' => 'end', 'current' => 0, 'total' => 0];
        }
        return ['action' => 'continue', 'current' => 0, 'total' => count($elements)];
    }

    public static function getColumn($columns)
    {
        $fields = [];
        foreach ($columns as $column) {
            if ($column['VALUE'] == 'IMAGES') {
                $fields['ELEMENT'][] = 'DETAIL_PICTURE';
                $fields['PROPERTY'][] = 37;
            } else if (strpos($column['VALUE'], 'PRICE_') !== false) {
                $id = str_replace('PRICE_', '', $column['VALUE']);
                $fields['PRICE'][] = $id;
            } else if (strpos($column['VALUE'], 'STORE_') !== false) {
                $id = str_replace('STORE_', '', $column['VALUE']);
                $fields['STORE'][] = $id;
            } else if ($column['VALUE'] == 'CATALOG_COUNT') {
                $fields['CATALOG'][] = $column['VALUE'];
            } else if (strpos($column['VALUE'], 'PROPERTY_') !== false) {
                $id = str_replace('PROPERTY_', '', $column['VALUE']);
                $fields['PROPERTY'][] = $id;
            } else if ($column['VALUE'] == 'IBLOCK_SECTION_NAME') {
                $fields['ELEMENT'][] = 'IBLOCK_SECTION.NAME';
            } else if ($column['VALUE'] == 'SKIP') {

            } else {
                $fields['ELEMENT'][] = $column['VALUE'];
            }
        }

        return $fields;
    }

    public static function getPrice($priceId)
    {
        $result = Table\ITGazievWbPriceTable::getById($priceId);
        $condition = $result->fetch();
        if ($condition['PARAMETERS']) {
            $condition['PARAMETERS'] = unserialize($condition['PARAMETERS']) ?? [];
        }

        if ($condition['CONDITIONS']) {
            $condition['CONDITIONS'] = unserialize($condition['CONDITIONS']) ?? [];
        }

        $condition['FILTER'] = ParseRule::initCondition($condition['CONDITIONS']);

        return $condition;
    }

    public static function priceGenerate($priceId)
    {
        $condition = self::getPrice($priceId);

        $cachePath = $_SERVER['DOCUMENT_ROOT'] . '/upload/' . self::$module_id . '/cache_price_' . $priceId . '.json';
        if (!file_exists($cachePath)) {
            return ['action' => 'end'];
        }
        $cacheData = json_decode(file_get_contents($cachePath), true);

        $savePath = $_SERVER['DOCUMENT_ROOT'] . '/upload/' . self::$module_id . '/save_price_' . $priceId . '.json';
        $saveData = [];
        if (file_exists($savePath)) {
            $saveData = json_decode(file_get_contents($savePath), true);
        }

        $executionTime = 20;
        $startTime = time();
        $action = 'end';

        foreach ($cacheData as $element) {
            if (isset($saveData[$element['ID']]))
                continue;

            // TODO : Generate columns
            $saveData[$element['ID']] = self::generateColumn($condition['PARAMETERS'], $element);

            if ((time() - $startTime) >= $executionTime) {
                break;
            }
        }

        if (count($saveData) != count($cacheData)) {
            $action = 'continue';
        }
        file_put_contents($savePath, json_encode($saveData));
        return ['action' => $action, 'current' => count($saveData), 'total' => count($cacheData)];
    }

    public static function makeFile($priceId)
    {
        include($_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . self::$module_id . '/lib/vendor/PHPExcel.php');

        $savePath = $_SERVER['DOCUMENT_ROOT'] . '/upload/' . self::$module_id . '/save_price_' . $priceId . '.json';
        if (!file_exists($savePath)) {
            return ['action' => 'error'];
        }

        $data = json_decode(file_get_contents($savePath), true);
        $data = array_values($data);

        $fileName = $_SERVER['DOCUMENT_ROOT'] . '/upload/' . self::$module_id . '/products_' . $priceId . '.xls';

        XLSX::generateExcell($data, $fileName);

        //return ['action' => 'end', 'url' => 'https://vdd.it-gaziev.ru/upload/' . self::$module_id . '/products_' . $priceId . '.xls'];
    }

    public static function generateColumn($parameters, $element)
    {
        $result = [];

        foreach ($parameters as $column) {
            if ($column['VALUE'] == 'NAME') {
                $result[$column['COLUMN']] = $element['NAME'];
            } else if ($column['VALUE'] == 'CODE') {
                $result[$column['COLUMN']] = $element['CODE'];
            } else if ($column['VALUE'] == 'PREVIEW_TEXT') {
                $result[$column['COLUMN']] = $element['PREVIEW_TEXT'];
            } else if ($column['VALUE'] == 'DETAIL_TEXT') {
                $result[$column['COLUMN']] = $element['DETAIL_TEXT'];
            } else if ($column['VALUE'] == 'PREVIEW_PICTURE') {
                $image = \CFile::ResizeImageGet($element['PREVIEW_PICTURE'], ['width' => 700, 'height' => 900], BX_RESIZE_IMAGE_PROPORTIONAL_ALT, true);
                $result[$column['COLUMN']] = $image['src'] ? 'https://v-dd.ru' . $image['src'] : '';
            } else if ($column['VALUE'] == 'DETAIL_PICTURE') {
                $image = \CFile::ResizeImageGet($element['DETAIL_PICTURE'], ['width' => 700, 'height' => 900], BX_RESIZE_IMAGE_PROPORTIONAL_ALT, true);
                $result[$column['COLUMN']] = $image['src'] ? 'https://v-dd.ru' . $image['src'] : '';
            } else if ($column['VALUE'] == 'IBLOCK_SECTION_ID') {
                $result[$column['COLUMN']] = $element['IBLOCK_SECTION_ID'];
            } else if ($column['VALUE'] == 'IBLOCK_SECTION_NAME') {
                $result[$column['COLUMN']] = $element['IBLOCK_ELEMENT_IBLOCK_SECTION_NAME'];
            } else if ($column['VALUE'] == 'CATALOG_COUNT') {
                $result[$column['COLUMN']] = $element['CATALOG']['QUANTITY'];
            } else if (strpos($column['VALUE'], 'PRICE_') !== false) {
                $id = str_replace('PRICE_', '', $column['VALUE']);

                $filtered = array_values(array_filter($element['PRICE'], function ($array) use ($id) {
                    return $array['PRICE_ID'] == $id;
                }));

                $result[$column['COLUMN']] = $filtered[0] ? $filtered[0]['PRICE'] : 0;
            } else if (strpos($column['VALUE'], 'STORE_') !== false) {
                $id = str_replace('STORE_', '', $column['VALUE']);

                $filtered = array_values(array_filter($element['STORE'], function ($array) use ($id) {
                    return $array['STORE_ID'] == $id;
                }));

                $result[$column['COLUMN']] = $filtered[0] ? $filtered[0]['AMOUNT'] : 0;
            } else if (strpos($column['VALUE'], 'PROPERTY_') !== false) {
                $id = str_replace('PROPERTY_', '', $column['VALUE']);

                $filtered = array_values(array_filter($element['PROPERTY'], function ($array) use ($id) {
                    return $array['PROP_ID'] == $id;
                }));

                $value = $filtered[0] ? self::getValue($filtered[0]) : "";

                $result[$column['COLUMN']] = $value;
            } else if ($column['VALUE'] == 'IMAGES') {
                $images = [];
                if ($element['DETAIL_PICTURE']) {
                    $file = \CFile::ResizeImageGet($element['DETAIL_PICTURE'], ['width' => 700, 'height' => 900], BX_RESIZE_IMAGE_PROPORTIONAL, true);
                    if ($file['width'] < 500 || $file['height'] < 500) {
                        $image = \CFile::GetPath($element['DETAIL_PICTURE']);
                        $ext = pathinfo($file['src']);
                        $path_output = $_SERVER['DOCUMENT_ROOT'] . '/upload/' . self::$module_id . '/cache/' . $ext['filename'] . '_' . $element['ID'] . '.' . $ext['extension'];

                        try {
                            self::resizeImageWithBackground(
                                $_SERVER['DOCUMENT_ROOT'] . $image, // Путь к исходному изображению
                                $path_output, // Путь для сохранения результата
                                700, // Ширина
                                900, // Высота
                                [255, 255, 255] // Цвет фона (белый)
                            );
                            $images[] = ['src' => '/upload/' . self::$module_id . '/cache/' . $ext['filename'] . '_' . $element['ID'] . '.' . $ext['extension']];
                        } catch (\Exception $e) {

                        }
                    } else {
                        $images[] = $file;
                    }
                }

                $filtered = array_values(array_filter($element['PROPERTY'], function ($array) {
                    return $array['PROP_ID'] == 37;
                }));

                foreach ($filtered as $moreImage) {

                    $file = \CFile::ResizeImageGet($moreImage['VALUE'], ['width' => 700, 'height' => 900], BX_RESIZE_IMAGE_PROPORTIONAL, true);
                    if ($file['width'] < 500 || $file['height'] < 500) {
                        $image = \CFile::GetPath($moreImage['VALUE']);
                        $ext = pathinfo($file['src']);
                        $path_output = $_SERVER['DOCUMENT_ROOT'] . '/upload/' . self::$module_id . '/cache/' . $ext['filename'] . '_' . $element['ID'] . '.' . $ext['extension'];

                        try {
                            self::resizeImageWithBackground(
                                $_SERVER['DOCUMENT_ROOT'] . $image, // Путь к исходному изображению
                                $path_output, // Путь для сохранения результата
                                700, // Ширина
                                900, // Высота
                                [255, 255, 255] // Цвет фона (белый)
                            );
                            $images[] = ['src' => '/upload/' . self::$module_id . '/cache/' . $ext['filename'] . '_' . $element['ID'] . '.' . $ext['extension']];
                        } catch (\Exception $e) {

                        }
                    } else {
                        $images[] = $file;
                    }
                }

                $imgs = [];
                $count = 0;
                foreach ($images as $image) {

                    $imgs[] = 'https://v-dd.ru' . $image['src'];
                    $count++;
                    if ($count > 20)
                        break;
                }

                $result[$column['COLUMN']] = implode(';', $imgs);
            } else {
                $result[$column['COLUMN']] = '';
            }
        }

        return $result;
    }

    public static function resizeImageWithBackground($sourcePath, $outputPath, $targetWidth = 700, $targetHeight = 900, $backgroundColor = [255, 255, 255])
    {
        // Проверяем, существует ли файл
        if (!file_exists($sourcePath)) {
            throw new \Exception("Файл не найден: " . $sourcePath);
        }
        if (file_exists($outputPath)) {
            return;
        }
        // Получаем информацию об изображении
        [$sourceWidth, $sourceHeight, $sourceType] = getimagesize($sourcePath);

        // Создаём исходное изображение в зависимости от типа
        switch ($sourceType) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            default:
                throw new \Exception("Неподдерживаемый тип изображения: " . $sourceType);
        }

        // Вычисляем масштаб и размеры
        $scale = min($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
        $newWidth = (int) ($sourceWidth * $scale);
        $newHeight = (int) ($sourceHeight * $scale);

        // Создаём новый холст с белым фоном
        $outputImage = imagecreatetruecolor($targetWidth, $targetHeight);
        $backgroundColor = imagecolorallocate($outputImage, $backgroundColor[0], $backgroundColor[1], $backgroundColor[2]);
        imagefill($outputImage, 0, 0, $backgroundColor);

        // Вычисляем координаты для центрирования изображения
        $offsetX = (int) (($targetWidth - $newWidth) / 2);
        $offsetY = (int) (($targetHeight - $newHeight) / 2);

        // Копируем и изменяем размер исходного изображения
        imagecopyresampled(
            $outputImage,
            $sourceImage,
            $offsetX,
            $offsetY, // Куда копировать
            0,
            0,               // Откуда копировать
            $newWidth,
            $newHeight, // Новый размер
            $sourceWidth,
            $sourceHeight // Исходный размер
        );

        // Сохраняем результат в файл
        switch ($sourceType) {
            case IMAGETYPE_JPEG:
                imagejpeg($outputImage, $outputPath, 90); // 90 — качество JPEG
                break;
            case IMAGETYPE_PNG:
                imagepng($outputImage, $outputPath);
                break;
            case IMAGETYPE_GIF:
                imagegif($outputImage, $outputPath);
                break;
        }

        // Освобождаем ресурсы
        imagedestroy($sourceImage);
        imagedestroy($outputImage);
    }

    public static function getValue($value)
    {
        $value['USER_TYPE_SETTINGS'] = !empty($value['USER_TYPE_SETTINGS']) ? unserialize($value['USER_TYPE_SETTINGS']) : [];
        if ($value['USER_TYPE_SETTINGS'] && $value['USER_TYPE_SETTINGS']['TABLE_NAME']) {
            $value['VALUE'] = self::searchPropHLoad($value);
        }

        return $value['VALUE'];
    }

    public static function searchPropHLoad($res)
    {
        Loader::includeModule("highloadblock");
        $result = $res['VALUE'];
        if ($res && $res['USER_TYPE_SETTINGS']['TABLE_NAME']) {
            $hlblockFind = HL\HighloadBlockTable::getList([
                'filter' => ['=TABLE_NAME' => $res['USER_TYPE_SETTINGS']['TABLE_NAME']]
            ])->fetch();

            $hlbl = $hlblockFind['ID']; // Указываем ID нашего highloadblock блока к которому будет делать запросы.
            $hlblock = HL\HighloadBlockTable::getById($hlbl)->fetch();

            $entity = HL\HighloadBlockTable::compileEntity($hlblock);
            $entity_data_class = $entity->getDataClass();

            $rsData = $entity_data_class::getList(array(
                "select" => array("*"),
                "order" => array("ID" => "ASC"),
                "filter" => array("UF_XML_ID" => $res['VALUE']),
            ));

            if ($arData = $rsData->Fetch()) {
                $result = $arData['UF_NAME'];
            }
        }
        return $result;
    }
}
