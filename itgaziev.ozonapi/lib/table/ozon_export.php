<?php

namespace ITGaziev\OzonAPI\Table;

use \Bitrix\Main\Entity;
use \Bitrix\Main\Type;

class OzonExportTable extends Entity\DataManager {

    public static function getTableName() {
        return 'b_ozon_export';
    }

    public static function getUfId() {
        return 'OZON_EXPORT';
    }

    public static function getMap() {
        return array(
            new Entity\IntegerField('ID', array('primary' => true, 'autocomplete' => true)),
            new Entity\StringField('ACTIVE', array('required' => true)),
            new Entity\DateTimeField('TIME_CREATE', array('required' => true)),
            new Entity\StringField('NAME', array('required' => true)),

            //ID iblock bitrix
            new Entity\IntegerField('SETTINGS_ID'),
            new Entity\IntegerField('IBLOCK'),
            
            //ID ozon category
            new Entity\StringField('OZON_SECTION'),
            
            //Свойство Озон в связке со свойствами битрикс array serialize 
            new Entity\TextField('PARAMETERS'),
            //new Entity\TextField('PARAMETERS_FILED'),
            new Entity\TextField('PARAMETERS_OZON'),

            //Фильтр товаров array serialize 
            new Entity\TextField('FILTERS'),

            //Время выгрузки 0 - Не выгружать, 1 - Обновлять каждый 1 час, 2 - обновлять каждые 2 часа, 3 - обновлять каждые 3 час, 4 - обновлять каждые 6 часов, 5 - обновлять каждые 12 часов, 6 - обнволять каждый 1 день, 7 обновлять каждые 2 дня, 8 - обновлять каждые 3 дня, 9 - обновлять 1 раз в неделю
            new Entity\IntegerField('AGENT_TIME'),

            //Время последний выгрузки
            new Entity\DateTimeField('LAST_TIME_UPDATE'),
        );
    }
}