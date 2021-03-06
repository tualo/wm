<?php
namespace Tualo\Office\WM;
use Tualo\Office\CMS\ICmsMiddleware;
use Tualo\Office\CMS\CMSMiddlewareWMHelper;
use Tualo\Office\Basic\TualoApplication;

class WMLegitimation implements ICmsMiddleware{

    public static function run(&$request,&$result){
        @session_start();
        $db = CMSMiddlewareWMHelper::$db;


        TualoApplication::timing("WMLegitimation start");


        if (!isset($_SESSION['current_state'])) return;
        if (!isset($_SESSION['pug_session'])) return;
        
        if ( $_SESSION['current_state'] == 'legitimation' ){
            WMInit::$next_state = 'ballotpaper';
        }
        if ( $_SESSION['current_state'] == 'legitimation-extended' ){
            WMInit::$next_state = $db->singleValue('select value_plain from wm_texts where id=\'after_legitimation_extended_state\'',[],'value_plain');
            if (WMInit::$next_state === false) WMInit::$next_state = 'legitimation';
        }

        if (isset($_REQUEST['asklegitimation'])&&($_REQUEST['asklegitimation']=='1')){
            //echo '*';exit();
            WMInit::$next_state = 'legitimation-ru-sure';
        }
        if (isset($_REQUEST['asklegitimation'])&&($_REQUEST['asklegitimation']=='2')){
            WMInit::$next_state = 'legitimation';
        }
            

        if (isset($_REQUEST['legitimation'])&&($_REQUEST['legitimation']=='0')){
            WMInit::$next_state = 'legitimized-ru-sure';
        }

        if (isset($_REQUEST['legitimation'])&&($_REQUEST['legitimation']=='2')){
            WMInit::$next_state = 'legitimation';
        }

        if (
            isset($_REQUEST['vorname']) &&
            isset($_REQUEST['nachname']) &&
            isset($_REQUEST['titel']) &&

            is_string($_REQUEST['vorname']) &&
            is_string($_REQUEST['nachname']) &&
            is_string($_REQUEST['titel'])
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
