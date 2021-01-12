<?php
use Tualo\Office\CMS\ICmsMiddleware;

class WMInit implements ICmsMiddleware{

    public static $current_state = '';
    public static $next_state = '';
    public static function registerstep($name){
        $db = CMSMiddlewareWMHelper::$db;
        $key = $db->singleValue('select uuid() u',[],'u');
        
        foreach($_SESSION['pug_session']['step_hash'] as $k=>$n){
            if ($n==$name) unset($_SESSION['pug_session']['step_hash'][$k]);
        }
        $_SESSION['pug_session']['step_hash'][$key]=$name;
        $_SESSION['pug_session']['step_key'][$name]=$key;

        $_SESSION['pug_session']['stepuuid']=$key;

    }


    public static function _initrun(&$request,&$result){
        


        if (!isset($_SESSION['pug_session'])){
            $_SESSION['pug_session'] = [];
            $_SESSION['pug_session']['error'] = [];
            $_SESSION['pug_session']['login']=false;
            $_SESSION['saving']=false;
            
            $_SESSION['pug_session']['login_error']=0;
            $_SESSION['pug_session']['step_hash'] = [];
            $_SESSION['current_state'] = '';
        }
        

        if (
            isset($_REQUEST['uuid']) && 
            isset($_SESSION['pug_session']['step_hash'][$_REQUEST['uuid']])
        ){
            $_SESSION['current_state'] = $_SESSION['pug_session']['step_hash'][$_REQUEST['uuid']];
        }else{


        }
        WMInit::$next_state = 'login';


       

    }

    public static function run(&$request,&$result){
        session_start();
        $db = CMSMiddlewareWMHelper::$db;

        
//        $result['textvalues'] = $db->direct('select replace(system_settings_id,\'cmstext/\',\'\') id, property from system_settings where id like \'cmstext/%\' ',[],'system_settings_id');
        
        self::_initrun($request,$result);

        try{
            $obj = new BrowserDetection();
            $obj->detect();
            $ver = $obj->getVersion();
            $verP = explode('.',$ver);
            $major = intval($verP[0]);
            if ($obj->getBrowser()=='Google Chrome'){
                if ($major<64){ // back ward support edge
                    $_SESSION['pug_session']['error'][] = "Sie haben einen veralteten Browser.";
                    WMInit::$next_state = 'old-browser';
                }
            }
            if ($obj->getBrowser()=='Firefox'){
                if ($major<68){
                    $_SESSION['pug_session']['error'][] = "Sie haben einen veralteten Browser.";
                    WMInit::$next_state = 'old-browser';
                }
            }
            if ($obj->getBrowser()=='Internet Explorer 11'){
                if ($major<44){
                    $_SESSION['pug_session']['error'][] = "Sie haben einen veralteten Browser.";
                    WMInit::$next_state = 'old-browser';
                }
            }
            if ($obj->getBrowser()=='Internet Explorer'){
                if ($major<44){
                    $_SESSION['pug_session']['error'][] = "Sie haben einen veralteten Browser.";
                    WMInit::$next_state = 'old-browser';
                }
            }
            
        }catch(Exception $e){
            
        }
            
        

        //Google Chrome
        //echo $obj->detect()->getInfo();


        
        $_SESSION['pug_session']['error'] = [];

        session_commit();
        
    }
}