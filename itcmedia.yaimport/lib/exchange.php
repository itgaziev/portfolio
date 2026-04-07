<?
namespace ITCMedia\YaImport;

use ITCMedia\YaImport;

class Exchange {
    static $module_id = 'itcmedia.yaimport';
    static $type = ''; // read | parse | import | images | clear
    static $id = 0; // id import
    static $condition = null;
    
    // Optimization proccess
    static $starttime = null;
    static $timeout = 20;
    static $sessid = null;
    static $sessidFiles = [];

    static $errors = [];
    static $clearImage = false;

    public static function runProccess($id, $type, $sessid = null, $clearImage = 'N') {
        self::$sessid = $sessid;
        self::$clearImage = $clearImage;
        self::$sessidFiles = [
            'filelist' => $_SERVER['DOCUMENT_ROOT'] . '/upload/' . self::$module_id . '/filelist_' . self::$sessid . '.json',
            'excel' => $_SERVER['DOCUMENT_ROOT'] . '/upload/' . self::$module_id . '/excel_' . self::$sessid . '.json',
            'import' => $_SERVER['DOCUMENT_ROOT'] . '/upload/' . self::$module_id . '/import_' . self::$sessid . '.json',
            'catalog' => $_SERVER['DOCUMENT_ROOT'] . '/upload/' . self::$module_id . '/catalog_' . self::$sessid . '.json',
            'image_dir' => false
        ];

        self::$starttime = time();
        self::$id = $id;
        self::$type = $type;

        $result = YaImport\Table\ITCMediaYaImportTable::getById(self::$id);
        self::$condition = $result->fetch();
        if(!empty(self::$condition['PARAMETERS'])) self::$condition['PARAMETERS'] = unserialize(self::$condition['PARAMETERS']);
        if(!empty(self::$condition['IMAGES_SETTINGS'])) {
            self::$condition['IMAGES_SETTINGS'] = unserialize(self::$condition['IMAGES_SETTINGS']);
        } else {
            self::$condition['IMAGES_SETTINGS'] = [];
        }
        if (self::$condition['IMAGES_SETTINGS']['HASH_IMAGE']) {
            $imageDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/'. self::$module_id .'/import_files/'.self::$condition['IMAGES_SETTINGS']['HASH_IMAGE'];
            if (!file_exists($imageDir)) {
                mkdir($imageDir, 0700, true);
            }
            self::$sessidFiles['image_dir'] = $imageDir;
        }

        switch ($type) {
            case 'read':
                self::clearFiles();
                return self::readYandex();
            case 'parse':
                return self::parseExcel();
            case 'import':
                return self::importFile();
            case 'price':
                return self::importPrice();
            case 'store':
                return self::importStore();
            case 'clear': return self::clearFiles();
        }
    }

    public static function clearImage() {
        $files = glob(self::$sessidFiles['image_dir'].'/*'); 
        foreach($files as $file) {
            if(is_file($file)) {
                unlink($file);
            }
        }
    }

    public static function readYandex() {
        if (self::$clearImage == 'Y') {
            self::clearImage();
        }

        $yandex = new YaImport\Yandex(self::$condition['YA_TOKEN']);
        if ($result = $yandex->SaveFile(self::$condition['YA_EXCEL'])) {
            $localName = YaImport\Main::GetLocalFileName(self::$condition['YA_EXCEL']);
            if (!file_exists($result)) {
                self::$errors[] = 'ERROR FILE';
            }
        } else {
            self::$errors[] = 'ERROR FILE';
        }

        if (!self::$errors) {
            $files = [];
            $files['MAIN'] = $yandex->ReadFolder(self::$condition['IMAGES_SETTINGS']['MAIN_IMAGE']['FOLDER']);
            $files['GALLERY'] = $yandex->ReadFolder(self::$condition['IMAGES_SETTINGS']['GALLERY']['FOLDER']);
            $files['FILE'] = $yandex->ReadFolder(self::$condition['IMAGES_SETTINGS']['FILE']['FOLDER']);

            file_put_contents(self::$sessidFiles['filelist'], json_encode($files));
        }

        return self::agentReturn('end', 'parse', 'Прайс загружен');
    }

    public static function parseExcel() {
        $filepath = YaImport\Main::GetPathFile(self::$condition['YA_EXCEL']);        

        if (!file_exists(self::$sessidFiles['filelist'])) {
            self::$errors[] = 'NOT FOUND FILE';
            return false;
        }

        $arFiles = json_decode(file_get_contents(self::$sessidFiles['filelist']), true);

        if (!file_exists($filepath)) {
            self::$errors[] = 'NOT FOUND FILE';
            return false;
        }

        if (!file_exists(self::$sessidFiles['excel'])) {
            $excel = new YaImport\Excel($filepath);
            $read = $excel->ReadFile($filepath);
            file_put_contents(self::$sessidFiles['excel'], json_encode($read));
        } else {
            $read = json_decode(file_get_contents(self::$sessidFiles['excel']), true);
        }

        $data = [];
        $data['position'] = 0;
        if (file_exists(self::$sessidFiles['import'])) {
            $data = json_decode(file_get_contents(self::$sessidFiles['import']), true);
        }

        foreach ($read['body'] as $key => $value) {
            if ($key <= $data['position']) continue;

            $data['items'][] = YaImport\Helper::ConvertData($value, self::$condition, $read['header'], $arFiles);
            $data['position'] = $key;

            if (self::executeTime()) break;
        }

        if ($data['position'] + 1 >= count($read['body'])) {
            // TODO : End process
            file_put_contents(self::$sessidFiles['import'], json_encode($data));
            return self::agentReturn('end', 'import', 'Обработка прайса успешно завершена');
        }

        // TODO : continue
        file_put_contents(self::$sessidFiles['import'], json_encode($data));
        return self::agentReturn('continue', 'parse', 'Обработка прайса ' . ($data['position'] + 1) . ' из ' . count($read['body']) . ' позиций');
    }

    public static function importFile() {
        if (!file_exists(self::$sessidFiles['import'])) {
            self::$errors[] = 'NOT FOUND FILE';
            return false;
        }

        $data = json_decode(file_get_contents(self::$sessidFiles['import']), true);

        if (file_exists(self::$sessidFiles['catalog'])) {
            $catalog = json_decode(file_get_contents(self::$sessidFiles['catalog']), true);
        } else {
            $catalog = [
                'items' => [],
                'position' => -1
            ];
        }
        $arProps = YaImport\Main::GetPropertyIBlock(self::$condition['IBLOCK_ID']);
        foreach ($data['items'] as $key => $item) {
            if ($key <= $catalog['position']) continue;
            
            $arItem = YaImport\Catalog::ImportElement($item, self::$condition, $arProps, self::$sessidFiles['image_dir']);
            $catalog['items'][] = $arItem;
            $catalog['position'] = $key;
            if($arItem['error']) {
                self::$errors[] = $arItem['error'];
            }
            if (self::executeTime()) break;
        }

        if (count($catalog['items']) == count($data['items'])) {
            // TODO : End process
            $catalog['position'] = -1;
            file_put_contents(self::$sessidFiles['catalog'], json_encode($catalog));
            return self::agentReturn('end', 'price', 'Импорт товаров успешно завершен');
        }

        // TODO : continue
        file_put_contents(self::$sessidFiles['catalog'], json_encode($catalog));
        return self::agentReturn('continue', 'import', 'Импорт товаров ' . ($catalog['position'] + 1) . ' из ' . count($data['items']) . ' товаров');
    }

    public static function importPrice() {
        if (file_exists(self::$sessidFiles['catalog'])) {
            $catalog = json_decode(file_get_contents(self::$sessidFiles['catalog']), true);
        } else {
            self::$errors[] = 'NOT FOUND FILE';
            return false;
        }
        $arProps = YaImport\Main::GetPropertyIBlock(self::$condition['IBLOCK_ID']);
        foreach ($catalog['items'] as $key => $item) {
            if ($key <= $catalog['position']) continue;
            
            $arItem = YaImport\Catalog::ImportPrice($item, self::$condition);
            $catalog['position'] = $key;
            if($arItem['error']) {
                self::$errors[] = $arItem['error'];
            }
            if (self::executeTime()) break;
        }

        if (count($catalog['items']) == $catalog['position'] + 1) {
            // TODO : End process
            $catalog['position'] = -1;
            file_put_contents(self::$sessidFiles['catalog'], json_encode($catalog));
            return self::agentReturn('end', 'store', 'Импорт цен успешно завершен');
        }

        // TODO : continue
        file_put_contents(self::$sessidFiles['catalog'], json_encode($catalog));
        return self::agentReturn('continue', 'price', 'Импорт цен ' . ($catalog['position'] + 1) . ' из ' . count($catalog['items']) . ' товаров');
    }

    public static function importStore() {
        if (file_exists(self::$sessidFiles['catalog'])) {
            $catalog = json_decode(file_get_contents(self::$sessidFiles['catalog']), true);
        } else {
            self::$errors[] = 'NOT FOUND FILE';
            return;
        }

        $arProps = YaImport\Main::GetPropertyIBlock(self::$condition['IBLOCK_ID']);
        foreach ($catalog['items'] as $key => $item) {
            if ($key <= $catalog['position']) continue;
            
            $arItem = YaImport\Catalog::ImportStore($item, self::$condition);
            $catalog['position'] = $key;
            if($arItem['error']) {
                self::$errors[] = $arItem['error'];
            }
            if (self::executeTime()) break;
        }

        if (count($catalog['items']) == $catalog['position'] + 1) {
            // TODO : End process
            $catalog['position'] = 0;
            file_put_contents(self::$sessidFiles['catalog'], json_encode($catalog));
            return self::agentReturn('end', 'clear', 'Импорт остатков завершен');
        }

        // TODO : continue
        file_put_contents(self::$sessidFiles['catalog'], json_encode($catalog));
        return self::agentReturn('continue', 'store', 'Импорт остатков ' . ($catalog['position'] + 1) . ' из ' . count($catalog['items']) . ' товаров');
    }

    private static function executeTime() {
        return (time() - self::$starttime > self::$timeout);
    }

    private static function agentReturn($result, $type, $message) {
        $return = [];
        $return['result'] = $result;
        if ($type !== 'end') {
            $return['agent'] = '\\ITCMedia\\YaImport\\Exchange::runProccess(' . self::$condition['ID'] . ', "'. $type .'", "' . self::$sessid . '")';
        } else {
            $return['agent'] = null;
        }
        $return['message'] = $message;
        $return['type'] = $type;
        $return['sessid'] = self::$sessid;
        $return['id'] = self::$id;
        $return['errors'] = self::$errors;
        return $return;
    }

    private static function clearFiles() {
        unlink(self::$sessidFiles['filelist']);
        unlink(self::$sessidFiles['excel']);
        unlink(self::$sessidFiles['import']);
        unlink(self::$sessidFiles['catalog']);

        return self::agentReturn('end', 'end', 'Файлы очищены');
    }
}