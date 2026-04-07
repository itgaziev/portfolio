<?

namespace ITCMedia\YaImport;

class Helper {

    public static function ConvertData($row, $condition, $header, $files) {
        $arItem = [];


        // Вызов событие функции
        $rsHandlers = GetModuleEvents("itcmedia.yaimport", "ConvertDataItemBefore");
        while($arHandler = $rsHandlers->Fetch())
            ExecuteModuleEventEx($arHandler, array(&$arItem, $row, $condition, $header, $files));        

        foreach ($row as $key => $value) {
            if ($condition['PARAMETERS'][$key]['FIELD'] == 'SKIP' || empty($condition['PARAMETERS'][$key]['FIELD'])) {
                continue;
            }
            $code = $condition['PARAMETERS'][$key]['FIELD'];
            $value = trim($value);
            $isFilter = ($condition['PARAMETERS'][$key]['FILTER'] && $condition['PARAMETERS'][$key]['FILTER'] == 'Y');

            $codeData = self::GetCodeData($code);
            $arItem[$codeData['TYPE']][$codeData['CODE']] = $value;

            if ($isFilter) {
                $arItem['FILTER'][$codeData['TYPE']][$codeData['CODE']] = $value;
            }
        }
        // '/SH.100.1.370.4?(_?(\d+)).(jpg|png)/'
        // MAIN IMAGE
        if ($condition['IMAGES_SETTINGS']['MAIN_IMAGE']['MASK_NAME']) {
            $imageMask = self::ConverMask($condition['IMAGES_SETTINGS']['MAIN_IMAGE']['MASK_NAME'], $row);
            $arItem['IMAGE']['MAIN'] = [
                'FILE' => self::FindFile($files['MAIN'], $imageMask),
                'MASK' => $imageMask,
            ];
        }
        // GALLERY
        if ($condition['IMAGES_SETTINGS']['GALLERY']['MASK_NAME']) {
            $codeData = self::GetCodeData($condition['IMAGES_SETTINGS']['GALLERY']['PROPERTY_ID']);
            $imageMask = self::ConverMask($condition['IMAGES_SETTINGS']['GALLERY']['MASK_NAME'], $row);
            $arItem['IMAGE']['GALLERY'] = [
                'MASK' => $imageMask,
                'FILE' => self::FindFile($files['GALLERY'], $imageMask),
                'PROPERTY' => $codeData['CODE']
            ];
        }
        // DOCUMENT
        if ($condition['IMAGES_SETTINGS']['FILE']['MASK_NAME']) {
            $codeData = self::GetCodeData($condition['IMAGES_SETTINGS']['FILE']['PROPERTY_ID']);
            $imageMask = self::ConverMask($condition['IMAGES_SETTINGS']['FILE']['MASK_NAME'], $row);
            $arItem['IMAGE']['FILE'] = [
                'MASK' => $imageMask,
                'FILE' => self::FindFile($files['FILE'], $imageMask),
                'PROPERTY' => $codeData['CODE']
            ];
        }
        // Дублируещие изображения для товаров в разделе
        if ($arItem['FIELD']['IBLOCK_SECTION_ID']) {
            foreach ($condition['IMAGES_SETTINGS']['SECTION_MAIN_IMAGE'] as $key => $value) {
                if ($arItem['FIELD']['IBLOCK_SECTION_ID'] == $value['SECTION_ID']) {
                    $arItem['IMAGE']['SECTION_IMAGE'] = $value['FULL_PATH_IMAGE'];
                }
            }
        }

        // Вызов событие функции
        $rsHandlers = GetModuleEvents("itcmedia.yaimport", "ConvertDataItemAfter");
        while($arHandler = $rsHandlers->Fetch())
            ExecuteModuleEventEx($arHandler, array(&$arItem, $row, $condition, $header, $files));

        return $arItem;
    }

    public static function GetCodeData($code) {
        $data = [
            'TYPE' => 'FIELD',
            'CODE' => $code,
        ];

        if (preg_match('/PROPERTY_(\d+)/', $code, $match)) {
            $data = ['TYPE' => 'PROPERTY', 'CODE' => $match[1]];    
        }
        if (preg_match('/STORE_(\d+)/', $code, $match)) {
            $data = ['TYPE' => 'STORE', 'CODE' => $match[1]];    
        }
        if (preg_match('/PRICE_(\d+)/', $code, $match)) {
            $data = ['TYPE' => 'PRICE', 'CODE' => $match[1]];    
        }
        if ($code == 'STORE_BASE') {
            $data = ['TYPE' => 'STORE', 'CODE' => 'BASE'];    
        }
        return $data;
    }

    public static function ConverMask($string, $row) {
        $head_preg = '/\{HEADER_(\d+)\}/';

        if (preg_match($head_preg, $string, $match)) {
            if ($row[$match[1] - 1]) {
                $value = str_replace($match[0], $row[$match[1] - 1], $string);
            }
        }

        return $value;
    }

    public static function FindFile($fileList, $regex) {
        // $array = preg_grep(
        //     '/name.(jpg|png)/i',
        //     array( 'file' , 'name.png', 'this')
        // );
        $arFiles = [];
        foreach ($fileList as $key => $value) {
            if (preg_match('/'.$regex . '/i', $value['name'])) {
                $arFiles[] = $value;
            }
        }
        
        return $arFiles;
    }
}