<?php

class WMLogout extends CMSMiddleWare{
    public static function killSession(){
        session_start();
        unset($_SESSION['pug_session']);
        session_commit();
    }

    public static function run(&$request,&$result){
        session_start();
        $db = CMSMiddlewareWMHelper::$db;

        if (isset($_REQUEST['logout'])&&($_REQUEST['logout']=='1')){
            $result = [];
            $next_state = '';
            if (isset( $_SESSION['pug_session']['texts']['logoutpage']) && ($_SESSION['pug_session']['texts']['logoutpage']['value_plain']==1) ) {
                $next_state = 'logoutpage';
            }
            unset($_SESSION['pug_session']);
            WMInit::_initrun($request,$result);
            $_SESSION['pug_session']['login']=false;
            if ($next_state != '')   WMInit::$next_state = $next_state;
        }

        if (isset($_REQUEST['logout'])&&($_REQUEST['logout']=='2')){
            unset($_SESSION['pug_session']);
            $result = [];
            WMInit::_initrun($request,$result);
            $_SESSION['pug_session']['login']=false;
            WMInit::$next_state = 'not-legitimized';
        }
        session_commit();
        
    }
}
