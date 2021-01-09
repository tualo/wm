<?php

namespace tualo\Office\WM\Middleware;
use tualo\Office\Basic\TualoApplication as App;
use tualo\Office\Basic\IMiddleware;

class Middleware implements IMiddleware{
    public static function register(){
        App::use('wm',function(){
            try{
                App::javascript(  'skeleton_js', './skeleton/Routes.js', [], 1 );
            }catch(\Exception $e){
                App::set('maintanceMode','on');
                App::addError($e->getMessage());
            }
        },-100);
        App::use('wm_time',function(){
            try{
                
                if(!isset($_SESSION['skeleton_time']))$_SESSION['skeleton_time']=[];
                if(!isset($_SESSION['skeleton_time_loggedIn']))$_SESSION['skeleton_time_loggedIn']=[];
                if (count($_SESSION['skeleton_time'])>10) $_SESSION['skeleton_time']=[];
                if (count($_SESSION['skeleton_time_loggedIn'])>10) $_SESSION['skeleton_time_loggedIn']=[];
                
                $_SESSION['skeleton_time'][]=date('Y-m-d H:i:s',time());
                if (isset($_SESSION['tualoapplication']) && isset($_SESSION['tualoapplication']['loggedIn']) && ($_SESSION['tualoapplication']['loggedIn']) ){
                    $_SESSION['skeleton_time_loggedIn'][]=date('Y-m-d H:i:s',time());
                }
                session_commit();
                
            }catch(\Exception $e){
                App::set('maintanceMode','on');
                App::addError($e->getMessage());
            }
        },200);
    }
}