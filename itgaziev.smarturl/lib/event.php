<?
namespace ITGaziev\SmartURL;

class Event {

    public static function OnBuildGlobalMenu(&$aGlobalMenu, &$aModuleMenu) {
        global $USER;

        if ($USER->IsAdmin()) {
            if (!isset($aGlobalMenu['global_menu_itgaziev'])) {
                $aGlobalMenu['global_menu_itgaziev'] = [
                    'menu_id' => 'itgaziev',
                    'text' => 'ITGaziev',
                    'title' => 'ITGaziev',
                    'sort' => 1000,
                    'items_id' => 'global_menu_itgaziev',
                    'help_section' => 'itgaziev',
                    'items' => [],
                ];
            }
            $aGlobalMenu['global_menu_itgaziev']['items'][] = [
                'parent_menu' => 'global_menu_itgaziev',
                'sort'        => 99,
                'url'         => 'itgaziev.smarturl_list.php?lang=' . LANGUAGE_ID,
                'text'        => 'Умные ссылки',
                'title'       => 'Умные ссылки',
                'items_id'    => 'itgaziev_smarturl',
                'more_url' => [
                    'itgaziev.smarturl_detail.php',
                ]
            ];
        }
    }

    public static function onPageStart() {  
        $request = \Bitrix\Main\Context::getCurrent()->getRequest();
        if($request->isAdminSection()) return;
        if ($request->isPost()) return;
        if (isset($_GET['ajax'])) return;

        Main::proccessUrl();
    }

    public static function OnEndBufferContent(&$context) {
        if (isset($GLOBALS['SMARTURL_SEO'])) {
            $seo = $GLOBALS['SMARTURL_SEO'];

        }
    }
}