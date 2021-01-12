<?php
namespace Tualo\Office\WM;
use Tualo\Office\CMS\ICmsMiddleware;


class WMEmptyAccept implements ICmsMiddleware{
    public static function run(&$request,&$result){
        if (!isset($_SESSION['current_state'])) return;
        if ($_SESSION['current_state']=='login'){
            if (!isset($_REQUEST['accept'])){
                $_REQUEST['accept']=1;
                syslog(LOG_DEBUG, "WMEmptyAccept accept param not given set it to 1");
            }else{
                syslog(LOG_DEBUG, "WMEmptyAccept accept param not given set it to 1");
            }
        }
        session_commit();
        
    }
}