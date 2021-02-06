<?php
namespace Tualo\Office\WM;
use Tualo\Office\CMS\ICmsMiddleware;
use Tualo\Office\CMS\CMSMiddlewareWMHelper;



use Michelf\Markdown;
use Michelf\MarkdownExtra;

class WMTexts implements ICmsMiddleware{

    public static function run(&$request,&$result){
        @session_start();
        $db = CMSMiddlewareWMHelper::$db;

        $_SESSION['pug_session']['texts'] = $db->direct('select id,value_plain,value_html from wm_texts',[],'id');
        $_SESSION['pug_session']['ballotpaper_styles'] = $db->singleValue('select css from view_wm_balltopaper_colors',[],'css');

        $_SESSION['pug_session']['markdownhtml'] = $db->direct('select id,value_plain from wm_texts',[],'id');
        //$Parsedown = new ParsedownExtraPlugin;
        foreach( $_SESSION['pug_session']['markdownhtml'] as $key=>$entry){
            $_SESSION['pug_session']['markdownhtml'][$key]['value_plain'] = MarkdownExtra::defaultTransform( /*$Parsedown->text(*/ $entry['value_plain'] ) /*)*/;
            if (strpos($_SESSION['pug_session']['markdownhtml'][$key]['value_plain'],"<p>")===0){
                $_SESSION['pug_session']['markdownhtml'][$key]['value_plain'] = substr( $_SESSION['pug_session']['markdownhtml'][$key]['value_plain'] ,3,-3) /*)*/;
            }
        }
        session_commit();
        
    }
}

