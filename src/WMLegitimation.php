<?php
namespace Tualo\Office\WM;
use Tualo\Office\CMS\ICmsMiddleware;
use Tualo\Office\CMS\CMSMiddlewareWMHelper;

class WMLegitimation implements ICmsMiddleware{

    public static function run(&$request,&$result){
        session_start();
        $db = CMSMiddlewareWMHelper::$db;


        if (!isset($_SESSION['current_state'])) return;
        if (!isset($_SESSION['pug_session'])) return;
        
        if ( $_SESSION['current_state'] == 'legitimation' ){
            WMInit::$next_state = 'ballotpaper';
        }
        if ( $_SESSION['current_state'] == 'legitimation-extended' ){
            WMInit::$next_state = 'legitimation';
        }

        if (
            isset($_REQUEST['vorname']) &&
            isset($_REQUEST['nachname']) &&
            isset($_REQUEST['titel'])
        ){
            $data = [
                'firstname' => $_REQUEST['vorname'],
                'lastname' => $_REQUEST['nachname'],
                'title' => $_REQUEST['titel']
            ];
            if (isset($_SESSION['api_url']) && isset($_SESSION['pug_session']['secret_token'])){
                $url = $_SESSION['api_url'].'/cmp_wm_ruecklauf/api/extended/'.$_SESSION['pug_session']['secret_token'].'?extended_data='.urlencode(json_encode($data));
                $object = WMRequestHelper::query($url,'./');
            }
        }
        session_commit();
        

    }
}
