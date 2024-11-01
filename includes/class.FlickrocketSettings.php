<?php

if (!defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!class_exists('FlickrocketSettings')):

/**
 * FlickrocketSettings
 */
class FlickrocketSettings extends WC_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'flickrocket';
		$this->label = __( 'FlickRocket','woocommerce-digital-content-delivery-with-drm-flickrocket' );

		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
		add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
		add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
	}

	/**
	 * Get settings array
	 *
	 * @return array
	 */
	public function get_settings() 
	{
		global $fr_account_text;

		Flickrocket::log('Info','Start get_settings');
		$account_text = '';
		
		try 
		{
			$shopemail = get_bloginfo('admin_email');
			if (get_option('fr_access_token','') == '' || get_option('fr_access_token','') == '' || get_option('fr_error','') != '') 
			{
				// No OAuth yet - Show only Oauth options
				return apply_filters( 'woocommerce_' . $this->id . '_settings', array(
					array( 'title' => '', 'type' => 'title', 'desc' => '<div id="fr_message"></div>', 'id' => 'fr_message'),
					array( 'title' => '', 'type' => 'title', 'desc' => __( '<b>Here\'s what Flickrocket can do for you</b>'
						,'woocommerce-digital-content-delivery-with-drm-flickrocket'), 'id' => 'fr_account_page_options_1' ),
					array( 'title' => '', 'type' => 'title', 'desc' => __(
						'<ul>
							<li>&bull; Securely encrypt your valuable content (video, audio, PDF, ePub, HTML, etc.) to prevent your customers from illegal sharing</li>
							<li>&bull; Transcode your content for best results and compatibility with virtually all target platforms</li>
							<li>&bull; Host your content for highspeed delivery with our dedicated Content Delivery Network</li>
							<li>&bull; Customers can use the browser or native apps for iOS, Android, Windows, Mac, Kindle Fire, Smart TVs to access purchased content</li>
						</ul>'
						,'woocommerce-digital-content-delivery-with-drm-flickrocket'),	'id' => 'fr_account_page_options_2' ),
					array( 'title' => '', 'type' => 'title', 'desc' => __( '<b>If you don\'t have a FlickRocket account yet, sign up for your account.</b><br />').
						'<button class="button-secondary" id="fr_sign_up">'.__('Sign up to Flickrocket - it\'s FREE').'</button>', 'id' => 'fr_account_page_options_5' ),
					array( 'title' => '', 'type' => 'title', 'desc' => __('<b>If you already have an account with Flickrocket, log in now.</b><br />','woocommerce-digital-content-delivery-with-drm-flickrocket').
						'<button class="button-primary" id="oauth_login">'.__('Login - I have already signed up','woocommerce-digital-content-delivery-with-drm-flickrocket').'</button>',
						'id' => 'fr_account_page_options_6' )
					)); 
			}
			else 
			{
				// Auth available - Show all options
				$only_sso_login = Flickrocket::get_sso_info();
				if ($only_sso_login)
				{
					$sso_help = __( 'The current SSO mode of the backend is "SSO only". Separate user credentials for the player will not be included in the email or shown to the user.',
						'woocommerce-digital-content-delivery-with-drm-flickrocket');
				}
				else
				{
					$sso_help = __( 'The current SSO mode of the backend is "Optional". Separate user credentials for the player are included in the email and shown to the user.', 
						'woocommerce-digital-content-delivery-with-drm-flickrocket');
				}

				return apply_filters( 'woocommerce_' . $this->id . '_settings', array(
					array( 'title' => '', 'type' => 'title', 'desc' => '<div id="fr_message"></div>', 'id' => 'fr_message'),

					array( 'title' => __('Flickrocket Status'), 'type' => 'title', 'desc' => __('<b>Current plan:</b> ','woocommerce-digital-content-delivery-with-drm-flickrocket').$fr_account_text,
						'id' => 'fr_account_page_options_1' ), 

					array( 	'type' => 'sectionend', 'id' => 'end_section_1'),

					array( 	'title' => __('Product Sync Settings','woocommerce-digital-content-delivery-with-drm-flickrocket'), 'type' => 'title', 
							'desc' => __('Sync products from Flickrocket to WooCommerce when you upload.','woocommerce-digital-content-delivery-with-drm-flickrocket'), 'id' => 'fr_account_page_options_4' ),

					array(
						'title'         => __('Ongoing Sync','woocommerce-digital-content-delivery-with-drm-flickrocket'),
						'desc'          => __('Automatically create products in WooCommerce as they become available','woocommerce-digital-content-delivery-with-drm-flickrocket'),
						'id'            => 'fr_sync_products',
						'default'       => 'yes',
						'type'          => 'checkbox',
						'autoload'      => true,
					),
					array(
						'title'         => __('Auto Activate','woocommerce-digital-content-delivery-with-drm-flickrocket'),
						'desc'          => __('Automatically activate created products','woocommerce-digital-content-delivery-with-drm-flickrocket'),
						'id'            => 'fr_activate_products',
						'default'       => 'yes',
						'type'          => 'checkbox',
						'autoload'      => true,
					),
					array( 'type' => 'sectionend', 'id' => 'end_section_2'),

					array( 'title' => __('General Settings','woocommerce-digital-content-delivery-with-drm-flickrocket'), 'type' => 'title', 
						'desc' => __('General settings to manage the digital content in your shop.','woocommerce-digital-content-delivery-with-drm-flickrocket'), 
						'id' => 'fr_account_page_options_5' ),

					array(
						'title'         => __('Show Previews','woocommerce-digital-content-delivery-with-drm-flickrocket'),
						'desc'          => __('Display preview button for content preview (if available)','woocommerce-digital-content-delivery-with-drm-flickrocket'),
						'id'            => 'fr_show_previews',
						'default'       => 'yes',
						'type'          => 'checkbox',
						'autoload'      => true,
					),
					array(
						'title'         => __('Extend order emails','woocommerce-digital-content-delivery-with-drm-flickrocket'),
						'desc'          => __('Adds information on how to access ordered digital content to sales emails','woocommerce-digital-content-delivery-with-drm-flickrocket'),
						'id'            => 'fr_extend_emails',
						'default'       => 'yes',
						'type'          => 'checkbox',
						'autoload'      => true,
					),
					array(
						'title'         => __('Credit orders directly into customer account','woocommerce-digital-content-delivery-with-drm-flickrocket'),
						'desc'          => __('Orders are credited directly in the customer account. Generate codes only for multi-quantity, multi-user orders.','woocommerce-digital-content-delivery-with-drm-flickrocket'),
						'id'            => 'fr_credit_orders_directly',
						'default'       => 'yes',
						'type'          => 'checkbox',
						'autoload'      => true,
					),
					array(
						'title'         => __('Legacy Content Access','woocommerce-digital-content-delivery-with-drm-flickrocket'),
						'desc'          => __('Use legacy theme for content access (if available)','woocommerce-digital-content-delivery-with-drm-flickrocket'),
						'id'            => 'fr_use_legacy',
						'default'       => 'no',
						'type'          => 'checkbox',
						'autoload'      => true,
					),
					array(
						'title'         => __( 'Admin email','woocommerce-digital-content-delivery-with-drm-flickrocket'),
						'desc'          => __( 'Email address for important notifications such as process errors','woocommerce-digital-content-delivery-with-drm-flickrocket'),
						'id'            => 'fr_error_email',
						'default'       => '',
						'type'          => 'text',
						'autoload'      => true,
					),
					// Select
					array(
						'title'     => __( 'Logging', 'woocommerce-digital-content-delivery-with-drm-flickrocket' ),
						'desc'      => __( 'Select how much logging is done. Logging "Errors only" may improve performance.', 'woocommerce-digital-content-delivery-with-drm-flickrocket' ),
						'id'        => 'fr_logging',
						'class'     => 'wc-enhanced-select',
						'default'   => 'Default',
						'type'      => 'select',
						'options'   => array(
							'0'        => __( 'Default', 'woocommerce-digital-content-delivery-with-drm-flickrocket' ),
							'1'        => __( 'Errors only', 'woocommerce-digital-content-delivery-with-drm-flickrocket' ),
						),
						'autoload' => true,
						// 'desc_tip' => true,
					),
					array( 'type' => 'sectionend', 'id' => 'end_section_3'),

					array( 'title' => __('Single-Sign-On (SSO)','woocommerce-digital-content-delivery-with-drm-flickrocket'), 'type' => 'title', 
						'desc' => $sso_help, 
						'id' => 'fr_account_page_options_6' ),
					array(
						'title'         => __('Enable SSO ','woocommerce-digital-content-delivery-with-drm-flickrocket'),
						'desc'          => __('Enable Single-Sign-On (SSO) via WooCommerce login. ','woocommerce-digital-content-delivery-with-drm-flickrocket'),
						'id'            => 'fr_use_sso',
						'default'       => 'no',
						'type'          => 'checkbox',
						'autoload'      => true,
					),
					array( 'type' => 'sectionend', 'id' => 'end_section_4'),

					// array( 'title' => __('Role/Content Access Group Settings','woocommerce-digital-content-delivery-with-drm-flickrocket'), 
					// 	'type' => 'title', 
					// 	'desc' => __('Associate Wordpress roles with Flickrocket Content Access Groups (optional).','woocommerce-digital-content-delivery-with-drm-flickrocket'), 
					// 	'id' => 'fr_account_page_options_7' ),
					// array( 'title' => '', 'type' => 'title', 'desc' => 
					// 	'<div id="fr_roles_and_groups">
					// 		<div id="fr_roles_groups_list"></div>
					// 	</div>', 
					// 	'id' => 'fr_roles_groups'),
					// array( 'type' => 'sectionend', 'id' => 'end_section_5'),

					array( 	'title' => __('Content access section translations','woocommerce-digital-content-delivery-with-drm-flickrocket'), 'type' => 'title', 
					'desc' => __('See <a href="https://www.flickrocket.com/en/plugin-content-access-page-localization" target="_blank">https://www.flickrocket.com/en/plugin-content-access-page-localization</a> on how <br />to add support for a new language to the customer facing content access section','woocommerce-digital-content-delivery-with-drm-flickrocket'), 'id' => 'fr_account_page_options_7' ),

				)); // End pages settings
			}	
		}
		catch (Exception $ex)
		{
			//Display error
			return apply_filters( 'woocommerce_' . $this->id . '_settings', array(
				array( 'title' => __('Error','woocommerce-digital-content-delivery-with-drm-flickrocket'), 'type' => 'title', 'desc' => sprintf(__('Error initializing (%s): One reason might be that php-soap is not installed/active?','woocommerce-digital-content-delivery-with-drm-flickrocket'), $ex->getMessage()), 
				'id' => 'fr_account_page_options' )));
		}
		Flickrocket::log('Info','End get_settings');
	}
	
	public function output() 
	{
		global $current_section, $hide_save_button, $fr_account_text;

		// // Get roles
		// if ( ! isset( $wp_roles ) )	$wp_roles = new WP_Roles();
	
		// $all_roles = $wp_roles->get_names();
		// $all_roles_json = json_encode($all_roles);

		// // Get groups
		// $all_groups = Flickrocket::get_access_groups();
		// $all_groups_json = json_encode($all_groups);

		// // Make roles/group related data available to JS
		// echo '<script>var all_roles_json=\''.$all_roles_json.'\'; var all_groups_json=\''.$all_groups_json.'\'</script>';

		// $group_to_role_setting = get_option('fr_roles_groups_assignment', false);
		// echo "<input type='hidden' id='group_to_role_setting' name='group_to_role_setting' value='".$group_to_role_setting."'>";

		// Get company status
		$fr_account_text = '';
		$plan = '';
		$paid = false;
		$result = Flickrocket::get_company_infos('');
		if (is_array($result) && array_key_exists('company', $result))
		{
			// Get plan
			$account = $result['company']['account_level'];
			if ($account == 3) { $fr_account_text = "GOLD"; }
			else if ($account == 2) { $fr_account_text = "SILVER"; }
			else { $fr_account_text = "BASIC"; }

			// Store company_id
			update_option( 'fr_company_id', $result['company']['id'], false);

			// Store Multipass secret
			update_option( 'fr_multipass_secret', $result['company']['external_access_token'], false );
		}

		$settings = $this->get_settings();

		if (get_option('fr_access_token','') == '')
		{
			$hide_save_button = true; // Show "Save" bitton only if already logged in to Flickrocket
		}

		WC_Admin_Settings::output_fields( $settings );
	}

	/**
	 * Save settings
	 */
	public function save() {
		global $current_section;
		
		Flickrocket::log('Info','Start save');
		
		$settings = $this->get_settings();

		WC_Admin_Settings::save_fields( $settings );
		
		// // Update roles/Groups assignment
		// $group_role_setting = stripslashes($_POST['group_to_role_setting']);
		// update_option( 'fr_roles_groups_assignment', $group_role_setting, false );

		Flickrocket::log('Info','End save');
	}
}

endif;

return new FlickrocketSettings();

?>