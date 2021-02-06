<?php
namespace Tualo\Office\WM;
use Tualo\Office\CMS\ICmsMiddleware;
use Tualo\Office\CMS\CMSMiddlewareWMHelper;

class WMFinish implements ICmsMiddleware{

    public static function run(&$request,&$result){
        @session_start();
        $db = CMSMiddlewareWMHelper::$db;


        if (!isset($_SESSION['current_state'])) return;
        if (!isset($_SESSION['pug_session'])) return;


 

        $_SESSION['pug_session']['usernamefield'] = bin2hex(random_bytes(6));
        $_SESSION['pug_session']['passwordfield'] = bin2hex(random_bytes(6));

        $wm_wahlschein_register = $db->singleRow('select * from wm_loginpage_settings where id = 1',array(),'');
        $current = date('Y-m-d H:i:s',time());
        if ($wm_wahlschein_register['starttime']>$current){
            $result['wm_state'] = 'notstarted';
        }else if ($wm_wahlschein_register['stoptime']<$current){
            $result['wm_state'] = 'stopped';
        }else if ($wm_wahlschein_register['interrupted']==1){
            $result['wm_state'] = 'interrupted';
        }else{

            if (
                isset($_SESSION['pug_session']) &&
                isset($_SESSION['pug_session']['ballotpaper']) &&
                isset($_SESSION['pug_session']['ballotpaper']['interrupted']) &&
                $_SESSION['pug_session']['ballotpaper']['interrupted'] == true
            ){
                $info = $db->singleRow('select * from view_website_stimmzettel where id={id}',$_SESSION['pug_session']['ballotpaper']);
                WMInit::_initrun($request,$result);
                $_SESSION['pug_session']['interrupted']=$info;
                WMInit::$next_state = 'ballotpaper-interrupted';
            }

            if(WMInit::$next_state == 'error'){
                WMInit::_initrun($request,$result);
                $_SESSION['pug_session']['login']=false;
                WMInit::$next_state = 'error';
            }

            $result['wm_state'] = WMInit::$next_state;
            $_SESSION['pug_session']['current_state_was'] =  $_SESSION['current_state'];
            WMInit::registerstep( $result['wm_state'] ); 
        }


        session_commit();
    }
}