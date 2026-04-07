<?
namespace ITGaziev\SmartURL;

class Main {
    public static function getSites() {
        $rsSites = \Bitrix\Main\SiteTable::getList()->fetchAll();
        $arrSites = [];
        foreach ($rsSites as $site) {
            $arrSites['REFERENCE'][] = '['.$site['LID'] .'] ' . $site['NAME'];
            $arrSites['REFERENCE_ID'][] = $site['LID'];
        }
        return $arrSites;
    }
    public static function getIblocks() {
        $arIblock = \Bitrix\Iblock\IblockTable::getList()->fetchAll();
        $arrSites = [];
        foreach ($arIblock as $site) {
            $arrSites['REFERENCE'][] = '['.$site['ID'] .'] ' . $site['NAME'];
            $arrSites['REFERENCE_ID'][] = $site['ID'];
        }
        return $arrSites;
    }
    public static function getSections($iblock_id) {
        $sectionList = \Bitrix\Iblock\SectionTable::getList([
            'select' => ['ID', 'NAME', 'DEPTH_LEVEL', 'IBLOCK_SECTION_ID', 'IBLOCK_ID'],
            'filter' => ['IBLOCK_ID' => $iblock_id],
            'order' => ['DEPTH_LEVEL' => 'ASC'],
        ])->fetchAll();
        $arrSites = [];
        foreach ($sectionList as $site) {
            $arrSites['REFERENCE'][] = '['.$site['ID'] .'] ' . $site['NAME'];
            $arrSites['REFERENCE_ID'][] = $site['ID'];
        }
        return $arrSites;  
    }
    public static function proccessUrl() {
        global $APPLICATION;
        $site_id = SITE_ID;
        $request = \Bitrix\Main\Context::getCurrent()->getRequest();
        $requestUri = $request->getRequestUri();
        $url = $APPLICATION->GetCurPage();
        $fixUrl = str_replace(' ', '%20', $url);
        $encode = urlencode($url);
        $encode = str_replace("%2F", "/", $encode);

        $oldUrl = Table\UrlTable::getList(['filter' => ['ACTIVE' => 'Y', '@OLD_URL' => [$url, $fixUrl, $encode], 'SITE_ID' => $site_id]])->fetch();
        if ($oldUrl) {
            if ($oldUrl['NEW_URL'] && $oldUrl['NEED_REDIRECTED']) {
                LocalRedirect($oldUrl['NEW_URL'], false, '301 Moved permanently');
            }
            return self::setSeo($oldUrl);
        }

        $newUrl = Table\UrlTable::getList(['filter' => ['ACTIVE' => 'Y', '@NEW_URL' => [$url, $fixUrl], 'SITE_ID' => $site_id]])->fetch();
        if ($newUrl) {
            $url = $newUrl['NEW_URL'];
            $originalUrl = $newUrl['OLD_URL'];

            $context = \Bitrix\Main\Application::getInstance()->getContext();       
            $server = $context->getServer();    
            $serverArray = $server->toArray();
            $GLOBALS['SMART_URL_NEW'] = $url;
            $_SERVER['REQUEST_URI'] = $originalUrl;
            $serverArray['REQUEST_URI'] = $originalUrl;
    
           
            $server->set($serverArray);
            $context->initialize(new \Bitrix\Main\HttpRequest($server, $_GET, [], [], $_COOKIE), $context->getResponse(), $server);
            $APPLICATION->sDocPath2 = GetPagePath(false, true);
            $APPLICATION->sDirPath = GetDirPath($APPLICATION->sDocPath2);
            $APPLICATION->SetCurPage($originalUrl); 
            return self::setSeo($newUrl);
        }
    }

    public static function setSeo($data) {
        $GLOBALS['SMARTURL_SEO'] = [
            'name' => $data['NAME'],
            'h1' => $data['HEAD_TITLE'],
            'seo_title' => $data['SEO_TITLE'],
            'seo_description' => $data['SEO_DESCRIPTION'],
            'url_page' => $data['NEW_URL'] ?? $data['OLD_URL'],
            'replace_seo' => $data['REPLACED_SEO'] == 'Y',
            'html_text' => $data['HTML_DESCRIPTION']
        ];
    }

    public static function loadSeo() {
        global $APPLICATION;
        $seo = $GLOBALS['SMARTURL_SEO'] ?? [];
        if ($seo['replace_seo']) {
            if ($seo['h1']) {
                $APPLICATION->SetTitle($seo['h1']);
            }
            if ($seo['seo_title']) {
                $APPLICATION->SetPageProperty('title', $seo['seo_title']);
            }
            if ($seo['seo_description']) {
                $APPLICATION->SetPageProperty('description', $seo['seo_description']);
            }
            $APPLICATION->SetPageProperty('keywords', "");

        }
        if ($seo['name']) {
            $APPLICATION->AddChainItem($seo['name'], $seo['url_page']);
        }
    }
}