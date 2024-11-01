<?php
	/*
	Plugin Name: WooCommerce Digital Content Delivery (incl. DRM) - FlickRocket
	Plugin URI: https://www.flickrocket.com/
	Text Domain: woocommerce-digital-content-delivery-with-drm-flickrocket
	Description: Enable sales and rentals of (optionally DRM protected) digital content such as DVDs, video (HD+SD), audio books, ebooks  (epub and PDF) and packaged content such as HTML, Flash, images, etc. Includes CDN, customizable player/reader, tracking and much more. Supports PC, Mac, iOS, Android, Kindle and SmartTVs.
	Version: 4.74
	Author: Flickrocket
	Author URI: https://www.flickrocket.com/
	WC requires at least: 2.6.0
    WC tested up to: 8.9.1
	License: ***********
	*/
	
	global $wpdb, $FlickPluginCurrentVersion;
	$FlickPluginCurrentVersion 	= "4.74";
	
	define('ALLOW_UNFILTERED_UPLOADS', true);
	
	define( 'FW_PATH',	dirname(__FILE__) );
	define( 'FW_URL', 	plugins_url()."/".basename(dirname(__FILE__)) );
	define( 'FILE_NAME' , __FILE__ );
	define( 'EMAIL_PATH', plugin_dir_path( __FILE__ ) );

	include_once FW_PATH."/config.php";

	include_once FW_PATH."/includes/class.FlickrocketMultipass.php";
	include_once FW_PATH."/includes/class.Flickrocket.php";
	include_once FW_PATH."/includes/class.FlickrocketWoocommerce.php";
	include_once FW_PATH."/includes/class.FlickrocketWoocommerceSync.php";

	global $licensecache; 
	global $pricecache;
	$licensecache = array();
	$pricecache = array();

	register_uninstall_hook( __FILE__, 'flickrocket_woocommerce_uninstall' );

	add_filter( 'cron_schedules', 'process_sync_jobs' ); // Custom schedule

	add_action( 'init', 'flick_initialize' );
	add_action( 'plugins_loaded', 'myplugin_load_textdomain' );
	add_action( 'admin_init', 'fr_plugin_admin_init' );
	add_action( 'flickrocket_sync_hook','fr_process_queue' ); 	// Cron hook for processing job queue
	add_action( 'woocommerce_get_settings_pages', 'flick_load_setting_tab', 200, 1 );
	add_action( 'wp_login', 'login', 10, 2);
	add_action( 'admin_notices', 'fr_error_notice' );

	add_action( 'admin_post_nopriv_fr_oauth_callback', 'incoming_fr_oauth_callback' );
	add_action( 'admin_post_fr_oauth_callback', 'incoming_fr_oauth_callback' );

	add_action( 'admin_post_nopriv_flickrocket_product_redirect', 'incoming_flickrocket_product_redirect' );
	add_action( 'admin_post_flickrocket_product_redirect', 'incoming_flickrocket_product_redirect' );

	// add_action( 'admin_menu', 'register_role_group_management' );
	add_action( 'admin_menu', 'register_digital_content' );

	add_action( 'admin_menu', 'register_fr_main_menu' );

	function flick_initialize(){
		global $wp; 

		$wp->add_query_var('fr_unlock'); 

		if(class_exists('FlickrocketWoocommerce')) {
			FlickrocketWoocommerce::init();	
		} 
		if(class_exists('FlickrocketWoocommerceSync')) {
			FlickrocketWoocommerceSync::init();
		} 
		// if(class_exists('FlickrocketAccessGroups')) {
		// 	FlickrocketAccessGroups::init();	
		// } 

		wp_enqueue_style( 'myPluginStylesheet', FW_URL . '/css/flickrocket.css' );
		wp_enqueue_script( 'fr_public', FW_URL . '/js/fr_public.js', array('jquery'), '1.4');
		$scriptData = array(
			'FR_OAUTH_URL' => FR_OAUTH_URL.'/oauth2/auth',
			'FR_EXTADMIN_URL' => FR_EXTADMIN_URL,
			'FR_UPLOADER_URL' => FR_UPLOADER_URL,
			'FR_CLIENT_ID' => FR_CLIENT_ID,

			'FR_ACCESS_TOKEN' => get_option('fr_access_token', ''),

			'BLOG_EMAIL' => get_option( 'admin_email' ),
			'ajaxurl' => admin_url( 'admin-ajax.php' )
		);
		wp_localize_script('fr_public', 'fr_options', $scriptData);

		add_rewrite_endpoint( 'mycontent', EP_ROOT | EP_PAGES );
		
		add_thickbox();
	}

	function myplugin_load_textdomain() {

		// include( dirname(__FILE__ ) . '/languages/en_US.php' );

		$domain = basename(dirname(__FILE__));
		$locale = apply_filters('plugin_locale', get_locale(), $domain );
		$result = load_plugin_textdomain($domain, FALSE, basename(dirname( __FILE__ )).'/languages');
	}

	/**
	* Register admin script.
	*/
	
	function fr_plugin_admin_init() {
		// Register our script.
		wp_enqueue_script( 'fr_admin', FW_URL . '/js/fr_custom.js', array('jquery'),'1.6');

		wp_enqueue_script('jquery-ui-progressbar');
		wp_enqueue_style('e2b-admin-ui-css','https://ajax.googleapis.com/ajax/libs/jqueryui/1.9.0/themes/base/jquery-ui.css',false,"1.9.0",false);
	}
	
	// Check if WooCommerce is active
	if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		
		// Hooks call when install the plugin, it will create table from db	
		register_activation_hook( __FILE__, 'flickrocket_woocommerce_activate' );

		// Hook calls when deactivated
		register_deactivation_hook( __FILE__, 'fr_job_hook_deactivate' );
	}
			
	function flickrocket_woocommerce_activate(){

		// Make sure CURL exists
		if (!function_exists('curl_version'))
		{
			wp_die('Problem using cURL. Make sure PHP support for cURL is installed');
			return false;
		}
		
		update_option( 'flickrocket_theme_id', 0, false );
		update_option( 'fr_refresh_token', '', false );
		update_option( 'fr_access_token', '', false );
		update_option( 'fr_access_token_expiry', '', false );	
		update_option( 'fr_sync_products', 'yes', false );
		update_option( 'fr_activate_products', 'no', false );
		update_option( 'fr_show_previews', 'yes',false );
		update_option( 'fr_extend_emails', 'yes',false );
		update_option( 'fr_domain', '', false );
		update_option( 'fr_error', '', false );
		update_option( 'fr_multipass_secret', '', false );
		update_option( 'fr_error_email', '', false );
		update_option( 'fr_last_version', '', false );
		// update_option( 'fr_roles_groups_assignment', '', false ); 
		update_option( 'fr_mautic_id', 0, false);
		update_option( 'fr_oauth_err_count', 0, false);
		update_option( 'fr_use_legacy', 'no', false );
		update_option( 'fr_use_sso', 'no', false );
		update_option( 'fr_logging', "0", false );
		update_option( 'fr_credit_orders_directly', "yes", false );

		if ( ! wp_next_scheduled( 'flickrocket_sync_hook' ) ) {
			wp_schedule_event( time(), 'Flickrocket - Every 5 minutes', 'flickrocket_sync_hook' ); }

		//Mautic
		try
		{
			$store_raw_country = get_option( 'woocommerce_default_country' );
			$split_country = explode( ":", $store_raw_country );
			$store_country = $split_country[0];
			$store_state   = $split_country[1];

			$country = WC()->countries->countries[$store_country];
			$pattern = '@\(.*?\)@';
			$country_no_backets = trim(preg_replace($pattern,'',$country));

			$states = WC()->countries->get_states( $store_country );
			$state  = ! empty( $states[ $store_state ] ) ? $states[ $store_state ] : '';

			$contact = array(
				'email' => get_option( 'admin_email' ),
				'website' => get_bloginfo( 'wpurl' ),
				'address1' => get_option( 'woocommerce_store_address' ),
				'address2' => get_option( 'woocommerce_store_address_2' ),
				'city' => get_option( 'woocommerce_store_city' ),
				'zipcode' => get_option( 'woocommerce_store_postcode' ),
				'country' =>  $country_no_backets,
				'tags' => array( 'type:lead', 'origin:plugin_install', 'plugin:woocommerce' )
			);

			if ($country_no_backets == "Unites States")
				$contact['state'] = $state;

			$result = Flickrocket::mautic('/api/mautic/contacts/new', $contact);

			if (array_key_exists( 'contact', $result))
			{
				$fr_mautic_id = $result['contact']['id'];
				update_option('fr_mautic_id', $fr_mautic_id);
			}
		}
		catch (Exception $e)
		{
			Flickrocket::log("Error", "Activate | Mautic error | Message: ".$e->getMessage()." | Trace: ".$e->getTraceAsString());
		}
	}

	function fr_job_hook_deactivate() {
			$timestamp = wp_next_scheduled( 'flickrocket_sync_hook' );
			wp_unschedule_event( $timestamp, 'flickrocket_sync_hook' ); 
	}

	function process_sync_jobs( $schedules ) {
		$schedules['Flickrocket - Every 5 minutes'] = array(
			'interval' => 300, 
			'display'  => esc_html__( 'Flickrocket - Every 5 minutes' ),
		);
		return $schedules;
	}

	function fr_process_queue()
	{
		global $licensecache, $pricecache;

		// Check for items to be processed
		set_time_limit(300); //Set execution time limit to 5 minutes

		$starttime = time();
		$files = glob(FW_PATH.'/jobs/queued/*.{sync,delete}', GLOB_BRACE);
		foreach($files as $file) {
			$json = json_decode(file_get_contents ( $file, true ), true);
			rename($file, FW_PATH.'/jobs/done/'.basename($file)); //Make sure it is not processed twice

			$ext = pathinfo($file, PATHINFO_EXTENSION);
			if ($ext == 'sync')
			{
				$result = FlickrocketWoocommerceSync::process_product_sync($json, $licensecache, $pricecache);
			}
			else if ($ext == 'delete')
			{
				$result = FlickrocketWoocommerceSync::process_product_delete($json);
			}

			if (time() - $starttime > 240) break; // break after 4 minutes because next scheduled sync will happen shortly (in less than one minute) // Required??
		}
	}

	function flick_load_setting_tab($settings){
		$settings[] = include_once( FW_PATH."/includes/class.FlickrocketSettings.php" );
		return $settings;
	}
	
	function flickrocket_woocommerce_uninstall()
	{	
		include_once FW_PATH."/includes/class.Flickrocket.php";

		//Mauric - indicate removal
		try
		{
			$contact = array(
				'email' => get_option( 'admin_email' ),
				'tags' => array( 'plugin:woocommerce_removed' )
			);
			Flickrocket::mautic('/api/mautic/contacts/new', $contact);
		}
		catch (Exception $e)
		{ }

		// Delete webhooks
		try
		{
			$webhooks = Flickrocket::get_webhooks();
			deleteHooks($webhooks);
		}
		catch (Exception $e)
		{ }

		// Delete options
		delete_option( 'flickrocket_user_email' );
		delete_option( 'flickrocket_user_password' );
		delete_option( 'flickrocket_theme_id' );
		delete_option( 'flickrocket_sync_secret' );
		delete_option( 'fr_refresh_token');
		delete_option( 'fr_access_token');
		delete_option( 'fr_access_token_expiry');
		delete_option( 'fr_sync_products');
		delete_option( 'fr_activate_products');
		delete_option( 'fr_show_preview');
		delete_option( 'fr_show_previews');
		delete_option( 'fr_extend_emails');
		delete_option( 'fr_domain');
		delete_option( 'fr_error');
		delete_option( 'fr_multipass_secret');
		delete_option( 'fr_error_email');
		delete_option( 'fr_last_version');
		// delete_option( 'fr_roles_groups_assignment');
		delete_option( 'fr_mautic_id');
		delete_option( 'fr_use_legacy');
		delete_option( 'fr_use_sso');
		delete_option( 'fr_logging');
		delete_option( 'fr_credit_orders_directly');
	}

	//Delete webhooks
	function deleteHooks($webhooks)
	{
		if (!array_key_exists('error', $webhooks) && count($webhooks) > 0)
		{
			foreach ($webhooks as $webhook)
			{
				$hookid = $webhook['id'];
				$result = Flickrocket::delete_webhook($hookid);
			}
		}
	}

	function login( $user_login, $user ) 
	{
		// Check if current version has already been logged
		global $woocommerce, $wp_version, $FlickPluginCurrentVersion;

		if ( ! function_exists( 'get_plugins' ) ) require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		if ($FlickPluginCurrentVersion != get_option( 'fr_last_version', '' ))
		{
			update_option( 'fr_last_version', $FlickPluginCurrentVersion );	
		}

		FlickrocketWoocommerce::flick_meta_cleanup();
	}


	function fr_error_notice() 
	{
		$page = '';
		$tab = '';

		if (array_key_exists ( 'page', $_REQUEST ) && array_key_exists ( 'tab', $_REQUEST ))
		{
			$page = $_REQUEST['page'];
			$tab = $_REQUEST['tab'];
		}

		if ( get_option('fr_refresh_token','') == '' && $page != "wc-settings" && $tab != "flickrocket" )
		{
			// Upon initial install when no authentication was done
			echo '
<div class="error notice">
	<p>
		<b>'.__('Important:','woocommerce-digital-content-delivery-with-drm-flickrocket').' </b>'.
			sprintf(__('To use "WooCommerce Digital Content Delivery (incl. DRM) - FlickRocket" you need to log in under %s','woocommerce-digital-content-delivery-with-drm-flickrocket'), 
				'<a href="'.get_site_url().'/wp-admin/admin.php?page=wc-settings&tab=flickrocket">'.__('settings','woocommerce-digital-content-delivery-with-drm-flickrocket').'</a>').
	'</p>
</div>
';
		}
		else if (get_option('fr_error','') != '' && $page != "wc-settings" && $tab != "flickrocket" )
		{
			// Upon previous error
			echo '
<div class="error notice">
	<p>
		<b>'.__('Important:','woocommerce-digital-content-delivery-with-drm-flickrocket').' </b> '.get_option('fr_error','').
	'</p>
</div>
';
			update_option('fr_error',''); // Clear error so it is only displayed once
		}
	}

	function incoming_fr_oauth_callback()
	{
		error_log('incoming_fr_oauth_callback()');
		
		$code = $_GET['code'];
		$companyid = $_GET['companyid'];
		$result = Flickrocket::fr_oauth_callback($code, $companyid);

		//Register Hooks
		$result = Flickrocket::set_webhook('product/create', 'http'.(empty($_SERVER['HTTPS'])?'':'s').'://'.$_SERVER["HTTP_HOST"].$_SERVER["PHP_SELF"].'?action=fr_auto_product_sync', true);
		$result = Flickrocket::set_webhook('product/update', 'http'.(empty($_SERVER['HTTPS'])?'':'s').'://'.$_SERVER["HTTP_HOST"].$_SERVER["PHP_SELF"].'?action=fr_auto_product_sync', true);
		$result = Flickrocket::set_webhook('product/delete', 'http'.(empty($_SERVER['HTTPS'])?'':'s').'://'.$_SERVER["HTTP_HOST"].$_SERVER["PHP_SELF"].'?action=fr_auto_product_sync', true);
		
		//Exit with closing window and send message
		$return = '<html>
		<body>
			<script>
				window.opener.postMessage({ "message": "fr_complete" });
				window.close();
			</script>
		</body>
		</html>';
		echo $return;
		status_header(200);
	}

	function incoming_flickrocket_product_redirect() 
	{
		Flickrocket::log("Info", "incoming_flickrocket_product_redirect()");
		
		$fr_product = strtoupper(htmlspecialchars($_GET["product"]));

		Flickrocket::log("Info", "incoming_flickrocket_product_redirect for product: ".$fr_product);

		$args = array(
		'post_type' => 'product',
		'meta_query' => array(
			array(
				'key' => '_flickrocket_project_key_id',
				'value' => $fr_product,
				'compare' => '='
			)));
		
		$query = new WP_Query($args);

		if ($query->post_count > 0) 
		{
			$post_id = $query->posts[0]->ID;
			if ( $post_id != 0 )
			{
				$url = get_permalink( $post_id );
				wp_redirect( $url );
				exit;
			}
		}  
	}

	// function register_role_group_management() {
	// 	add_submenu_page(
	// 		'users.php',
	// 		'Content Access Groups',
	// 		'Content Access Groups',
	// 		'manage_options',
	// 		'content_access_groups',
	// 		'content_access_groups_callback'
	// 	);
	// }

	// function content_access_groups_callback() {
    //     echo '
    // <div id="fr_role_group_management"></div>';
	// }

	function register_digital_content() {
		add_submenu_page(
			'edit.php?post_type=product',
			'Digital Content',
			'Digital Content',
			'manage_woocommerce',
			'Digital Content',
			'digital_content_callback'
		);
	}

	function digital_content_callback() {
		
		//Get product count from server
		$api_error = true;
		$result = Flickrocket::get_product_count();
		if (is_array($result) && !array_key_exists('error', $result )) 
			$api_error = false;
		
				
		if (get_option('fr_access_token','') == '' || $api_error == true )
		{
			echo '<h1>'.__('Manage Digital Content','woocommerce-digital-content-delivery-with-drm-flickrocket').'</h1>';
			echo '<b>'.__('Warning:','woocommerce-digital-content-delivery-with-drm-flickrocket').'</b>'.sprintf(__('You currently don\'t have valid credentials for "WooCommerce Digital Content Delivery (incl. DRM) - FlickRocket". First you 
need to log in under %s','woocommerce-digital-content-delivery-with-drm-flickrocket'), '<a href="/wp-admin/admin.php?page=wc-settings&tab=flickrocket">'.__('settings','woocommerce-digital-content-delivery-with-drm-flickrocket').'</a>.');
		}
		else
		{
			// Show Manage page

			$projectWizardURL = "https://admin.flickrocket.com/cms/product/create";

			echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.1/jquery.js" type="text/javascript" charset="utf-8"></script>';
			echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js" type="text/javascript" charset="utf-8"></script>';
			echo '<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">';

			echo '<h1>'.__('Manage Digital Content','woocommerce-digital-content-delivery-with-drm-flickrocket').'</h1>';
			echo '<p>'.sprintf(__('Each digital content product needs to be uploaded to Flickrocket for preparation (transcoding, protection, content delivery). Depending on 
your %s these products are automatically synced to WooCommerce and optionally published. 
Alternatively you can sync them using the section below or create the products manually as part of the Woocommerce product setup.','woocommerce-digital-content-delivery-with-drm-flickrocket'),
'<a href="/wp-admin/admin.php?page=wc-settings&tab=flickrocket">'.__('settings','woocommerce-digital-content-delivery-with-drm-flickrocket').'</a>').
				'</p>';

			echo '<hr>';
			echo '<h2>'.__('Manage product(s) on Flickrocket','woocommerce-digital-content-delivery-with-drm-flickrocket').'</h2>';
			echo '<p>'.__('Use this section to create new products or manage existing. Content upload/processing takes some time. You will be notified by email when finished.','woocommerce-digital-content-delivery-with-drm-flickrocket').'</p>';
			echo '<div><input type="button" class="button button-primary" value="'.__('Manage','woocommerce-digital-content-delivery-with-drm-flickrocket').'" onclick=\'window.open("'.$projectWizardURL.'", "FlickRocket", "resizable,scrollbars,status");\'></div>';
			echo '<p></p>';
			echo '<hr>';
			echo '<h2>'.__('Sync existing products from Flickrocket to Woocommerce','woocommerce-digital-content-delivery-with-drm-flickrocket').'</h2>';
			echo '
	<div class="row">
		<div class="form-group">
			<p>'
				.__('Use this function to import products from your Flickrocket account into WooCommerce. 
During the import you must not navigate away from this page.').
			'</p>
			<p>'.
				sprintf(__('Count of products available in your Flickrocket account: %s','woocommerce-digital-content-delivery-with-drm-flickrocket'), $result["count"])
			.'</p>
			<button type="button" id="fr_sync_button" class="button button-primary">'.__('Sync products now','woocommerce-digital-content-delivery-with-drm-flickrocket').'</button>
		</div>
	</div>
	<div class="row">
		<div id="fr_sync_progress" style="position: relative; display: none;">
			<div id="fr_sync_progressbar">
				<div id="fr_sync_progressbar_label" style="position: absolute; left: 50%; top: 4px;"></div>
			</div>
		</div>
	</div>
	<br />
	<div id="fr_getting_sync_products">'
		.__('Getting product information','woocommerce-digital-content-delivery-with-drm-flickrocket'). 
	'</div>
	<div id="fr_sync_product_display" style="display: none;">
		<div class="row">
			<a href="#" id="fr_sync_toggle">'.__('Toggle selection','woocommerce-digital-content-delivery-with-drm-flickrocket').'</a>
		</div>
		<div class="row">
			<table class="wp-list-table widefat striped posts" id="fr_sync_products_table">
				<thead>
					<tr>
						<td>'.__('Sync','woocommerce-digital-content-delivery-with-drm-flickrocket').'</td>
						<td>'.__('Product ID','woocommerce-digital-content-delivery-with-drm-flickrocket').'</td> 
						<td>'.__('Name','woocommerce-digital-content-delivery-with-drm-flickrocket').'</td>
						<td>'.__('Type','woocommerce-digital-content-delivery-with-drm-flickrocket').'</td>
						<td>'.__('Exists in Woo...','woocommerce-digital-content-delivery-with-drm-flickrocket').'</td>
					</tr>
				</thead>
				<tbody id="fr_sync_products_table_body"></tbody>
			</table>
		</div>
	</div>
	';
		}
	}

	function marketplace_callback() {
		echo '<br /><iframe src="'.FR_MARKETPLACE_URL.'" id="frIframe" width="100%" height="800px"></iframe>';
	}

	function register_fr_main_menu() {
		add_menu_page(
			'Flickrocket',
			'Flickrocket',
			'manage_woocommerce',
			'fr_main_menu',
			'digital_content_callback',
			plugins_url( 'woocommerce-digital-content-delivery-with-drm-flickrocket/images/flickrocket_logo.svg' )
		);
		add_submenu_page(
			'fr_main_menu',
			__('Products','woocommerce-digital-content-delivery-with-drm-flickrocket'),
			__('Products','woocommerce-digital-content-delivery-with-drm-flickrocket'),
			'manage_woocommerce',
			'fr_main_menu',
			'digital_content_callback'
		);

		add_submenu_page(
			'fr_main_menu',
			__('Analytics','woocommerce-digital-content-delivery-with-drm-flickrocket'),
			__('Analytics','woocommerce-digital-content-delivery-with-drm-flickrocket'),
			'manage_woocommerce',
			'fr_analytics',
			'analytics_callback'
		);

		// add_submenu_page(
		// 	'fr_main_menu',
		// 	'Groups',
		// 	'Groups',
		// 	'manage_options',
		// 	'content_access_groups',
		// 	'content_access_groups_callback'
		// );

		add_submenu_page(
			'fr_main_menu',
			__('Marketplace','woocommerce-digital-content-delivery-with-drm-flickrocket'),
			__('Marketplace','woocommerce-digital-content-delivery-with-drm-flickrocket'),
			'manage_options',
			'fr_marketplace',
			'marketplace_callback'
		);

		add_submenu_page(
			'fr_main_menu',
			'Settings',
			'Settings',
			'manage_options',
			'fr_settings_link',
			'fr_settings_link_callback'
		);
	}

	function fr_settings_link_callback() {
		echo 	'<p>
					<b>Settings for this module are part of the regular WooCommerce settings</b>
				</p>
				<a href="../wp-admin/admin.php?page=wc-settings&tab=flickrocket">Open Settings</a>
				';
	}

	function analytics_callback() {

		if (empty($_POST["start_date"]))
		{
			// Use standard dates
			$end_date = date('Y-m-d');
			$start_date = date('Y-m-d', strtotime("-1 month")); //A month before today;
		}
		else
		{
			// Use given dates
			$end_date = $_POST["end_date"];
			$start_date = $_POST["start_date"];
		}

		$licenses = Flickrocket::get_license_history($start_date, $end_date);
		if ($licenses == null)
		{
			// Some error happened, probably no scope permissions yet or otherwise not logged in
			echo 	'<p>
						<b>Error:</b> Please login again to make sure you have the latest permissions.
					</p>
					<div class="button-primary" id="oauth_login">'.__('Login','woocommerce-digital-content-delivery-with-drm-flickrocket').'</div>
					';
			return;
		}

		render_analytics_page( $start_date, $end_date, $licenses);
	}

	function render_analytics_page( $start_date, $end_date, $licenses) {
		if (empty($licenses))
		{
			// Licenses were not fetched before -> do this now
			$licenses = Flickrocket::get_license_history($start_date, $end_date);
		}
		$player_devices = Flickrocket::get_player_devices($start_date, $end_date);
		$content_usage = Flickrocket::get_content_use($start_date, $end_date);
		$license_types = Flickrocket::get_license_types($start_date, $end_date);

		echo "
		<script src='https://cdnjs.cloudflare.com/ajax/libs/echarts/5.1.1/echarts.min.js'></script>
		<script>
			var fr_lic_stats = ".json_encode($licenses).";
			var fr_players_stats = ".json_encode($player_devices).";
			var fr_content_stats = ".json_encode($content_usage).";
			var fr_license_stats = ".json_encode($license_types).";
		</script>". 

		'<div id="fr_analytics" class="container-fluid">
		<form method="POST">
			<div class="row">
				<label for="start_date">Start date:</label>
				<input id="start_date" type="date" name="start_date" value="'.$start_date.'" min="2018-01-01" />
				&nbsp;&nbsp;
				<label for="end_date">End date:</label>
				<input id="end_date" type="date" name="end_date" value="'.$end_date.'" min="2018-01-01" />
				<input type="submit" value="Submit" class="button-primary">
			</div>
		</form>
		<hr />
        <div class="row">
            <div class="col-sm-6">
				<h2>Issued Licenses</h2>
                <div id="IssuedLicenses_Chart" style="height:350px;">
                </div>
                <div id="IssuedLicenses_Table">
                    <table id="IssuedLicenses_Table_data" class="widefat fixed"></table>
                </div>
            </div>
            <div class="col-sm-6">
				<h2>Player Devices</h2>
                <div id="FluxPlayerDevices_Chart" style="height:350px;">
                </div>
                <div id="FluxPlayerDevices_Table">
                    <table id="FluxPlayerDevices_Table_data" class="widefat fixed"></table>
                </div>
            </div>
        </div>
		<p></p>
        <div class="row">
            <div class="col-sm-6">
				<h2>Content Use</h2>
                <div id="FluxPlayerContentUsage_Chart" style="height:350px;">
                </div>
                <div id="FluxPlayerContentUsage_Table">
                    <table id="FluxPlayerContentUsage_Table_data" class="widefat fixed "></table>
                </div>
            </div>
			<div class="col-sm-6">
				<h3>License Types</h3>
				<div id="LicensePurchases_Chart" style="height:350px;">
				</div>
				<div id="LicensePurchases_Table">
					<table id="LicensePurchases_Table_data" class="table table-striped"></table>
				</div>
			</div>
        </div>
    </div>';
	}
	
?>