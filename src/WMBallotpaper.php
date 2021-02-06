<?php
namespace Tualo\Office\WM;
use Tualo\Office\CMS\ICmsMiddleware;
use Tualo\Office\CMS\CMSMiddlewareWMHelper;

class WMBallotpaper implements ICmsMiddleware{

    public static function save(&$request,&$result){
        $db = CMSMiddlewareWMHelper::$db;

        $voter = $db->direct('select session_id from voters where voter_id = {voter_id} and session_id={session_id} ', ['voter_id'=>$_SESSION['pug_session']['voter_id'],'session_id'=>session_id()] );

        if (count($voter)==0){ 
            $_SESSION['pug_session']['error'][] = "Die Sitzung ist nicht mehr gültig";
            WMInit::$next_state = 'error';
            
            return;
        }


        $voter = $db->direct('select voter_id from voters where voter_id = {voter_id} and completed=1 ', ['voter_id'=>$_SESSION['pug_session']['voter_id'],'session_id'=>session_id()] );
        if (count($voter)>0){ 
            $_SESSION['pug_session']['error'][] = "Die Sitzung ist nicht mehr gültig, Sie haben bereits bereits gewählt.";
            WMInit::$next_state = 'error';
            
            return;
        }

        
        try{

            $db->direct('start transaction;');
            $pgpkeys = $db->direct('select * from pgpkeys');
            $_SESSION['pug_session']['pgp'] = [];
            $_SESSION['pug_session']['pgp_decrypted'] = [];
            foreach($pgpkeys as $keyitem){
                $hash = $keyitem;
                
                $hash['ballotpaper']=TualoApplicationPGP::encrypt( $keyitem['publickey'], json_encode($_SESSION['pug_session']['ballotpaper']['checks']));
                
                $hash['voter_id']=$_SESSION['pug_session']['voter_id'];

                $db->direct('insert into ballotbox (id,keyname,ballotpaper,voter_id) values (uuid(),{keyname},{ballotpaper},{voter_id})',$hash);
                unset($hash['privatekey']);
                $_SESSION['pug_session']['pgp'][] = $hash;

                
            }
            if ($_SESSION['api']==1){
                $url = $_SESSION['api_url'].str_replace('{voter_id}',$_SESSION['pug_session']['voter_id'],'cmp_wm_ruecklauf/api/set/{voter_id}');
                $record = WMRequestHelper::query($url,['secret_token'=>$_SESSION['pug_session']['secret_token']]);
                if ($record===false) throw new Exception('Der Vorgang konnte nicht abgeschlossen werden');
                if ($record['success']==false) throw new Exception($record['msg']);
            }
            $voter = $db->direct('update voters set completed = 1 where voter_id = {voter_id}',$_SESSION['pug_session']);

            unset($_SESSION['pug_session']);
            $result = [];
            WMInit::_initrun($request,$result);
            $_SESSION['pug_session']['login']=false;
            WMInit::$next_state = 'ballotpaper-saved';
           
            $db->direct('commit;');

        }catch(Exception $e){
            WMInit::$next_state = 'error';
            $_SESSION['pug_session']['error'][] = $e->getMessage();
        }

    }

    public static function valid(){
        $db = CMSMiddlewareWMHelper::$db;
        $_SESSION['pug_session']['ballotpaper']['valid'] = false;
        $kandidaten = $db->direct('select id,barcode,ridx,stimmzettelgruppen from kandidaten',$_SESSION['pug_session']['ballotpaper'],'id');
        $stimmzettelgruppen = $db->direct('select ridx,id,name,sitze,stimmzettel,0 __checkcount from stimmzettelgruppen where stimmzettel in (select ridx from stimmzettel where id={id})',$_SESSION['pug_session']['ballotpaper'],'ridx');

        foreach($_SESSION['pug_session']['ballotpaper']['checks'] as $check){
            if (isset($kandidaten[$check])){
                if (isset($stimmzettelgruppen[$kandidaten[$check]['stimmzettelgruppen']])){
                    $stimmzettelgruppen[$kandidaten[$check]['stimmzettelgruppen']]['__checkcount']++;
                }else{
                    syslog(LOG_CRIT,"WM Stimmzettegruppe {$kandidaten[$check]['stimmzettelgruppe']} bei Kandidat ID $check not found");
                    WMInit::$next_state = 'error';
                    $_SESSION['pug_session']['error'][] = 'Der Kandidat ist nicht für Ihren Stimmzettel zugelassen.';
                    return false;
                }
            }else{
                syslog(LOG_CRIT,"WM Kandidate ID $check not found");
                WMInit::$next_state = 'error';
                $_SESSION['pug_session']['error'][] = 'Der Kandidat ist nicht für Ihren Stimmzettel zugelassen.';
                return false;
            }

        }


        $valid = true;
        foreach($stimmzettelgruppen as $stimmzettelgruppe){
            if ($stimmzettelgruppe['__checkcount']>$stimmzettelgruppe['sitze']){
                $valid=false;
            }
        }
        $_SESSION['pug_session']['ballotpaper']['valid'] = $valid;
        return true;

    }

    public static function empty($id){
        $db = CMSMiddlewareWMHelper::$db;
        return [
            'id' => $id,
            'valid' => true,
            'max' => $db->singleValue('select sum(sitze) sitze from stimmzettelgruppen where stimmzettel in (select ridx from stimmzettel where id={id})',['id'=>$id],'sitze'),
            'checkcount' => 0,
            'checks' => [],
            'interrupted'=> false
        ];
    }

    public static function run(&$request,&$result){
        @session_start();
        $db = CMSMiddlewareWMHelper::$db;

        
        if (!isset($_SESSION['current_state'])) return;
        if (!isset($_SESSION['pug_session'])) return;

        if (isset($_SESSION['pug_session']) && (isset($_SESSION['pug_session']['ballotpaper']))){
            $interrupted = $db->singleValue('select unterbrochen from stimmzettel where id={id} ',$_SESSION['pug_session']['ballotpaper'],'unterbrochen');
            if ($interrupted==1) $_SESSION['pug_session']['ballotpaper']['interrupted']=true;
        }
        if ( 
            ($_SESSION['current_state'] == 'ballotpaper') && 
            ($_SESSION['pug_session']['ballotpaper']['interrupted']==false) 
        ){

            if (!isset($_REQUEST['candidate'])&& ( !isset($_REQUEST['correct']) )) $_REQUEST['candidate'] = [];

            if (isset($_REQUEST['candidate'])){
                $_SESSION['pug_session']['ballotpaper']['checkcount']=count($_REQUEST['candidate']);
                $_SESSION['pug_session']['ballotpaper']['checks']=$_REQUEST['candidate'];
            }
            
            WMInit::$next_state = 'overview';

            if (WMBallotpaper::valid()===false){
                return;
            }

        }

        if ( 
            (($_SESSION['current_state'] == 'ballotpaper')||($_SESSION['current_state'] == 'overview')) && 
            (isset($_REQUEST['correct'])) &&
            ($_SESSION['pug_session']['ballotpaper']['interrupted']==false) 
        ){

            self::empty($_SESSION['pug_session']['ballotpaper']['id']);
            WMInit::$next_state = 'ballotpaper';

        }else if ( 
            ($_SESSION['current_state'] == 'overview') && 
            (!isset($_REQUEST['correct'])) &&
            ($_SESSION['pug_session']['ballotpaper']['interrupted']==false) 
        ){

            if ($_SESSION['pug_session']['ballotpaper']['valid']==true){
                WMInit::$next_state = 'error';
                // time to save
                WMBallotpaper::save($request,$result);
            }else{
                WMInit::$next_state = 'error';
                $_SESSION['pug_session']['error'][] = 'Zu viele Stimmen auf dem Stimmzettel';
            }
        }
        session_commit();
        
        
    }
}