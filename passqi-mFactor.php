<?php
/**
* Plugin Name: passQi mFactor
* Plugin URI: https://www.passqi.com/mfactor-support/
* Description: This plugin enables logging in with passQi.
* Version: 1.0.8
* Author: passQi, Inc.
* Author URI: http://www.passqi.com
* License: GNU General Public License, Version 3
* Copyright: Copyright © 2016 passQi, Inc.
*/

define('PQ_DEBUG',true);





defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
include("pq-mfactor-paths.php"); 


//Register hooks
register_activation_hook(__FILE__, 'pq_login_install');


add_action( 'admin_menu', 'pq_login_menu' );
add_action( 'admin_init', 'pq_login_settings_init' );
add_action( 'show_user_profile', 'pq_login_show_extra_profile_fields' );
add_action( 'edit_user_profile', 'pq_login_show_extra_profile_fields' );
add_action( 'personal_options_update', 'pq_login_save_custom_user_profile_fields' );
add_action( 'edit_user_profile_update', 'pq_login_save_custom_user_profile_fields' );
add_filter( 'wp_authenticate_user', 'pq_login_edit_login', 13, 3 );
add_action( 'login_enqueue_scripts', 'pq_login_footer_message', 1000 );
add_action( 'user_register', 'pq_registration_save_custom_values', 10, 1 );
add_action( 'password_reset', 'pq_login_passqi_reset');
add_action( 'template_redirect', 'pq_callback_reply' );
add_action( 'init', 'pq_secure_cpt' );


//Set global vars
$host = $_SERVER["HTTP_HOST"];
$_P_USER;

//Error strings
define('PQ_ERROR_PQ_REQUIRED', __('<b>ERROR:</b> passQi is required to login.', 'pql_tdm'));
define('PQ_ERROR_INVALID_KEY', __('<b>ERROR:</b> Invalid public key.', 'pql_tdm'));
define('PQ_ERROR_NONEXISTENT_COOKIE', __('<b>ERROR:</b> passQi cookie does not exist. Try again later.', 'pql_tdm'));
define('PQ_ERROR_7', __('<b>ERROR:</b> Site does not have passQi public key. Please contact site administrator.', 'pql_tdm'));
define('PQ_ERROR_NONCE', __('<b>ERROR:</b> Nonce error. Try again later.', 'pql_tdm'));
define('PQ_ERROR_TOKENSMATCH', __('<b>ERROR:</b> passQi token error. If you keep getting this message, reset your password or contact administrator.', 'pql_tdm'));
define('PQ_ERROR_IDS', __('<b>ERROR:</b> passQi IDs do not match. This could happen if you are using an new device. Try resetting your password.', 'pql_tdm'));
define('PQ_ERROR_INVALID_COOKIE', __('<b>ERROR:</b> Invalid passQi cookie. Try again later. If you keep getting this message, close and reopen your browser.'));


//On activation of plugin
function pq_login_install(){

error_log("pq_login_install called");

	
	/** Ensure WordPress version is 4.2 or later **/
	if(version_compare( get_bloginfo( 'version' ), '4.2', '<' ) ){ //If version is less than 4.2
		deactivate_plugins( basename( __FILE__ ) ); //Deactivate plugin
	};
    
    /**Add passQi meta fields**/
    $_blogusers = get_users();
    foreach ( $_blogusers as $_user ) {
        pq_login_passqi_reset($_user, '', true);
    }
    
    error_log("in pq login install deleting public cert");
    
    if(!get_option('pq_public_cert')){
        update_option("pq_public_cert", "");
        update_option("pq_expire", 1);
    }
    
    $GLOBALS['pq_error'] = "";
  
    
    update_option("pq_uninstallkey", "");
    if(!get_option('pq_stats_totalUses')){
        update_option("pq_stats_totalUses", 0);
        update_option("pq_stats_totalUsers", 0);
        update_option("pq_stats_usersPctg", 0);
        update_option("pq_stats_usersRequire", 0);
        update_option("pq_stats_usersLastUsed", 0);
    }
    if(!get_option('pq_defaults_administrator')){
        update_option("pq_defaults_administrator", 0);
        update_option("pq_defaults_editor", 0);
        update_option("pq_defaults_author", 0);
        update_option("pq_defaults_contributor", 0);
        update_option("pq_defaults_subscriber", 0);
    }
    if(!get_option('pq_defaults_touch_administrator')){
        update_option("pq_defaults_touch_administrator", 0);
        update_option("pq_defaults_touch_editor", 0);
        update_option("pq_defaults_touch_author", 0);
        update_option("pq_defaults_touch_contributor", 0);
        update_option("pq_defaults_touch_subscriber", 0);
    }
    
   	$loginMessage = get_option('pq_enable_login_message');
   	
   	if(!$loginMessage)
   	{
        update_option('pq_enable_login_message', 0);
    }
  
    update_option('pq_mfactor_version', '1.0.6');

}



/**
 * Register options page
 */
function pq_login_menu() {
    /*Add options page*/
	add_users_page( __('passQi mFactor Options', 'pql_tdm'), __('passQi mFactor', 'pql_tdm'), 'manage_options', 'pql_options', 'pq_login_options' );

}

function pq_login_footer_message( ){
    if(get_option('pq_enable_login_message') == 1){
        wp_enqueue_script( 'footer-message', plugin_dir_url(__FILE__) . 'logintext.js' );
    }
}

/**
 * Get user role string
 */
function getrole($user){
    
    $cap = $user->caps;
 
    if(isset($cap['administrator']) && $cap['administrator'] == 1) return 'administrator';
    if(isset($cap['editor']) && $cap['editor'] == 1) return 'editor';
    if(isset($cap['author']) && $cap['author'] == 1) return 'author';
    if(isset($cap['contributor']) && $cap['contributor'] == 1) return 'contributor';
    if(isset($cap['subscriber']) && $cap['subscriber'] == 1) return 'subscriber';
    return 'subscriber';
}


/**
 * Create options pages, update options, etc.
 */
function pq_login_settings_init( ) {
    
    

    /**Register settings page**/
	register_setting( 'pqMfactorPage', 'pq_login_settings' );

    /**Add the main section**/
	add_settings_section(
		'pq_login_pluginPage_passQi', 
		'Users', 
		'pq_login_settings_section_callback', 
		'pqMfactorPage'
	);
    
    $tempArray = explode('&', $_SERVER['REQUEST_URI']);
    $pq_part = $tempArray[0];
    $backUri = get_protocol_string() . $_SERVER['HTTP_HOST'] . $pq_part;
    $offset = 0;
    if(isset($_GET['user_offset']) && ctype_digit($_GET['user_offset'])){
        if(wp_verify_nonce($_GET['_offsetnonce'], 'o' . $_GET['user_offset'])){
            $offset = sanitize_text_field($_GET['user_offset']);
        }else{
            wp_die("Invalid request. <a href='".$backUri."'>Back to mFactor options</a>");
        }
    }
    
    /**Update user meta per-user if this method is called when we have post data**/
    /**Used for the admin ovveride of passQi usage per-user**/
    if(isset($_GET["pqa"])){
        if($_GET["pqa"] == get_option("pq_uninstallkey") && wp_verify_nonce( $_GET['__uninstallnonce'], 'delete_key_'.get_option('pq_public_cert') ) && current_user_can('manage_options') ){
			error_log("uninstalling deleting pq_public_cert");
            update_option('pq_public_cert', '');
        }
    }
    

    if(get_option('pq_public_cert') == '' || (get_option('pq_public_cert') != '' && get_option('pq_expire') <= 40)){
        add_settings_section(
            'pq_login_pluginPage_public',
            'Public Key',
            'pq_login_public_section2_callback', 
            'pqMfactorPage'
        );
    }
    add_settings_section(
            'pq_login_pluginPage_other',
            'Other',
            'pq_login_public_section3_callback', 
            'pqMfactorPage'
        );
    
    update_option('pq_uninstallkey', bin2hex(openssl_random_pseudo_bytes(4)));
    
    $pq_option_page = "";
    if(isset($_POST['option_page']))
    {
    	$pq_option_page = $_POST['option_page'];
    }
    
    if($pq_option_page == 'pqMfactorPage' && current_user_can('manage_options')){
        
        if(isset($_POST['poweredbyenable'])){
            $poweredbyenable = sanitize_text_field($_POST['poweredbyenable']);
        	
        	if($poweredbyenable == 1){
        		update_option('pq_enable_login_message',1);
        	}else if($poweredbyenable == 0){
                update_option('pq_enable_login_message',0);
            }
        
        }
        
      	$pq_default_users_u =   sanitize_text_field($_POST['pq_default_users_u'  ]);
        $pq_default_users_a =   sanitize_text_field($_POST['pq_default_users_a'  ]);
        $pq_default_touchid_a = sanitize_text_field($_POST['pq_default_touchid_a']);
        
        if($pq_default_users_u != '0' && get_role($pq_default_users_u) != NULL ){
            $defaultPqOptions = true;
            $defaultTouchOptions = true;

            if($pq_default_users_a == '0') $defaultPqOptions = false;
            if($pq_default_touchid_a == '0') $defaultTouchOptions = false;

            if($pq_default_users_a == '3') $pq_default_users_a = 0;
            if($pq_default_touchid_a == '3') $pq_default_touchid_a = 0;
            
            $active_role = get_role($pq_default_users_u);
            if(!empty($active_role) && zeroonetwo($pq_default_users_a)){ //Validation
            
                if($defaultPqOptions) update_option("pq_defaults_" . $pq_default_users_u,
                                                    $pq_default_users_a);

                if($defaultTouchOptions) update_option("pq_defaults_touch_" . $pq_default_users_u,
                                                       $pq_default_touchid_a);
            }

        }
        
        
        $user_offset = sanitize_text_field($_POST['user_offset']);
        
        if(!ctype_digit($user_offset)) $user_offset = 0; //Validate that user_offset is a number
        
        $_b_u = new WP_User_Query( array( 'number' => 20, 'offset' => $user_offset ) );
        if ( ! empty( $_b_u->results ) ) {
           foreach ( $_b_u->results as $_user ) {
               
           
               
               		if(isset($_POST['pq_require_admin_'.$_user->ID]))
               		{
               			 $pq_require_admin_user = sanitize_text_field($_POST['pq_require_admin_'.$_user->ID]);
               		}
               		else
               		{
               		 	$pq_require_admin_user = 0;
               		}
                  
                  if(isset($_POST['pq_require_touchid_admin_' . $_user->ID]))
               		{
               			 $pq_require_touchadmin_user = sanitize_text_field($_POST['pq_require_touchid_admin_'.$_user->ID]);
               		}
               		else
               		{
               		 	$pq_require_touchadmin_user =      0;
               		}
                  
                  
                   if(!zeroonetwothree($pq_require_admin_user))       //Validate that values are numeric
                       wp_die("Invalid value");//$pq_require_admin_user = 0; 
                       
                   if(!zeroonetwothree($pq_require_touchadmin_user)) //and within valid range
                       wp_die("Invalid value");//$pq_require_touchadmin_user = 0; 
                   
                   if($pq_require_admin_user == 2) {pq_login_passqi_reset( $_user, '' );}
                   
                   error_log("pq_require_admin" . " for user: " . $_user->ID . ": " . $pq_require_admin_user);
                	error_log("pq_require_touchid_admin" . " for user: " . $_user->ID . ": " . $pq_require_touchadmin_user);
                   
                   update_user_meta($_user->ID, 'pq_require_admin', $pq_require_admin_user);
                   update_user_meta($_user->ID, 'pq_require_touchid_admin', $pq_require_touchadmin_user);
               }
           }
       
       
       error_log("did update per user options now applying (overwriting) bulk");
        
        if(isset($_POST['pq_bulk_users_u'])){
        
        
            $pq_bulk_users_u = sanitize_text_field($_POST['pq_bulk_users_u']);
            
            $active_role = get_role($pq_bulk_users_u);
             
            
           
         
            
            if($pq_bulk_users_u != '0' && ( !empty($active_role) || $pq_default_users_u == 'ALL' ) ){
                
                $doPqOptions =    true;
                $doTouchOptions = true;
                
                $pq_bulk_users_a =   sanitize_text_field($_POST['pq_bulk_users_a'  ]); 
                $pq_bulk_touchid_a = sanitize_text_field($_POST['pq_bulk_touchid_a']); 
              
                
                if($pq_bulk_users_a == '0'   || !zeroonetwothree($pq_bulk_users_a))   {$doPqOptions =    false;}
                if($pq_bulk_touchid_a == '0' || !zeroonetwothree($pq_bulk_touchid_a)) {$doTouchOptions = false;} //Validate actions
                
                if($pq_bulk_users_a ==   '3') {$pq_bulk_users_a   = 0;}
                if($pq_bulk_touchid_a == '3') {$pq_bulk_touchid_a = 0;}
                
                $query_array = array ( 'number' => 100000000, 'offset' => 0 );
                if(!($pq_bulk_users_a == 'ALL')) 
                    $query_array = array_merge($query_array, array( 'role' => ucfirst($pq_bulk_users_u) ) );
                
                $_a_u = new WP_User_Query( $query_array );
                
                
                
                if ( ! empty( $_a_u->results ) ) {
                    foreach($_a_u->results as $_user){
                       if( $_user->caps[$pq_bulk_users_u] == 1 || $pq_bulk_users_u == 'ALL' ){
                           
                           if($doPqOptions){
                           
                           	
                               if($pq_bulk_users_a == 2) {pq_login_passqi_reset( $_user, '' );}
                               update_user_meta($_user->ID, 'pq_require_admin', $pq_bulk_users_a);     
                           }
                         
                           if($doTouchOptions){
                    
                               update_user_meta($_user->ID, 'pq_require_touchid_admin', $pq_bulk_touchid_a); 
                           }
                        
                       }
                    }
                }
            }
        }
        
        error_log("did bulk options");
        
        if(isset($_POST['pubKey']) ) {
        
        error_log("pubkey exists");
		
			$p_key = trim($_POST['pubKey'], " \r\n\t");
		
			if (strpos($p_key, "\r\n") !== false) {
				error_log("replacing");
				$p_key = str_replace("\r\n","\n",$p_key);
			}else{
				error_log("not replacing");
			}
		
			$pq_well_formed_key  = startsWith($p_key,"-----BEGIN CERTIFICATE-----") && endsWith($p_key,"-----END CERTIFICATE-----") && (preg_replace('/\s+/', '', $p_key));
			$pq_ssl_validates = openssl_get_publickey($p_key) != false;

			 $pq_sanitized_key = sanitize_text_field($p_key);
			 // verify newline stripped key compares with system sanitized text
			 $pq_newline_stripped_key = str_replace("\n"," ",$p_key); // sanitize strips newlines and does other checks
			 $pq_is_sanitized = $pq_sanitized_key == $pq_newline_stripped_key;
		 
			 if($pq_well_formed_key && $pq_ssl_validates && $pq_is_sanitized)
			 {
			

				update_option('pq_public_cert', $p_key);
			
				$keyArray = openssl_x509_parse($p_key);
				error_log("timestamp of cert is " . $keyArray['validTo_time_t'] . " compare to " . strtotime('today'));
				$diff = $keyArray['validTo_time_t'] - time();
				update_option("pq_expire", date("z", $diff));
			
				
			
			}else{
				if($p_key != ""){
				
					error_log($p_key);
					wp_die(PQ_ERROR_INVALID_KEY.'<br>'.$p_key);
				
				} 
			}
		}
		else
		{
			error_log("no public key");
		}
    }
}

/**
 * Updates public key expiration date
 */
function pq_update_public_expiration( ){
    $keyArray = openssl_x509_parse(get_option('pq_public_cert'));
    $foundKeyTag = preg_match("#\/OU=(.*)\CN#",$keyArray['name'],$keyTagMatches);

	if($foundKeyTag)
	{
    
    	$GLOBALS['pq-key-tag'] = " (" . $keyTagMatches[1] . ")";
    }	
    else
    {
    
   	 $GLOBALS['pq-key-tag'] = "bad key";
    }
    
    error_log("timestamp of cert is " . $keyArray['validTo_time_t'] . " compare to " . strtotime('today'));
    $diff = $keyArray['validTo_time_t'] - time();
    update_option("pq_expire", date("z", $diff));
}

/**
 * Check if a string starts with another string
 */
function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
}

/**
 * Check if a string ends with another string
 */
function endsWith($haystack, $needle) {
    // search forward starting from end minus needle length characters
    return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== FALSE);
}

function pq_login_public_section3_callback(){
    $extra = '';
    if(get_option('pq_enable_login_message') == 1){
        $extra = 'checked="checked"';
    }
    
    echo '<input type="checkbox" id="poweredbycb" name="poweredbyenable" value="1"' . $extra . '></input>&nbsp;<label for="poweredbycb">Enable <i>"Multi-factor login powered by passQi mFactor"</i> on login page</label>';
}

function zeroonetwo($val){
    if($val == 0 || $val == 1 || $val == 2) return true;
    
    return false;
}
function zeroonetwothree($val){
    if (zeroonetwo($val) || $val == 3) return true;
    
    return false;
}
    
/**
 * This function renders the list of users on the site,
 * as well as Bulk and Default Actions options 
 */
function pq_login_userlist_render( ){

    
    $tempArray = explode('&', $_SERVER['REQUEST_URI']);
    $pq_part = $tempArray[0];
    $buttonUri = get_protocol_string() . $_SERVER['HTTP_HOST'] . $pq_part;
    
    //Get offset of table
    $offset = 0;
    if(isset($_GET['user_offset']) && ctype_digit($_GET['user_offset'])){
        if(wp_verify_nonce($_GET['_offsetnonce'], 'o' . $_GET['user_offset'])){
            $offset = sanitize_text_field($_GET['user_offset']);
        }else{
            wp_die("Invalid request. <a href='".$buttonUri."'>Back to mFactor options</a>");
        }
    }
    
    //Store in hidden value to enable refresh
    echo '<input type="hidden" name="user_offset" value="'.$offset.'"></input>';
    
   
    
    //Show paging options at top of table
    $counted_users = count_users();
    $numUsers = $counted_users['total_users'];
    echo '<div class="tablenav top">';
    echo '<div class="tablenav-pages" style="float:left">';
    echo '<span class="displaying-num">Displaying '. ($offset+1) . '-' . min(($offset+20),$numUsers) . ' of ' . $numUsers . ' users.</span>';
    
    ////Show arrows and text based on current position/////
    
    
    
    if($offset>20){
        echo '<a class="first-page" href="' . $buttonUri . '">&lt;&lt;</a>&nbsp;';
    }else{
        echo '<span class="tablenav-pages-navspan">&lt;&lt;</span>&nbsp;';
    }
    if($offset>0){
        echo '<a class="prev-page" href="'. wp_nonce_url($buttonUri  . '&user_offset=' . ($offset-20), 'o' . ($offset-20), '_offsetnonce') . '">&lt;</a>&nbsp;';
    }else{
        echo '<span class="tablenav-pages-navspan">&lt;</span>&nbsp;';
    }
    echo (($offset/20)+1) . ' of ' . (ceil($numUsers/20)) . '&nbsp;';
    if($offset<($numUsers)){

        echo '<a class="next-page" href="' . wp_nonce_url($buttonUri . '&user_offset=' . ($offset+20), 'o' . ($offset+20), '_offsetnonce') . '">&gt;</a>&nbsp;';
   
    }else{
        echo '<span class="tablenav-pages-navspan">&gt;</span>&nbsp;';
    }
    if($offset<(ceil(($numUsers+20)))){
    	$tempArray = explode('&',$_SERVER['REQUEST_URI']);
        $thisoffset = (((ceil($numUsers/20))-1)*20);
        echo '<a class="last-page" href="'. wp_nonce_url($buttonUri . '&user_offset=' . $thisoffset, 'o' . $thisoffset, '_offsetnonce') . '">&gt;&gt;</a>&nbsp;';
    }else{
        echo '<span class="tablenav-pages-navspan">&gt;&gt;</span>&nbsp;';
    }
    echo '</div></div>';
    
    
    //Create table of users
    echo '<div>';
    echo '<table class="widefat">';
    echo '<thead>';
    echo '<tr><th>'.__('Name', 'pql_tdm').'</th><th>'.__('Username', 'pql_tdm').'</th><th>'.__('Role', 'pql_tdm').'</th><th>'.__('mFactor Status', 'pql_tdm').'</th><th>'.__('mFactor Options', 'pql_tdm').'</th><th>'.__('touchQi Status', 'pql_tdm').'</th><th>'.__('touchQi Options', 'pql_tdm').'</th></tr>';
    echo '</thead>';
    echo '<tbody>';
    
    //Options and reused translations
    $qiUsers = 0;
    $totalUsers = 0;
    $_disabled = __("Disallowed", "pql_tdm");
    $_enabled = __("Allowed", "pql_tdm");
    $_required = __("Forced", "pql_tdm");
    $_Chooseone = __("Choose one", "pql_tdm");
    $_forceOnRequire = __("Force on require", "pql_tdm");
	$_allusers = __('All Users', 'pql_tdm');
    $_administrators = __('Administrators', 'pql_tdm');
    $_editors = __('Editors', 'pql_tdm');
    $_authors = __('Authors', 'pql_tdm');
    $_contributors = __('Contributors', 'pql_tdm');
    $_subscribers = __('Subscribers', 'pql_tdm');
    
    //Query 20 users at a time on the site using the offset
    $query = new WP_User_Query( array( 'number' => 20, 'offset' => $offset ) );
            if ( ! empty( $query->results ) ) {
	           foreach ( $query->results as $_user ) {
                   $_pql_ad[0] = '';$_pql_ad2[0] = '';
                   $_pql_ad[1] = '';$_pql_ad2[1] = '';
                   $_pql_ad[2] = '';$_pql_ad2[2] = '';
                   
                   //Get user options to set current values of dropdowns
                   if(get_user_meta($_user->ID, 'pq_require_admin', true) == 1){
                        $_pql_ad[2] = 'selected';
                   }else if(get_user_meta($_user->ID, 'pq_require_admin', true) == 2){
                       $_pql_ad[0] = 'selected';
                   }else{
                       $_pql_ad[1] = 'selected';
                   }
                    if(get_user_meta($_user->ID, 'pq_require_touchid_admin', true) == 1){
                        $_pql_ad2[2] = 'selected';
                    }else if(get_user_meta($_user->ID, 'pq_require_touchid_admin', true) == 2){
                       $_pql_ad2[0] = 'selected';
                   }else{
                       $_pql_ad2[1] = 'selected';
                   }
                   
                   //Construct dropdowns
                   if(pq_login_get_pq_status($_user->ID) == __('Inactive', 'pql_tdm') || pq_login_get_pq_status($_user->ID) == __("Enabled", "pql_tdm")){
                       $touchSelect = 
                           '<select id="pq_req_touchid_admin" name="pq_require_touchid_admin_'.$_user->ID.'"/>' .
                            '<option value="2" '.$_pql_ad2[0].'>'.$_disabled.'</option>' .
                            '<option value="0" '.$_pql_ad2[1].'>'.$_enabled.'</option>' .
                            '<option value="1" '.$_pql_ad2[2].'>'.$_forceOnRequire.'</option>' .
                           '</td>
                          </tr>';
                   }else{
                       $touchSelect = 
                           '<select id="pq_req_touchid_admin" name="pq_require_touchid_admin_'.$_user->ID.'"/>' .
                            '<option value="2" '.$_pql_ad2[0].'>'.$_disabled.'</option>' .
                            '<option value="0" '.$_pql_ad2[1].'>'.$_enabled.'</option>' .
                            '<option value="1" '.$_pql_ad2[2].'>'.$_required.'</option>' .
                        '</td>
                      </tr>';
                   }
                   
                   //Echo current table row
                   echo '<tr><td>' . esc_html($_user->display_name) . '</td><td>' .
                        esc_html($_user->user_login). '</td><td>'.
                        esc_html(ucfirst(getrole($_user))). '</td><td>'.
                        esc_html(pq_login_get_pq_status($_user->ID)).'</td><td>' . 
                        
                        '<select id="pq_req_admin" name="pq_require_admin_'.$_user->ID.'"/>' .
                            '<option value="2" '.$_pql_ad[0].'>'.$_disabled.'</option>' .
                            '<option value="0" '.$_pql_ad[1].'>'.$_enabled.'</option>' .
                            '<option value="1" '.$_pql_ad[2].'>'.$_required.'</option>' .
                        '</td><td>'.
                      pq_login_get_tq_status($_user->ID).'</td><td>'.
                      $touchSelect;
                if(get_user_meta($_user->ID, 'pq_require', true) == 'req' || get_user_meta($_user->ID, 'pq_require_admin', true) == 1) {$qiUsers++;}
                   $totalUsers++;
	           }
            
            } else {
	           echo __('No users found.', 'pql_tdm');
            }
    echo '</tbody>';
    echo '</table></div><br>';
    echo '<strong>'.__('Bulk Actions', 'pql_tdm').' </strong><br>'.__('for all existing', 'pql_tdm').'&nbsp;<select name="pq_bulk_users_u">';
    
        echo '<option value="0" selected>&lt;'.$_Chooseone.'&gt;</option>';
        echo '<option value="ALL">'.$_allusers.'</option>';
        echo '<option value="administrator">'.$_administrators.'</option>';
        echo '<option value="editor">'.$_editors.'</option>';
        echo '<option value="author">'.$_authors.'</option>';
        echo '<option value="contributor">'.$_contributors.'</option>';
        echo '<option value="subscriber">'.$_subscribers.'</option>';
    echo '</select>&nbsp;set passQi options to&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<select name="pq_bulk_users_a">';
        echo '<option value="0" selected>&lt;'.$_Chooseone.'&gt;</option>';
        echo '<option value="2">'.$_disabled.'</option>';
        echo '<option value="3">'.$_enabled.'</option>';
        echo '<option value="1">'.$_required.'</option>';
    echo '</select>';
    echo '&nbsp;and touchQi options to&nbsp;<select name="pq_bulk_touchid_a">';
        echo '<option value="0" selected>&lt;'.$_Chooseone.'&gt;</option>';
        echo '<option value="2">'.$_disabled.'</option>';
        echo '<option value="3">'.$_enabled .'</option>';
        echo '<option value="1">'.$_forceOnRequire.'</option>';
    echo '</select><br><br>';
    
    echo '<strong>Set Defaults </strong><br>for all new &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<select name="pq_default_users_u">';
        echo '<option value="0" selected>&lt;'.$_Chooseone.'&gt;</option>';
        echo '<option value="administrator">'.$_administrators.'</option>';
        echo '<option value="editor">'.$_editors.'</option>';
        echo '<option value="author">'.$_authors.'</option>';
        echo '<option value="contributor">'.$_contributors.'</option>';
        echo '<option value="subscriber">'.$_subscribers.'</option>';
    echo '</select>&nbsp;default passQi options to&nbsp;<select name="pq_default_users_a">';
        echo '<option value="0" selected>&lt;'.$_Chooseone.'&gt;</option>';
        echo '<option value="2">'.$_disabled.'</option>';
        echo '<option value="3">'.$_enabled .'</option>';
        echo '<option value="1">'.$_required.'</option>';
    echo '</select>';
    echo '&nbsp;and touchQi options to&nbsp;<select name="pq_default_touchid_a">';
        echo '<option value="0" selected>&lt;'.$_Chooseone.'&gt;</option>';
        echo '<option value="2">'.$_disabled.'</option>';
        echo '<option value="3">'.$_enabled .'</option>';
        echo '<option value="1">'.$_forceOnRequire.'</option>';
    echo '</select><br><br>';
    
    echo '<strong>Current Defaults </strong><br>';
        echo '<span style="width:8em;float:left;display:inline-block;">Administrators: </span><strong>passQi ' . 
            pq_getQiOptionName( get_option('pq_defaults_administrator'), false ) . ', touchQi '.
            pq_getQiOptionName( get_option('pq_defaults_touch_administrator') ) .
            '</strong><br>';
    echo '<span style="width:8em;float:left;display:inline-block;">Editors: </span><strong>passQi ' . 
            pq_getQiOptionName( get_option('pq_defaults_editor'), false ) . ', touchQi '.
            pq_getQiOptionName( get_option('pq_defaults_touch_editor') ) .
            '</strong><br>';
    echo '<span style="width:8em;float:left;display:inline-block;">Authors: </span><strong>passQi ' . 
            pq_getQiOptionName( get_option('pq_defaults_author'), false ) . ', touchQi '.
            pq_getQiOptionName( get_option('pq_defaults_touch_author') ) .
            '</strong><br>';
    echo '<span style="width:8em;float:left;display:inline-block;">Contributors: </span><strong>passQi ' . 
            pq_getQiOptionName( get_option('pq_defaults_contributor'), false ) . ', touchQi '.
            pq_getQiOptionName( get_option('pq_defaults_touch_contributor') ) .
            '</strong><br>';
    echo '<span style="width:8em;float:left;display:inline-block;">Subscribers: </span><strong>passQi ' . 
            pq_getQiOptionName( get_option('pq_defaults_subscriber'), false ) . ', touchQi '.
            pq_getQiOptionName( get_option('pq_defaults_touch_subscriber') ) .
            '</strong>';
    
    
}

/**
 * Get option name from value
 */
function pq_getQiOptionName( $value, $onrequire = TRUE ){
    $_disabled = __("Disallowed", "pql_tdm");
    $_enabled = __("Allowed", "pql_tdm");
    $_forceOnRequire = __("Forced on require", "pql_tdm");
    $_force = __("Forced", "pql_tdm");
    if( $value == 0 ){
        return $_enabled;
    }else if( $value == 1 ){
        if($onrequire) return $_forceOnRequire;
        return $_force;
    }else{ //Will return disabled if disabled or malformed database value
        return $_disabled;
    }
}

/**
 * Get status of passQi for current user
 */
function pq_login_get_pq_status($userid){
    
    $stat = __('Inactive', 'pql_tdm');
    if (get_user_meta($userid, "pq_may_require", true) == 1) $stat = __("Enabled", 'pql_tdm');
    if (get_user_meta($userid, "pq_require", true) == 'req') $stat = __("Required [user]", 'pql_tdm');
    if (get_user_meta($userid, 'pq_require_admin', true) == 1) $stat = __("Required [forced]",'pql_tdm');
    return $stat;
}

/**
 * Get status of touchQi for current user
 */
function pq_login_get_tq_status($userid){
    
    $stat = __('Inactive', 'pql_tdm');
    if (get_user_meta($userid, "pq_require", true) == 'req' || get_user_meta($userid, 'pq_require_admin', true) == 1){
        $stat = __('Enabled', 'pql_tdm');
    }
    if (get_user_meta($userid, "pq_require_touchid", true) == 1) $stat = __("Required [user]", 'pql_tdm');
    if (get_user_meta($userid, 'pq_require_touchid_admin', true) == 1) $stat = __("Required [forced]",'pql_tdm');
    return $stat;
}

/**
 * This function doesn't do much as of now
 */
function pq_login_settings_section_callback(  ) { 
    
    pq_login_userlist_render();
}
/**
 * Renders 'get public key' box
 */
function pq_login_public_section_callback(  ) {
    
    update_option('pq_random_hash', '');
    update_option('pq_random_s', '');
    update_option('pq_random_timestamp', '');
    if(get_option('pq_public_cert') == '' || (get_option('pq_public_cert') != '' && get_option('pq_expire') <= 40)){
        $new = (get_option('pq_public_cert') == '');
        echo '<div style="width:40%; float:left; background: #ccc; padding: 8px; border-radius:4px; -moz-border-radius: 4px; -webkit-border-radius:4px;">';
        if($new) {echo '<h2>'.__('Get a Public Key', 'pql_tdm').'</h2><br />';} else {echo '<h2>Renew your public key</h2><br />';};
        if($new) {
            $pq_expire = get_option('pq_expire');
        
            if(!ctype_digit($pq_expire)) $pq_expire = 0;
            echo __('A subscription and public key is required to enable passQi mFactor.<br />', 'pql_tdm');}else{echo 'Your public key will expire in ' . $pq_expire . ' days. You should get a new one to ensure your site\'s security.';};
        $site = $_SERVER['SERVER_NAME'];
        $cursite = siteurl();
        echo '<form name="pq_getPubKey" action="' . $GLOBALS['pq-store-action-url'] . '" method="POST" target="_blank">';
        echo '<!--Your secure token is: --><br><span class="color:#ccc"><input type="hidden" name="securetk" contentEditable="false" value="'.pq_login_generate_secure_token().'"></input></span>';
        
        echo '<input name="wp_root" type="hidden" value="' . $cursite . '">';
        echo '<input name="siteraw" type="hidden" value="' . $site . '">';
        echo '<input name="protocol" type="hidden" value="' . get_protocol() . '">';
        echo '<input name="integration" type="hidden" value="wp-plugin">';
        echo '<input name="deployed" type="hidden" value="' . $GLOBALS['pq-deploy-env'] . '">';
        
        $exp = get_option('pq_expire');if($new){$exp='n';}
        echo '<input name="cert_expire" type="hidden" value="' . esc_html($exp) . '">';                   
        global $user_email;
        get_currentuserinfo();
        echo '<input name="email_hash" type="hidden" value="'. hash("sha512", $user_email . $site) . '">';
        echo '<input class="right button button-primary" type="submit" value="Get your public key"></input>';
        echo '</form>';
        echo '</div>';

	error_log('rendering get cert form');
 	echo '<!-- got here -->';
        
    }else{
        pq_update_public_expiration();
        echo '<div style="float:left">'.__('A public key is installed.'). $GLOBALS['pq-key-tag'] .'.<p>'; 
        if(get_option('pq_expire') > 0){
            echo 'Your public key will expire in ' . get_option('pq_expire') . ' days.';
        }else{
            echo '<strong>WARNING:</strong> Your public key has expired!';
        }
        $nonce = wp_create_nonce( 'delete_key_' . get_option('pq_public_cert') );
        echo '</p><p><a href="?page=pql_options&pqa='.get_option("pq_uninstallkey").'&__uninstallnonce='.$nonce.'">Uninstall key</a></p>';
        echo '</div>';
    }
    echo '<div style="width:20%; float:right; background: #fff; padding: 8px; padding-top: 0px; border-radius:4px; -moz-border-radius: 4px; -webkit-border-radius:4px;-webkit-box-shadow: inset 0px -3px 2px 0px rgba(0,0,0,0.37);-moz-box-shadow: inset 0px -3px 2px 0px rgba(0,0,0,0.37);box-shadow: inset 0px -3px 2px 0px rgba(0,0,0,0.37);">';
        echo '<h2>Help</h2>';
        echo '<a href="https://passqi.com/mfactor-support">'.__('Support and Documentation', 'pql_tdm').'</a>';
        echo '</div>';
}

if (!function_exists('base_url')) {
    function base_url($atRoot=FALSE, $atCore=FALSE, $parse=FALSE){
        
        return $_SERVER["HTTP_HOST"];
    }
}

/**
 * Gets if https is used
 */
function get_protocol(){
    if(is_ssl() || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on')
    ){
        return "1";
    }
    return "0";
}

/**
 * Returns either 'http' or 'https' depending on protocol in use
 */
function get_protocol_string($useslashes = TRUE){
   
    $prstring = "";
    if(get_protocol() == "0"){
        $prstring = "http";
    }else{
        $prstring = "https";
    }
    if($useslashes) $prstring = $prstring . "://";
    return $prstring;
        
}
        
/**
 * Gets site path to WP install
 */
function siteurl(){               
	if ( is_multisite() ) {
		global $blog_id;
		$siteurl = get_blog_details($blog_id)->path;
	} else {
        $siteurl = str_replace(get_protocol_string() . base_url(TRUE),"",get_bloginfo('wpurl'));   
	}
	return $siteurl;
}

function pq_login_public_section2_callback( ){
    echo '<textarea class="widefat" rows="7" cols="50" id="public" placeholder="'.__('Paste public key here', 'pql_tdm').'" name="pubKey"></textarea>';
}

/**
 * Generates token, salt, and hash for get public key
 */
function pq_login_generate_secure_token( ){
    $token_a = bin2hex(openssl_random_pseudo_bytes(32));
    $salt = bin2hex(openssl_random_pseudo_bytes(32));
    $hash = hash('sha256', $token_a . $salt);
    
    update_option('pq_random_hash', $hash);
    update_option('pq_random_s', $salt);
    update_option('pq_random_timestamp', time());
    return $token_a;
}

/**
 * Renders the options page
 */
function pq_login_options(  ) {
   

 
    //Ensure user has sufficient permissions
    if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.', 'pql_tdm' ) );
	}

	?>
    <div class="wrap">
    <h2><?php _e( 'passQi mFactor', 'pql_tdm' ) ?></h2>
    <br /><br />
    <?php pq_login_public_section_callback() ?>
	<form action='options.php' method='post' style="clear:both;padding-top:20px;">
		
		<?php
		settings_fields( 'pqMfactorPage' );
		do_settings_sections( 'pqMfactorPage' );
		submit_button();
	
		?>
		
	</form>
    </div>
	<?php

}

/**
 * Reset passQi values on password reset, new user, reinstall, etc.
 * @param _user WP_User to reset
 * @param unused
 * @param checkExisting whether overwrite preexisting values
 */
function pq_login_passqi_reset($_user, $unused, $checkExisting = FALSE){
        
    if(!$checkExisting || ($checkExisting && get_user_meta($_user->ID, 'pqid', true) == '')){
        update_user_meta($_user->ID, 'pqid', '');
        update_user_meta($_user->ID, 'pqToken', '');
        update_user_meta($_user->ID, 'pqSalt', '');
        update_user_meta($_user->ID, 'pqTokenHashed', '');
        update_user_meta($_user->ID, 'pq_require', '');
        update_user_meta($_user->ID, 'pq_may_require', '0');
        update_user_meta($_user->ID, 'pq_require_admin', '0');
        update_user_meta($_user->ID, 'pq_require_admin', get_option('pq_defaults_' . getrole($_user)));
        update_user_meta($_user->ID, 'pq_require_touchid', '0');
        update_user_meta($_user->ID, 'pq_require_touchid_admin', '0');
        update_user_meta($_user->ID, 'pq_require_touchid_admin', get_option('pq_defaults_touch_' . getrole($_user)));
        update_user_meta($_user->ID, 'pq_login_count', '0');
        update_user_meta($_user->ID, 'pq_login_count_touchid', '0');
    }
}

/**
 * Add custom fields to user meta
 */
function pq_registration_save_custom_values( $user_id ) {

    update_user_meta($user_id, 'pqid', '');
    update_user_meta($user_id, 'pqSalt', '');
    update_user_meta($user_id, 'pqTokenHashed', '');
    update_user_meta($user_id, 'pq_require', '');
    update_user_meta($user_id, 'pq_may_require', '0');
    update_user_meta($user_id, 'pq_nonces', '');
    update_user_meta($user_id, 'pq_require_touchid', '0');
    update_user_meta($user_id, 'pq_require_touchid_admin', '0');
    update_user_meta($user_id, 'pq_login_count', '0');
    update_user_meta($user_id, 'pq_login_count_touchid', '0');
    update_user_meta($user_id, 'pq_login_audithash', '0');

}

/**
 * Top-level auth procedure
 */
function pq_login_edit_login( $user, $password ){
    pq_update_public_expiration( );
    $_P_USER = $user;

       //First ensure password is correct
	   $pq_login_pass_ok = wp_check_password($password, $user->user_pass, $user->ID);
    
	   if ( $pq_login_pass_ok ) {
           
           //If passQi cookie exists
           if( _pq_checkCookieType() != 'f' ) {
               
               error_log( 'checkCookie - exists' );

               //If the user or admin requires passQi
               if( get_user_meta($user->ID, "pq_require", true) == "req" || get_user_meta($user->ID, "pq_require_admin", true) == 1 ){
                   //Authenticate
				   error_log( 'passqi required' );
                   //Ensure existence of values
                   if ( strlen( get_user_meta( $user->ID, 'pqTokenHashed', true ) ) < 1 && get_option( 'pq_expire' ) > 0 ) {
                       error_log( 'populating' );
                       _pq_populateUserWithPassqiValues( $user, _pq_checkCookieType( ) );
                   } else if( !pq_login_authp( $user->user_login, $user ) ) {
                       //Fail
                       if( $GLOBALS['pq_error'] != 'SUCCEED_ON_TIME' ){
                            error_log("fail authp");
                            remove_action('authenticate', 'wp_authenticate_username_password', 20);
                            $user = new WP_Error( 'denied', $GLOBALS['pq_error'] );
                       }
                   } //If it doesn't fail, just continue on
               }else{
                   //If they don't require passQi
                   //Populate with passQi values if they don't have any
                   if(strlen(get_user_meta($user->ID, 'pqTokenHashed', true)) < 1 && get_option('pq_expire') > 0){
                       error_log("populating");
                       _pq_populateUserWithPassqiValues($user, _pq_checkCookieType());
                   }
                   if(get_user_meta($user->ID, "pq_may_require", true) == '0' && get_user_meta($user->ID, "pq_may_require", true) != '2'){
                        update_user_meta($_P_USER->ID, "pq_may_require", '1');
                        update_option("pq_stats_usersLastUsed", get_option("pq_stats_usersLastUsed") + 1);
                   }
                   update_option("pq_stats_totalUses", get_option("pq_stats_totalUses") + 1);
                   
                   
               }
           }else{
               if(get_user_meta($user->ID, "pq_require", true) == "req" || get_user_meta($user->ID, "pq_require_admin", true) == 1){
                       remove_action('authenticate', 'wp_authenticate_username_password', 20);
                       $user = new WP_Error( 'denied', PQ_ERROR_PQ_REQUIRED );
               }else{
                   if(get_user_meta($user->ID, "pq_may_require", true) == '1'){
                        update_user_meta($_P_USER->ID, "pq_may_require", '0');
                        update_option("pq_stats_usersLastUsed", get_option("pq_stats_usersLastUsed") - 1);
                   }
               }
           }
       };
    $h_split = explode(".", $_SERVER["HTTP_HOST"]);
    if(isset($_COOKIE['passqiMfaToken'])){
        unset($_COOKIE['passqiMfaToken']);
        setcookie('passqiMfaToken', '', time() - 3600);
        
    }
    if(isset($_COOKIE['passqiMfaToken_dev'])){
        unset($_COOKIE['passqiMfaToken_dev']);
        setcookie('passqiMfaToken_dev', '', time() - 3600);
    }
    return $user;
}

/**
 * Check dev or production cookie and return name
 */
function _pq_checkCookieType() {
    $_p_dcn = "passqiMfaToken_dev";
    $_p_cn = "passqiMfaToken";
    if(isset($_COOKIE[$_p_dcn])){
        return $_p_dcn;
    }
    if(isset($_COOKIE[$_p_cn])){
        return $_p_cn;
    }
    
    return "f";
}

/**
 * Populate user with initial passQi values
 */
function _pq_populateUserWithPassqiValues($user, $cookiename){
    $cookie = explode("#", $_COOKIE[$cookiename]);
    error_log("cookie text populate: " . print_r($cookie,true));
    
    $pubKeyCert = get_option('pq_public_cert', '');
    error_log("Populating user");
    
         
           $decryptedCookieResult =         _pq_getDecryptedPassQiToken($cookie,$user->user_login,$pubKeyCert);
           
    list($hasValidCookie,$cookiePassQiId,$cookiePassQiToken,$preShiftToken,$shift,
         $didTouchAuthId,$mfaSsid,$mfaSession,$mfaAppBrand,$auditHash,$canPerformTouchAuth) = $decryptedCookieResult;

        
        
    update_user_meta($user->id, 'pqid', $cookiePassQiId);
    
    
    $_p_salt = bin2hex(openssl_random_pseudo_bytes(64));
    
    update_user_meta($user->id, 'pqSalt', $_p_salt);
    update_user_meta($user->id, 'pqTokenHashed', hash("sha512", $cookiePassQiToken . $_p_salt, false));
    update_user_meta($user->id, 'pq_require', '');
    update_user_meta($user->id, 'pq_may_require', 0);
    update_user_meta($user->id, 'pq_login_audithash', $auditHash);
    
    if(get_user_meta($user->ID, 'pq_login_count', true) == 0 || 
       get_user_meta($user->ID, 'pq_login_count', true) == ""){
        update_user_meta($_user->ID, 'pq_login_count', '0');
    }
    if(get_user_meta($user->ID, 'pq_login_count_touchid', true) == 0 || 
       get_user_meta($user->ID, 'pq_login_count_touchid', true) == ""){
        update_user_meta($_user->ID, 'pq_login_count_touchid', '0');
    }
}

/**
 * Low-level passQi auth interface
 */
function pq_login_authp($username, $user){

    error_log("authp");
    $cookieName = _pq_checkCookieType();
   $GLOBALS['pq_error'] = "";
    $pubKeyCert = get_option('pq_public_cert', '');
    $cookieText = $_COOKIE[$cookieName];
    
    
    error_log("cookie text login authp: " . $cookieText);
    
    $infoPassQiToken = strtoupper(get_user_meta($user->ID, "pqTokenHashed", true));
    $requiresPassqi = true;
    $infoPassQiId = get_user_meta($user->ID, "pqid", true);
    $_P_USER = $user;
    
    $isPassQiTokenOk = false;
	
    error_log("authp::2");
    if(strlen($cookieText) < 1){
        $hasNoActiveCookie = true;
        $hasValidCookie = false;
        $GLOBALS['pq_error'] =PQ_ERROR_NONEXISTENT_COOKIE;
    }else{
        $hasNoActiveCookie = false;	
        $cookie = explode("#",$cookieText); 
        $GLOBALS['pq_error'] = '';
        
        error_log("shift as cookie[1]: " . $cookie[1]);
      
        
        $decryptedTokenResult = 
        _pq_getDecryptedPassQiToken($cookie,$username,$pubKeyCert);
        
          list($hasValidCookie,$cookiePassQiId,$cookiePassQiToken,
        $preShiftToken,$shift,$didTouchAuthId,
        $mdaSsid,$mfaSession,$mfaAppBrand,$auditHash,$canPerformTouchAuth) = $decryptedTokenResult;

        
        if($GLOBALS['pq_error'] == PQ_ERROR_7 || $GLOBALS['pq_error'] == "passQi error: Nonce failure"){
            error_log('failing on pq_error');
            return false;
        }
    }

	if($requiresPassqi)
	{
        error_log("authp::passed.1");
        if(isset($hasValidCookie) && $hasValidCookie)		// cookie is for user and must use mfa
        {
            error_log("authp::passed.2");
            error_log("authp:: retreive as info[...] = ".$infoPassQiId." AND cookie to ".$cookiePassQiId);
            if($infoPassQiId == $cookiePassQiId){
                error_log("authp::passed.INFO_EQUAL");
				$isPassQiIdOk = true;
                error_log("2nd with ". $cookiePassQiToken . " to ". $infoPassQiToken . " compare at " . strtoupper(hash("sha512", $cookiePassQiToken . get_user_meta($user->ID, "pqSalt", true))));
                if($infoPassQiToken == strtoupper(hash("sha512", $cookiePassQiToken . get_user_meta($user->ID, "pqSalt", true)))){
                    $isPassQiTokenOk = true;
                    error_log("its ok");
                    $authSuccess = true;
                    update_user_meta($_P_USER->ID, "pq_may_require", 1);
                }else{
                    $authSuccess = false;
                    update_user_meta($_P_USER->ID, "pq_may_require", 0);
                    $GLOBALS['pq_error'] = PQ_ERROR_TOKENSMATCH;
				}
            }else{
				$isPassQiIdOk = false;
				$authSuccess = false;
                update_user_meta($_P_USER->ID, "pq_may_require", 0);
                $GLOBALS['pq_error'] = PQ_ERROR_IDS;
            }
        }else if($requiresPassqi && (!isset($hasValidCookie) || (isset($hasValidCookie) && !$hasValidCookie)) ){
			$authSuccess = false;
            error_log("invalid_cookie!");
            $GLOBALS['pq_error'] = PQ_ERROR_INVALID_COOKIE;
        }
    }else{
        $authSuccess = true;		// password good, does not require passQi
        update_user_meta($_P_USER->ID, "pq_may_require", 0);
    }

    error_log("evaluating need touch auth id");
    $needsTouchAuth=false;
    if( (get_user_meta($user->ID, 'pq_require_touchid', true) == 1 || get_user_meta($user->ID, 'pq_require_touchid_admin', true) == 1) && get_user_meta($user->ID, 'pq_require_touchid_admin', true) != 2) { 
        $needsTouchAuth = true; 
    }

    if($authSuccess){
        if($needsTouchAuth){
            
            if($didTouchAuthId == "1"){
                error_log("did touch auth");
                update_user_meta($_P_USER->ID, "pq_login_count_touchid", 
                                 get_user_meta("pq_login_count_touchid", $_P_USER->ID) + 1);
                return true;
                
            }else if($canPerformTouchAuth == 0){
               $GLOBALS['pq_error'] = "passQi Error: Device does not support touchQi authentication.";
                return false;

            }else{
                error_log("needs touch auth fail with request");

                $requestTouchAuthIdUrl = $GLOBALS['pq-requestTouchAuthIdUrlPrefix'];
                
                $requestId = bin2hex(openssl_random_pseudo_bytes(32));
                
                setcookie("touchQiRequestId", $requestId, 0, "/", $_SERVER['HTTP_HOST'], false, true);
                
                // this should be a POST so it is not visible and encrypted by the ssl connection
                
                $requestTouchAuthIdUrl = $requestTouchAuthIdUrl . "?sessionId=" . $mfaSession . "&requestId=" . $requestId  ;
              
sleep(4);
                $getTouchResponse = file_get_contents($requestTouchAuthIdUrl);
                error_log("get touchid response: " . $getTouchResponse);
                $GLOBALS['pq_error'] = "Notice: Perform touchQi auth with app, then click passQi bookmark to continue login";
                return false;
            }
            
        }else{
        update_user_meta($_P_USER->ID, "pq_login_count", get_user_meta($user->ID, "pq_login_count", true) + 1);
        return true;
        }
    }else{
        return false;
    }

}

/**
 * User profile page
 */
function pq_login_show_extra_profile_fields( $user ){
    
    $_P_USER = $user;
    pq_update_public_expiration( );
    
    $exp = (get_option('pq_expire') <= 0);
    if((get_user_meta($user->ID, 'pq_may_require', true) == '1' && get_user_meta($user->ID, 'pq_require_admin', true) != '2') || get_user_meta($user->ID, 'pq_require_admin', true) == 1){
        if(get_user_meta($user->ID, 'pq_require_admin', true) == 0 || get_user_meta($user->ID, 'pq_require_admin', true) == 1){
            $dat=__('Enables passQi mFactor - Login requires use of passQi (Enables two-factor security).', 'pql_tdm');
            if(get_user_meta($user->ID, 'pq_require_touchid_admin', true) != 1 && get_user_meta($user->ID, 'pq_require_touchid_admin', true) != 2){
                $dat2=__("Require scanning your fingerprint on each login to enforce enhanced authentication. Must use a supported device.", 'pql_tdm');
            }else{
                $dat2=__("The administrator has overriden this option for your account.", 'pql_tdm');
            }
        }else{
            $dat=__('The administrator has overriden this option for your account.', 'pql_tdm');
            $dat2=__("This option is unavailable.", 'pql_tdm');
        }
        
        if(get_user_meta($user->ID, 'pq_require_admin', true) == 1){
            $dat=__('The administrator has overriden this option for your account.', 'pql_tdm');
        }
        
        if($exp){
            $dat='<span style="color:#900"><i><strong>&nbsp;'.__('WARNING:', 'pql_tdm').' </strong>'.__('Site is no longer enforcing passQi only login due to expired certificate. Please contact site administrator.', 'pql_tdm').'</i></span>';
            $dat2=__("This option is currently unavailable.", 'pql_tdm');
        }
        
        echo '<table class="form-table">';
        echo '<tr><th><label for="pq_require_user">'.__('Require passQi to login', 'pql_tdm').'</label></th>';
        $_pql_chk = "";
        $showsFingerprint = false;

        if(get_user_meta($user->ID, 'pq_require', true) == "req" || get_user_meta($user->ID, 'pq_require_admin', true) == 1){
            $_pql_chk = ' checked="checked" ';
            $showsFingerprint = true;
        }
        if(get_user_meta($user->ID, 'pq_require_admin', true) == 1 || $exp){
            $_pql_chk = $_pql_chk .' disabled="disabled"';
        }
        echo '<td><input type="checkbox" onclick="pq_ToggleCheckbox(this)" id="pq_req_passqi" name="pq_require" value="req"'.$_pql_chk.'/>&nbsp;<span class="description">'.$dat. '</span></td></tr>';
        
        if(get_user_meta($user->ID, 'pq_require_touchid_admin', true) != 2){
            $fingerStyle = "visibility:hidden";
            if($showsFingerprint) { $fingerStyle = ""; }
            $_pql_chk2 = "";
            echo '<tr id="pq_fingerprint_disable" style="' . $fingerStyle . '"><th><label for="pq_require_fingerprint">'.__('Require fingerprint on each login', 'pql_tdm').'</label></th>';
            if(get_user_meta($user->ID, 'pq_require_touchid', true) == 1 || get_user_meta($user->ID, 'pq_require_touchid_admin', true) == 1 ){
                $_pql_chk2 = ' checked="checked" ';

            }
            if(get_user_meta($user->ID, 'pq_require_touchid_admin', true) == 1 || get_user_meta($user->ID, 'pq_require_touchid_admin', true) == 2 || $exp){
                $_pql_chk2 = $_pql_chk2 . ' disabled="disabled" ';
            }
            echo '<td><input type="checkbox" id="pq_req_touchid" name="pq_require_touchid" value="1"'.$_pql_chk2.'>&nbsp;<span class="description">'.$dat2. '</span></td></tr>';
        }
        echo '</table>';
    }
    
    wp_enqueue_script( 'profile-fields', plugin_dir_url(__FILE__) . 'userprofile.js' );
    
}


/**
 * Abstraction for get_user_meta
 */
function _pq_get_user_info($title){
    return esc_attr( get_user_meta( $_P_USER->ID, $title, true ) );
}


/**
 * Get decrypted token
 */
 
 // was passed by reference: cookieInfo
  
function _pq_getDecryptedPassQiToken ($cookieInfo,  $username, $cert){
    
    error_log("pq.pq.GET_TOKEN");
   
    global $break;

    $sessionId =  $cookieInfo[0];
    $shift = $cookieInfo[1];
    $mfa =  $cookieInfo[2];
    
    error_log("dump cookieInfo array: " . print_r($cookieInfo,true));
    error_log("shift in get decrypted token: " . $shift);

    $mfaInfoJson  = base64_decode($mfa);
    error_log($mfaInfoJson);
    $mfaInfo = json_decode($mfaInfoJson,TRUE);
    
    $encryptedUserToken = $mfaInfo["userSiteTokenEncrypted"];
    $integrityHash = $mfaInfo["userIntegrityHash"];
 
    $version = $mfaInfo["version"];
    $opts = $mfaInfo["opts"];
    $session = $mfaInfo["sessionID"];
    $time = $mfaInfo["timestamp"];
	$didTouchAuthId = $mfaInfo['didTouchAuth'];
    $canPerformTouchAuth = $mfaInfo['canPerformTouchAuth'];
    $mfaSsid = $mfaInfo["ssid"];

	$touchQiSignature = '';
    if(isset($mfaInfo['touchQiSignature']))
    {
    	$touchQiSignature = $mfaInfo['touchQiSignature'];
    }

	error_log("touch qi signature: " . $touchQiSignature);
	
    $mfaAppBrand = $mfaInfo["appBrand"];

    $auditHash = 1;
    $_NONCE_ERROR = false;
    
    error_log($username);
    $timeFiveMinutesAgo = strtotime('-5 minutes');
    
    error_log("time five minutes ago: " . $timeFiveMinutesAgo . " cookie time: " . strtotime($time));
    
    if(!($timeFiveMinutesAgo > strtotime($time))){
        $infoNonces = explode(':', get_user_meta(get_user_by('login', $username)->ID, 'pq_nonces', true));
        
        if( get_user_meta(get_user_by('login',$username)->ID, 'pq_nonces', true) == '' ){
            error_log('(nonce) zero found');
            update_user_meta(get_user_by('login',$username)->ID, 'pq_nonces', time() . ':' . $shift);
        }else{
            error_log('(nonce) greater than zero found');
            $laststamp = '';
            $isstamp = true;
            $noncefound = false;
            $noncesAndStampsFiltered = array();
            foreach($infoNonces as $val){
                if($isstamp){
                    $laststamp = $val;
                    $timeFiveMinutesAgo = strtotime('-5 minutes');
                    if($timeFiveMinutesAgo > $laststamp){
                        $wasexpired = true;
                    }else{
                        $wasexpired = false;
                    }
                    $isstamp = false;
                    
                }else{
                    if(!$wasexpired){
                        $noncesAndStampsFiltered = array_merge($noncesAndStampsFiltered, array($laststamp, $val));
                        if($shift == $val){
                        
                        error_log('nonce found ' . $shift . " " . $val);
                            $noncefound = true;
                        }
                    }
                    $isstamp = true;
                }
            }


            if(!$noncefound){
            error_log('no nonce found');
                $noncesAndStampsFiltered = array_merge(array(time(), $shift), $noncesAndStampsFiltered);
                $noncesAndStampsStringified = '';
                $isstamp = true;
                foreach($noncesAndStampsFiltered as $nonceval){
                    if($isstamp){
                        $noncesAndStampsStringified .= $nonceval;
                        $noncesAndStampsStringified .= ':';
                        $isstamp = false;
                    }else{
                        $noncesAndStampsStringified .= $nonceval;
                        $noncesAndStampsStringified .= ':';
                        $isstamp = true;
                    }
                }
                $noncesAndStampsStringified = rtrim($noncesAndStampsStringified, ":");
                update_user_meta(get_user_by('login', $username)->ID, 'pq_nonces', $noncesAndStampsStringified);
            }else{
            
            error_log('setting nonce failure: ' . $shift);
              $GLOBALS['pq_error'] = "passQi error: Nonce failure";
                $_NONCE_ERROR = true;
            }
        }
                
    }else{
    	error_log("setting nonce error");
        $GLOBALS['pq_error'] = PQ_ERROR_NONCE;
        $_NONCE_ERROR = true;
    }
    
    $decryptedTokenInput = _pq_roundTripWithPublicKey($encryptedUserToken, $cert);
    if($decryptedTokenInput == "f"){
        $GLOBALS['pq_error'] = PQ_ERROR_7;
    }else if($decryptedTokenInput == "y"){
       $GLOBALS['pq_error'] = "SUCCEED_ON_TIME";
    }

    $elements = explode(":",$decryptedTokenInput);
    $decryptedPassQiId = $elements[0];

    
    $subVersion = $elements[2];
    

    if($subVersion == "01"){
        $decryptedPreMorphPassQiPassword = strtoupper($elements[1]);
        $restoredPassQiPassword = strtoupper(_pq_xorHex( $decryptedPreMorphPassQiPassword,$shift ));
    }else if($subVersion == "02"){
        $decryptedPreMorphPassQiPassword = $elements[1];
        $restoredPassQiPassword = _pqshiftTokens2( $decryptedPreMorphPassQiPassword,$shift );
        $auditHash = $elements[3];
    }
    
    error_log("smushing: " . $decryptedPreMorphPassQiPassword . " and " . $shift );
    error_log("restored: " . $restoredPassQiPassword);
    
    $_pq_stringToHash = $username . $decryptedPassQiId . ":" . $decryptedPreMorphPassQiPassword . ":" . $subVersion . $session . $time . $touchQiSignature;
    
    error_log("pq_stringToHash" . $_pq_stringToHash);
    $hashed = hash("sha512",$_pq_stringToHash,false);
    error_log("hashed: " . $hashed . " compares to ". $integrityHash);

    if($hashed == $integrityHash)
    {
        // echo "hash ok" . $break;
        error_log("hash ok");
        error_log($decryptedPassQiId);
        if($_NONCE_ERROR){
        error_log("failing with nonce error");
        
            return array(false,$decryptedPassQiId,$restoredPassQiPassword,$decryptedPreMorphPassQiPassword,"0","0","0","0","PQ2","0",0);
        }else{
        return array(true,$decryptedPassQiId,$restoredPassQiPassword,$decryptedPreMorphPassQiPassword,$shift,$didTouchAuthId,$mfaSsid,$session,$mfaAppBrand,$auditHash,$canPerformTouchAuth);
        }

    }
    else
    {
        // echo "hash failed" . $break;
        error_log("hash failed");
        error_log($decryptedPassQiId);
        $auditHash = "";
        //update_option('pq_error', "passQi is required to login");
        return array(false,$decryptedPassQiId,$restoredPassQiPassword,$decryptedPreMorphPassQiPassword,"0","0","0","0","PQ2","0");

    }
		
	
}

/**
 * Legacy XOR function for passQi 1
 */
function _pq_xorHex($a,$b){

    if((strlen($a) == 32) && (strlen($b) == 32))
    {
        $a1 = substr($a,0,32);
        $b1 = substr($b,0,32);
        $c = bin2hex(pack('H*',$a1) ^ pack('H*',$b1));

        return $c;
    }
    else
    {
        return "";
    }

}

/**
 * Token-shift operation
 */
function _pqshiftTokens2($a,$b, $to = FALSE){
    
    $charMap = getCharMap();

    $as = str_split($a);$bs = str_split($b);
    $len = count($as); $clen = strlen($charMap);
    $final = "";
    for ($i=0;$i<$len;$i++){
        if($to){
            $nPos = strpos($charMap, $as[$i]) + strpos($charMap, $bs[$i]);
        }else{
            $nPos = strpos($charMap, $as[$i]) - strpos($charMap, $bs[$i]);
        }
        if($nPos > $clen) $nPos = $nPos - $clen;
        if($nPos < 0) $nPos = $clen + $nPos;
        $final .= substr($charMap, $nPos, 1);
    }
    return $final;
}

/**
 * @return character map for token shift function
 */
function getCharMap() {
    
	return "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~?[]@!()*,=";
}



/**
 * Decrypt private key
 */
function _pq_roundTripWithPublicKey($cipherText,$pubKeyCert){
    
    error_log("pq.pq.pq.RTWPK");

    global $break;
    $keyArray = openssl_x509_parse($pubKeyCert);
    error_log("name: " . $keyArray['name']);
    if(preg_match("/passQi, Inc./",$keyArray['name']))
    {
        error_log("key present and valid");
        $keyValid = true;
        
        
    }
    else
    {
        error_log("key not present");
        $keyValid = false;
        return "f";

    }
    error_log("timestamp of cert is " . $keyArray['validTo_time_t'] . "compare to " . strtotime('today'));
                $diff = $keyArray['validTo_time_t'] - time();
                update_option("pq_expire", date("z", $diff));
        error_log(get_option("pq_expire"));
    if ($keyArray['validTo_time_t'] < time() ) { 
        error_log("timestamp fail, older than 1 year");
        return "y";
        }else{
        error_log("timestamp success");
        };

    $publicKey = openssl_get_publickey($pubKeyCert);
        if($publicKey == false){
            return "f";
        }
    $binaryCipher = base64_decode($cipherText);

    $playback = base64_encode($binaryCipher);

    $success = openssl_public_decrypt($binaryCipher,$decryptedText,$publicKey);
        error_log("openssl_success:" . $success);
        error_log("decrypted: " . $decryptedText);

    return $decryptedText;


}

/**
 * User-defined extra profile values
 */
function pq_login_save_custom_user_profile_fields( $user ) {
    $_P_USER = $user;
    if ( !current_user_can( 'edit_user', $user ) )
        exit();

    $pq_require =       sanitize_text_field($_POST['pq_require']);
    $pq_require_touch = sanitize_text_field($_POST['pq_require_touchid']);
    
    if($pq_require != 'req') $pq_require = 0;
    update_user_meta( $user, 'pq_require', $pq_require );
    
    
    if(zeroonetwothree($pq_require_touch)){
        update_user_meta( $user, 'pq_require_touchid', $pq_require_touch );
    }

}

// secure callback functions

function pq_secure_cpt() {
error_log("in pq secure cpt");

    $cpt_args = array(
        'exclude_from_search' => 'true',
        'publicly_queryable' => 'true',
        'show_ui' => 'false',
        'show_in_nav_menus' => 'false',
        'show_in_menu' => 'false',
        'query_var' => 'pq_callback'

    );
    register_post_type( 'pq_callback', $cpt_args);
    error_log('did register custom post type');


}




function pq_callback_reply() {

    global $wp_query;

    $isPqCallback = $wp_query->get( 'pq_callback' );


    if ( ! ($isPqCallback == "1" ) )
    {
    	error_log('skipping this query');
        return;                 // passes along any non pq_callback requests
    }

error_log('this is a pq_callback request');

    $salt = get_option('pq_random_s');
    $hash = get_option('pq_random_hash');

    update_option('pq_random_s',    str_repeat('0', 64));
    update_option('pq_random_hash', str_repeat('0', 64));

    if(( get_option('pq_random_timestamp') + (2 * 60) ) < time()){
    $salt = str_repeat('0', 64);
    $hash = str_repeat('0', 64);
    }


	
    echo $salt . "::" . $hash;

    die();		// this request is done.
 }

function pq_error_log ($msg)
{
	if(PQ_DEBUG)
	{
		error_log($msg);
	}

}
?>
