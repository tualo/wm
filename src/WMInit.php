<?php
namespace Tualo\Office\WM;
use Tualo\Office\CMS\ICmsMiddleware;
use Tualo\Office\CMS\CMSMiddlewareWMHelper;
use Tualo\Office\DS\DSReadRoute;
use Tualo\Office\Basic\TualoApplication;

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
        TualoApplication::timing("WMInit start 0");
        @session_start();
        $db = CMSMiddlewareWMHelper::$db;

        
//        $result['textvalues'] = $db->direct('select replace(system_settings_id,\'cmstext/\',\'\') id, property from system_settings where id like \'cmstext/%\' ',[],'system_settings_id');

        TualoApplication::timing("WMInit start 1");



        self::_initrun($request,$result);

        if (
            defined('__CMS_ALLOWED_IP__')
            && (defined('__CMS_ALLOWED_IP_REDIRECT__'))
            && (defined('__CMS_ALLOWED_IP_FIELD__'))
        ){
            if(isset( $_SERVER[__CMS_ALLOWED_IP_FIELD__] )){
                if (strpos(__CMS_ALLOWED_IP__, $_SERVER[__CMS_ALLOWED_IP_FIELD__])===false){
                    WMInit::$next_state = 'notstarted';
                    $_SESSION['current_state']= 'notstarted';
                }
            }else{
                if (strpos(__CMS_ALLOWED_IP__, $_SERVER['REMOTE_ADDR'])===false){
                    WMInit::$next_state = 'notstarted';
                    $_SESSION['current_state']= 'notstarted';

                }
            }

        }
        TualoApplication::timing("WMInit __CMS_ALLOWED_IP__");


        // $stimmzettelgruppen =  DSReadRoute::read($db,'stimmzettelgruppen',['shortfieldnames'=>1,'limit'=>100000]);
        // $result['stimmzettelgruppen'] = $stimmzettelgruppen['data'];

        $result['stimmzettelgruppen'] = $db->direct('select * from stimmzettelgruppen');
        TualoApplication::timing("WMInit sqls");

        //$view_website_stimmzettel = DSReadRoute::read($db,'view_website_stimmzettel',['shortfieldnames'=>1,'limit'=>100000]);
        //$result['view_website_stimmzettel'] = $view_website_stimmzettel['data'];
        $result['view_website_stimmzettel'] = $db->direct('select * from view_website_stimmzettel');

        TualoApplication::timing("WMInit sqls");

        //$view_website_candidates = DSReadRoute::read($db,'view_website_candidates',['shortfieldnames'=>1,'limit'=>100000]);
        //$result['view_website_candidates'] = $view_website_candidates['data'];
        $result['view_website_candidates'] = $db->direct('select * from view_website_candidates');

        TualoApplication::timing("WMInit sqls");

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
            
        TualoApplication::timing("WMInit browser");


        //Google Chrome
        //echo $obj->detect()->getInfo();


        
        $_SESSION['pug_session']['error'] = [];

        session_commit();
        TualoApplication::timing("WMInit session_commit");

    }
}