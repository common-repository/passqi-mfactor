<?php

if( !defined( 'WP_UNINSTALL_PLUGIN' ) )
    exit();

//delete options
delete_option( 'pq_login_options' );
delete_option( 'require_pq_for_all' );
delete_option( 'pq_public_cert' );
delete_option("pq_uninstallkey");
delete_option("pq_expire");
delete_option("pq_stats_totalUses");
delete_option("pq_stats_totalUsers");
delete_option("pq_stats_usersPctg");
delete_option("pq_stats_usersRequire");
delete_option("pq_stats_usersLastUsed");
delete_option("pq_defaults_administrator");
delete_option("pq_defaults_editor");
delete_option("pq_defaults_author");
delete_option("pq_defaults_contributor");
delete_option("pq_defaults_subscriber");
delete_option("pq_defaults_touch_administrator");
delete_option("pq_defaults_touch_editor");
delete_option("pq_defaults_touch_author");
delete_option("pq_defaults_touch_contributor");
delete_option("pq_defaults_touch_subscriber");
delete_option("pq_enable_login_message");
delete_option("pq_error");
delete_option("pq_login_settings");
delete_option("pq_mfactor_version");
delete_option("pq_random_hash");
delete_option("pq_random_s");
delete_option("pq_random_timestamp");


//Delete passQi user meta
$_blogusers = get_users();
foreach ( $_blogusers as $_user ) {
    delete_user_meta($_user->ID, 'pqid');
    delete_user_meta($_user->ID, 'pqToken');
    delete_user_meta($_user->ID, 'pqSalt');
    delete_user_meta($_user->ID, 'pqTokenHashed');
    delete_user_meta($_user->ID, 'pq_require');
    delete_user_meta($_user->ID, 'pq_may_require');
    delete_user_meta($_user->ID, 'pq_require_admin');
}




?>