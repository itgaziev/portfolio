<?php
namespace ITGaziev\SmartURL\Table;

use \Bitrix\Main\Entity;
use \Bitrix\Main\Type;

class UrlTable extends Entity\DataManager {
    public static function getTableName() {
        return 'itgaziev_smarturl';
    }

    public static function getUfId() {
        return 'SMART_URL';
    }

    public static function getMap() {
        return [
            new Entity\IntegerField('ID', array('primary' => true, 'autocomplete' => true)),
            new Entity\StringField('ACTIVE', array('required' => true)),
            new Entity\IntegerField('SORT'),
            new Entity\DateTimeField('TIME_CREATE', array('required' => true)),
            new Entity\StringField('NAME', array('required' => true)),
            new Entity\StringField('SITE_ID', array('required' => true)),
            new Entity\StringField('OLD_URL'),
            new Entity\StringField('NEW_URL'),
            new Entity\StringField('HEAD_TITLE'), //H1
            new Entity\StringField('SEO_TITLE'), //SEO TITLE
            new Entity\TextField("SEO_DESCRIPTION"), // SEO DESCRIPTION
            new Entity\TextField("HTML_DESCRIPTION"), // SEO DESCRIPTION
            new Entity\StringField('REPLACED_SEO'),
            new Entity\StringField('NEED_REDIRECTED'),
            new Entity\StringField('SHOW_TAGS'),
            new Entity\TextField('URLS_SHOW'),

        ];
    }
}