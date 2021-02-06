<?php
namespace Tualo\Office\WM;
use Tualo\Office\CMS\ICmsMiddleware;

class WMSetLoginStateIfNull implements ICmsMiddleware{
    public static function run(&$request,&$result){
        @session_start();
        if (!isset($_SESSION['current_state'])) $_SESSION['current_state']='';

        if ( $_SESSION['current_state']=='' ) {
            syslog(LOG_DEBUG, "WMSetLoginStateIfNull no current state set, set it to login");
            $_SESSION['current_state']='login';
        }
        session_commit();
        
    }
}