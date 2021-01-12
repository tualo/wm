<?php


class WMLoginUserNamePassword extends CMSMiddleWare{

    public static function loginGetCredentials($username){
        $db = CMSMiddlewareWMHelper::$db;
        $_SESSION['api'] = 0;
        if ($db->singleValue("select if(property<>'',1,0) v FROM system_settings WHERE system_settings_id = 'erp/url'  ",[],'v')=='1'){
            $_SESSION['api']=1;
            $_SESSION['api_url']=$db->singleValue("select property v FROM system_settings WHERE system_settings_id = 'erp/url'  ",[],'v');
            $_SESSION['api_private']=$db->singleValue("select property v FROM system_settings WHERE system_settings_id = 'erp/privatekey'  ",[],'v');
        }

        $record=false;
        if ($_SESSION['api']==1){
            $url = $_SESSION['api_url'].str_replace('{username}',$username,'cmp_wm_ruecklauf/api/check/{username}');
            $record = WMRequestHelper::query($url);
        }else{
            $record = $db->singleRow('select * from voter_credentials where username={username}',['username'=>$username]);
        }
        if ($record===false){

            $_SESSION['pug_session']['error_no']=3;
            $_SESSION['pug_session']['error'][] = 'Der Benutzername oder das Passwort stimmen nicht überein.';
            WMInit::$next_state = 'error';
            return false;

        }
        if ($record['success']==false){
            $_SESSION['pug_session']['error_no']=4;
            $_SESSION['pug_session']['error'][] = 'Der Benutzername oder das Passwort stimmen nicht überein.';
            WMInit::$next_state = 'error';
            return false;
        }else{
            $record = $record['data'];
        }
        if (!isset($record['allowed'])) $record['allowed']=1;
        if (!isset($record['voted'])) $record['voted']=0;
        
        
        return $record;
    }

    public static function login($username,$password){
        $db = CMSMiddlewareWMHelper::$db;

        $db->direct('delete from username_count where block_until<now()');
        if (
            $db->singleRow('select * from username_count where id = {username} and block_until>now() and num>2',['username'=>$username])!==false
        ){
            $_SESSION['pug_session']['login']=false;
            $_SESSION['pug_session']['error'][] = " ";
            WMInit::$next_state = 'blocked-user';
            return false;
        }


        // sichertheit erhöhen, durch das abrufen externer daten
        $record = self::loginGetCredentials($username);

        if ($record===false) { return false; }

        if ($record['canvote']==0){
            if (isset($record['state']) && ($record['state']=='5|0')){
                WMInit::_initrun($request,$result);
                $_SESSION['pug_session']['login']=false;
                $_SESSION['pug_session']['error'][] = "Sie haben bereits teilgenommen.";
                WMInit::$next_state = 'new-address';
            }else if (isset($record['state']) && ($record['state']=='16|0')){
                WMInit::_initrun($request,$result);
                $_SESSION['pug_session']['login']=false;
                $_SESSION['pug_session']['error'][] = "Sie haben bereits teilgenommen.";
                WMInit::$next_state = 'new-documents';
            }else if (isset($record['state']) && ($record['state']=='13|0')){
                WMInit::_initrun($request,$result);
                $_SESSION['pug_session']['login']=false;
                $_SESSION['pug_session']['error'][] = "Sie haben bereits teilgenommen.";
                WMInit::$next_state = 'inactive-account';
            }else{
                WMInit::_initrun($request,$result);
                $_SESSION['pug_session']['login']=false;
                $_SESSION['pug_session']['error'][] = "Sie haben bereits teilgenommen.";
                WMInit::$next_state = 'allready-voted';

                if (false == $db->singleRow('select voter_id from voters where voter_id = {id} and completed=1',$record)){
                    WMInit::$next_state = 'allready-voted-offline';
                }else{
                    WMInit::$next_state = 'allready-voted-online';
                }

            }
            return;
        }else{
            syslog(LOG_DEBUG, "WMLoginUserNamePassword canvote is 0, ".$record['state']);
        }



        if (crypt($password, $record['pwhash']) == $record['pwhash']) {
            if ($record['allowed']==0){
                $_SESSION['pug_session']['error_no']=2;
                $_SESSION['pug_session']['error'][] = 'Der Benutzername oder das Passwort stimmen nicht überein.';
                WMInit::$next_state = 'error';
                return false;                
            }
            if ($record['voted']==1){
                $_SESSION['pug_session']['error'][] = 'An der Wahl wurde bereits teilgenommen.';
                WMInit::$next_state = 'error';
                return false;                
            }
            $_SESSION['pug_session']['voter_id'] = $record['id'];
            if ($_SESSION['api']==1){

                $_SESSION['pug_session']['secret_token'] = TualoApplicationPGP::decrypt( $_SESSION['api_private'],$record['secret_token']);
            }
            $_SESSION['pug_session']['ballotpaper_id'] = $record['ballotpaper_id'];
            return true;     
        }else{
            syslog(LOG_DEBUG, "WMLoginUserNamePassword pwhash does not match");
            $_SESSION['pug_session']['error_no']=1;
            $_SESSION['pug_session']['error'][] = 'Der Benutzername oder das Passwort stimmen nicht überein.';
            WMInit::$next_state = 'error';
            $mins_time = 1;
            if (isset( $_SESSION['pug_session']['texts']['blocktime']) ) {
                $mins_time = intval($_SESSION['pug_session']['texts']['blocktime']['value_plain'] );
            }
            $db->direct('insert into username_count (id,block_until,num) values ( {username}, date_add(now(), interval '.$mins_time.' minute) ,1) on duplicate key update num=num+1,block_until=values(block_until) ',['username'=>$username]);

            return false;
        }
        
    }

    public static function run(&$request,&$result){
        session_start();
        $db = CMSMiddlewareWMHelper::$db;

        
        if (!isset($_SESSION['current_state'])) return;
        if (!isset($_SESSION['pug_session'])) return;
        
        if (
            isset($_REQUEST['p1']) &&
            isset($_REQUEST['p2'])
        ){
            $_SESSION['p1'] = $_REQUEST['p1'];
            $_SESSION['p2'] = $_REQUEST['p2'];
        }else if (
            isset($_REQUEST['c']) && (strlen($_REQUEST['c'])==16)
        ){
            WMInit::$next_state = 'login';
            $_SESSION['p1'] = substr($_REQUEST['c'],0,8);
            $_SESSION['p2'] = substr($_REQUEST['c'],8,8);
            $_SESSION['pug_session']['p1'] = substr($_REQUEST['c'],0,8);
            $_SESSION['pug_session']['p2'] = substr($_REQUEST['c'],8,8);
        }

        if ( isset($_SESSION['p1']) && isset($_SESSION['p2']) && ($_SESSION['current_state'] == 'login' )){

            if (isset($_REQUEST['accept'])){

                $username = $_SESSION['p1'];
                $password = $_SESSION['p2'];

                if (self::login($username,$password)){

                    $_SESSION['pug_session']['ballotpaper']=WMBallotpaper::empty( $_SESSION['pug_session']['ballotpaper_id'] );
                    $_SESSION['pug_session']['login']=true;
                    $_SESSION['pug_session']['error'] = [];
                    $voter = $db->direct('select session_id from voters where voter_id = {voter_id} and completed=1', ['voter_id'=>$_SESSION['pug_session']['voter_id'],'session_id'=>session_id()] );
                    if (count($voter)>0){ 
                        WMInit::_initrun($request,$result);
                        $_SESSION['pug_session']['login']=false;
                        $_SESSION['pug_session']['error'][] = "Sie haben bereits teilgenommen.";
                        WMInit::$next_state = 'allready-voted';
                        return;
                    }
                    $voter = $db->direct('insert into voters ( voter_id, session_id ) values ( {voter_id}, {session_id}) on duplicate key update session_id = values(session_id) ',array_merge(['session_id'=>session_id()],$_SESSION['pug_session']));
                    
                    WMInit::$next_state = $db->singleValue('select value_plain from wm_texts where id=\'after_login_state\'',[],'value_plain');
                    if (WMInit::$next_state === false) WMInit::$next_state = 'legitimation';
                    

                    if (isset($_SESSION['pug_session']) && (isset($_SESSION['pug_session']['ballotpaper']))){
                        $interrupted = $db->singleValue('select unterbrochen from stimmzettel where id={id} ',$_SESSION['pug_session']['ballotpaper'],'unterbrochen');
                        if ($interrupted==1) $_SESSION['pug_session']['ballotpaper']['interrupted']=true;
                    }
                }
            }
        }else{
            
        }
        session_commit();
        
    }
}
