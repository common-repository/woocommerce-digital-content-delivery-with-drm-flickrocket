<?php
	
	class FlickrocketWoocommerce
	{
		public static function init() 
		{
			// Hooks
			add_action( 'add_meta_boxes', array( get_class(), 'flickRocketProjectIDField' ), 1, 1 );
			add_action( 'woocommerce_product_options_general_product_data', array( get_class(), 'woo_add_custom_general_fields' ));
			add_action( 'woocommerce_process_product_meta', array( get_class(), 'woo_add_custom_general_fields_save' ) );
			add_action( 'woocommerce_product_after_variable_attributes', array( get_class(), 'display_license_variations_field' ), 10, 3 );
			add_action( 'save_post', array( get_class(), 'fr_save_post' ), 12, 1 );
			add_action( 'woocommerce_save_product_variation', array( get_class(), 'flickRocketVariationSave' ), 10, 3 );
			add_filter( 'product_type_options', array( get_class(),'fr_product_type_options'), 15, 1);
			add_filter( 'pre_option_woocommerce_enable_guest_checkout', array( get_class(), 'conditional_guest_checkout_based_on_product'));
			add_action( 'woocommerce_after_customer_login_form', array( get_class(), 'my_account_login' ) );
			add_action( 'woocommerce_after_my_account', array( get_class(), 'my_account_logged_in' ) );
			add_action( 'woocommerce_order_details_after_order_table', array( get_class(), 'flickrocket_order_details_after_order_table' ));			
			add_action( 'woocommerce_order_status_processing', array( get_class(), 'process_order' ), -5, 2 );
			add_action( 'woocommerce_order_status_completed', array( get_class(), 'process_order' ), -5, 2 );
			add_action( 'woocommerce_single_product_summary', array( get_class(), 'show_preview_button' ), 10, 3 );
			add_action( 'woocommerce_email_after_order_table', array( get_class(), 'fr_add_order_email_instructions' ), 20, 4 );
			add_filter( 'woocommerce_login_redirect', array( get_class(), 'flickrocket_my_account_after_login' ), 10, 2);
			add_filter( 'parse_request', array( get_class(), 'flickrocket_my_account_redirect' ));
			add_filter( 'woocommerce_product_meta_end', array( get_class(), 'show_fr_link_at_product_meta_end' ), 10, 2);

			add_action( 'woocommerce_account_mycontent_endpoint', array( get_class(), 'mycontent_endpoint_content'), 10, 2 );
			add_filter( 'woocommerce_account_menu_items', array( get_class(),'my_account_menu_order' ));
		}

		public static function my_account_menu_order( $menuOrder ) {
    
			$toInsert = array('mycontent' => __( 'My Content', 'woocommerce-digital-content-delivery-with-drm-flickrocket' ));
			$newMenuOrder = self::array_insert_after($menuOrder, 'dashboard', $toInsert);
			return $newMenuOrder;
		}
		
		public static function array_insert_after( array $array, $key, array $new ) {
			$keys = array_keys( $array );
			$index = array_search( $key, $keys );
			$pos = false === $index ? count( $array ) : $index + 1;
		
			return array_merge( array_slice( $array, 0, $pos ), $new, array_slice( $array, $pos ) );
		}
	
		public static function mycontent_endpoint_content() {
			self::my_account_logged_in();
		}
	

		public static function show_fr_link_at_product_meta_end() 
		{
			global $product;

			$productID	 	= $product->id;
			$flickRProjectID = get_post_meta( $productID, '_flickrocket_project_key_id', true );
			
			if (!empty($flickRProjectID))
			{
				$company_id = get_option('fr_company_id', '');
				if (!empty($company_id))
				{
					echo '<br /><small>Digital delivery powered by <a href="https://www.flickrocket.com/third-party-link?plugin=woocommerce&cid='.$company_id.'">Flickrocket</a></small>';
				}
			}
		}

		public static function fr_add_order_email_instructions( $order, $sent_to_admin, $plain_text, $email ) 
		{
			try 
			{
				Flickrocket::log("Info", "Adding email: ".$email->id." | Option: ".get_option('fr_extend_emails', 'yes'));
				if ( $email->id == 'customer_completed_order' || $email->id == 'new_order') 
				{
					if (get_option('fr_extend_emails', 'yes') == 'no')
					{
						Flickrocket::log("Info", "Adding email instructions: Not active");
						return;
					} 
					
					$code_usage = false;
					$frProducts = array();
					
					foreach( $order->get_items() as $item_id => $item_obj){

						$product_id = $item_obj['product_id'];
						$variation_id = $item_obj['variation_id'];

						$flickRProjectID = get_post_meta( $product_id, '_flickrocket_project_key_id', true );
						$flickRLicenseID = get_post_meta( $product_id, '_product_license_id', true );
						$flickRVLicenseID = get_post_meta( $variation_id, '_variations_license_id', true );
						
						$flickRLicenseID = empty($flickRVLicenseID) ? $flickRLicenseID : $flickRVLicenseID;
						
						if (!empty($flickRProjectID) && Flickrocket::is_project_id_valid($flickRProjectID)) //license might be empty in case of access group products
						{
							Flickrocket::log("Info", "Adding email instructions: Product and license exists | item: ".strval($item_id));

							// Valid Flickrocket product
							$code = wc_get_order_item_meta($item_id, "code");

							if (!empty($code)) {
								$code_usage = true;
							} 
							$frProduct = array( "product_id" => $product_id,
												"quantity" => $item_obj['quantity'],
												"name" => $item_obj['name'],
												"code" => $code );
							$frProducts[] = $frProduct;
						}
					}
					
					if (empty($frProducts))
					{
						Flickrocket::log("Info", "No FR products in order");
						return; // return if no flickrocket product is included
					} 

					//Determine user status
					$token = null;
					$email = $order->billing_email;

					//Get one time password that migthb be stored
					$user = get_user_by( 'email', $email );
					$user_id = $user->ID;

					$password = get_user_meta( $user_id, 'fr_one_time_password', true );

					// Get SSO information from backend
					$only_sso_login = Flickrocket::get_sso_info();

					$html = "<hr>";
					if ($code_usage) 
					{
						// This is multi quantity items (code) order
						$html .= '<p>'.__('To access the content, the codes below need to be redeemed on the web site (for iOS devices) or in the players. You can 
		access from the "My Account" section of the URL below:','woocommerce-digital-content-delivery-with-drm-flickrocket').'</p>
						<p><a href="'.get_site_url().'">'.get_site_url().'</a></p>';

						if ($only_sso_login)
						{
							// Only SSO login, show no credentials
						}
						else 
						{
							// No SSO login or SSO login is only an option
							$html .= '<p>'.__('To manage the content, you can use the following credentials to log in to the content section:','woocommerce-digital-content-delivery-with-drm-flickrocket').'</p>
							<p>
								'.__('Email: ','woocommerce-digital-content-delivery-with-drm-flickrocket').$email.'<br />
								'.__('Password: ','woocommerce-digital-content-delivery-with-drm-flickrocket').(empty($password) ? '&lt;'.__('Your existing player password','woocommerce-digital-content-delivery-with-drm-flickrocket').'&gt;' : $password).'
							</p>'
							.(get_option('fr_use_sso', 'no') == 'yes' ? 
							__('<p>Or login with your shop account.</p>','woocommerce-digital-content-delivery-with-drm-flickrocket') : '').
							'<p>
								<small>
									'.__('To reset your password you can use the "Forgot password" option.','woocommerce-digital-content-delivery-with-drm-flickrocket').'
								</small>
							</p>';
						}

						$html .= '<hr /><p><b>'.__('Redemption codes:','woocommerce-digital-content-delivery-with-drm-flickrocket').'</b></p>';
						$html .= '<p></p><table width="100%"><tr><td>'
								.__('Product Name','woocommerce-digital-content-delivery-with-drm-flickrocket')
								.'</td><td>'.__('Quantity','woocommerce-digital-content-delivery-with-drm-flickrocket')
								.'</td><td>'.__('Redemption Code','woocommerce-digital-content-delivery-with-drm-flickrocket').'</td></tr>';
						foreach ($frProducts as $frProduct){
							$html.="<tr><td>".$frProduct["name"]."</td><td>".$frProduct["quantity"]."</td>";
							$html.="<td>".$frProduct["code"]."</td></tr>";
						}
						$html.="</table>";
					}
					else
					{
						// This is single quantity items (login) order
						$html .= '<p><b>'.__('Accessing your content','woocommerce-digital-content-delivery-with-drm-flickrocket').'</b><p>

						<p>'.__('You can now access your content. Content is accessed using a player app which manages download, storage, 
						and content playback.','woocommerce-digital-content-delivery-with-drm-flickrocket').'</p>

						<p>'.__('If you are already using the player just open the player to view your purchase. Otherwise, please follow the easy 
		steps below:','woocommerce-digital-content-delivery-with-drm-flickrocket').'</p>

						<ol>
							<li>'.sprintf(__('Use your store login for your order to sign-in at %s and go to the "My Account" page.','woocommerce-digital-content-delivery-with-drm-flickrocket'), '<a href="'.get_site_url().'">'.get_site_url().'</a>').'</li>
							<li>'.__('Select and install the player for your platform.','woocommerce-digital-content-delivery-with-drm-flickrocket').'</li>
							<li>'.__('Open the player and sign-in','woocommerce-digital-content-delivery-with-drm-flickrocket');
							if ($only_sso_login)
							{
								// Only SSO login, show no credentials
							}
							else
							{
								// No SSO login or SSO login is only an option
								$html .= '<li>'.(get_option('fr_use_sso', 'no') == 'yes' ? 
								__('Log in with your shop account or use the following:','woocommerce-digital-content-delivery-with-drm-flickrocket') :
								__('Use the following:','woocommerce-digital-content-delivery-with-drm-flickrocket'))
								.'<p>
									<b>'.__('Email: ','woocommerce-digital-content-delivery-with-drm-flickrocket').$email.'</b><br />
									<b>'.__('Password: ','woocommerce-digital-content-delivery-with-drm-flickrocket').(empty($password) ? '&lt;'.__('Your existing player password','woocommerce-digital-content-delivery-with-drm-flickrocket').'&gt;' : $password).'</b>
								</p>
								<p><small>'.__('Note that the password above is separate from the store password. You can reset your password anytime using the "Forgot password" function.','woocommerce-digital-content-delivery-with-drm-flickrocket').
								'</small></p>
							</li>';
							}
						$html .= '</ol>';
					}

					$html = $html."<hr>";

					$html = apply_filters( "fr_modify_order_email_instructions", $html );

					echo $html;
				}
			}
			catch (Exception $ex)
			{ 
				Flickrocket::log('Error','Exception: Adding email instructions: '.$ex.getMessage().' | Stacktrace: '.$ex.getTraceAsString());
			}
		}
		
		public static function show_preview_button() 
		{
			global $post;
			$post_id = $post->ID;
	
			if (get_option('fr_show_previews', 'no') == 'yes')
			{
				// Check if preview exists
				$url = get_post_meta( $post_id, '_flickrocket_preview', true );
				
				if (!empty($url))
				{
					$product_type = self::get_preview_type($url);
					$product_name = $post->post_title;
	
					$phtml = '';
					if ($product_type == 'video') {
						$phtml = "<video id='previewPlayer' class='media' width='100%' loop controls><source src='".$url."' type='video/mp4'>".__('Your browser does not support the video tag.','woocommerce-digital-content-delivery-with-drm-flickrocket')."</video>";
					}
					else if ($product_type == 'audio') {
						$phtml = "<audio class='media' width='100%' loop controls><source src='".$url."' type='audio/mpeg'>".__('Your browser does not support the audio tag.','woocommerce-digital-content-delivery-with-drm-flickrocket')."</audio>";
					}
					else if ($product_type == 'pdf') {
						$phtml = "<a href='".$url."'>".__('Download PDF','woocommerce-digital-content-delivery-with-drm-flickrocket')."</a>";
					}
	
					$output = '<input href="#" alt="#TB_inline?inlineId=frpreview&height=338&width=600" title="'.$product_name.'" class="thickbox button wp-element-button" type="button" value="Preview" onclick="playPreview()" />';
					$output .= '
<div id="frpreview" style="display:none">'
		.$phtml
.'</div>
';
					echo $output;
				}
			}
		}
	
		public static function get_preview_type ($sDownloadUrl)
		{
			$sType = "";
	
			try
			{
				// Find "&Key"
				$iKeyPos = strpos($sDownloadUrl, "&Key=");
	
				//Cut at next parameter
				$KeyString = substr($sDownloadUrl, $iKeyPos + 5, strpos($sDownloadUrl, "&", $iKeyPos + 5) - $iKeyPos - 5 );
				$Extension = substr($KeyString, strripos($KeyString,".") + 1, strlen($KeyString) - strripos($KeyString, '.') - 1 );
				$Extension = strtolower($Extension);
	
				switch ($Extension)
				{
					case "mp4":
						$sType = "video";
						break;
	
					case "mp3":
						$sType = "audio";
						break;
	
					case "pdf":
						$sType = "pdf";
						break;
				}
			}
			catch (Exception $e)
			{ }
	
			return $sType;
		}
	

		public static function flickrocket_order_details_after_order_table ( $order )
		{
			$orderID = $order->id;
			$orderDetails = new WC_Order( $orderID );
			$userEmailID = $orderDetails->get_billing_email();
			$items = $orderDetails->get_items();
			
			$flickProduct = 0;
			foreach($items as $itemsDetails){
				
				$productID		= $itemsDetails['product_id'];
				$variationPID	= $itemsDetails['variation_id'];
				
				$flickRProjectID = get_post_meta( $productID, '_flickrocket_project_key_id', true );
				$flickRLicenseID = get_post_meta( $productID, '_product_license_id', true );
				$flickRVLicenseID = get_post_meta( $variationPID, '_variations_license_id', true );
								
				$flickRLicenseID	= $flickRVLicenseID == '' ? $flickRLicenseID : $flickRVLicenseID;
				
				if ($flickRProjectID != "" && $flickRLicenseID != "-1" && $flickRLicenseID != -1) // license might be 0 in case of access group products
				{
					$flickProduct = 1;
					break;
				}
			}

			if ($flickProduct == 1)
			{
				$was_fr_order_error = get_post_meta($orderID, 'fr_error', True);
				if (!$was_fr_order_error)
				{
					Flickrocket::log("Info","Rendering FR iframe after success");
					echo self::flickRocketRenderIframe($userEmailID, true, false);
				}
				else
				{
					Flickrocket::log("Info","Rendering FR iframe after error");
					echo self::flickRocketRenderIframe($userEmailID, false, false);
				}
			}
		}

		// SSO redirect if user is already logged in 
		public static function flickrocket_my_account_redirect()
		{
			global $wp;

			if (is_user_logged_in() && preg_match( '%^my\-account(?:/([^/]+)|)/?$%', $wp->request, $m ) && !empty($_REQUEST['fr_redirect_uri']))
			{
				$redirect_uri = $_REQUEST['fr_redirect_uri'];
				if (!empty($redirect_uri))
					{
					$company_id = intval(get_option('fr_company_id', 0));

					$user = wp_get_current_user();
					$user_email = strtolower($user->user_email);
					$result = Flickrocket::get_one_time_key_for_email($user_email);
					if (!empty($result))
					{
						$one_time_key = $result["key"];

						// Build redirect_uri
						
						$sep = "?";
						if (strpos($redirect_uri, "?") !== false)
						{
							$sep = "&";
						}

						$return_uri = urldecode($redirect_uri).$sep."email=".urlencode($user_email)."&company_id=".$company_id."&key=".$one_time_key;

						wp_redirect( $return_uri );
						exit;
					}
				}
			}
			return;
		}

		public static function flickrocket_my_account_after_login( $redirect, $user )
		{
			$quest = strpos($redirect, "?");
			if ($quest === false) {
				return $redirect;
			}
			if (strlen($redirect) == $quest + 1) {
				return $redirect;
			}
			$query = substr($redirect, $quest + 1);
			$params = explode ("&", $query);

			$parsed = array();
			foreach($params as $param){
				$val = explode ("=", $param);
				if (count($val) == 2)
				{
					$parsed[$val[0]] = $val[1];
				}
			}

			// Get logged in email and company

			$user_email = strtolower($user->user_email);
			$company_id = intval(get_option('fr_company_id', 0));

			// Get one-time key for email

			$result = Flickrocket::get_one_time_key_for_email($user_email);
			if (empty($result)) {
				return $redirect;
			}
			
			$one_time_key = $result["key"];

			// Build redirect_uri
			
			$redirect_uri = $parsed["fr_redirect_uri"];
			$sep = "?";
			if (strpos($redirect_uri, "?") !== false)
			{
				$sep = "&";
			}

			$return_uri = urldecode($redirect_uri).$sep."email=".urlencode($user_email)."&company_id=".$company_id."&key=".$one_time_key;

			wp_redirect( $return_uri );
			exit;
		}

		public static function my_account_login()
		{
			// Show My Content if "unlock=1" URL parameter was passed
			if ( get_query_var('fr_unlock') ) {
				echo self::flickRocketRenderIframe('', true, true);
			}
		}

		public static function my_account_logged_in()
		{
			global $wp;

			$current_user = wp_get_current_user(); 
			$user_id = $current_user->ID;
			$user_email = strtolower($current_user->user_email);

			// Check for existing orders
			$orders = get_posts( array(
					'numberposts' => -1,
					'meta_key'    => '_customer_user',
					'meta_value'  => get_current_user_id(),
					'post_type'   => wc_get_order_types(),
					'post_status' => array_keys( wc_get_order_statuses() ),
				) );

			$found = '';
			foreach($orders as $o){
				$order_id = $o->ID;
				$order = new WC_Order($order_id);
				foreach( $order->get_items() as $item ){
					$product_id = $item['product_id'];
					
					$found = get_post_meta( $product_id, '_flickrocket_project_key_id', true );

					if ($found != "") break;
				}
				if ($found != "") break;
			}
			
			if ($found == '')
			{
				// No orders, so check for group membership now
				$ga_users = Flickrocket::get_access_group_users();
				foreach ($ga_users as $user) {
					if ($user_email == strtolower($user['email']))
					{
						$found = true;
						break;
					}
				}
			}

			// Check if this call was made with request to perform code unlock
			$is_unlock = false;
			$fr_unlock = get_query_var('fr_unlock');
			if ($fr_unlock == "1")
			{
				$is_unlock = true;
			}

			$request = explode( '/', $wp->request );

			if ($found != '' || $is_unlock)
			{
				// Customer has content or gets here to unlock content

				// Show content access on Dashboard and My Content pages
				if ( (end($request) == 'my-account' || end($request) == 'mycontent')  && is_account_page() ) {
					echo self::flickRocketRenderIframe($user_email, true, $is_unlock);
				}
			}
			else
			{
				// Customer has no content but might want to redeem code

				// Show code redemption (and login) only on "My Content" page
				if ( end($request) == 'mycontent' && is_account_page() ) {
					echo "<div id='fr_activate_redeem_code'>";
					echo 	"You have no digital content yet. If you have a code, click below to redeem it.<br /><br />";
					echo 	"<a href='#' onclick='fr_activate_redeem_code()'>Redeem code</a>";
					echo "</div>";
					echo "<div id='fr_redeem_code' style='display:none'>";
					echo 	self::flickRocketRenderIframe($user_email, true, true);
					echo "</div>";
				}
			}			
		}

		//Cleanup temporary flickrocket data
		public static function flick_meta_cleanup()
		{
			try {
				$expiry = date('Y-m-d H:i:s', time() - 3600 * 24 * 180);

				$args  = array(
					'meta_query' => array(
						array(
							'key' => 'flickrocket_timestamp',
							'value' => $expiry,
							'compare' => '<'
							),
					));

				// Create the WP_User_Query object
				$wp_user_query = new WP_User_Query($args);
				// Get the results
				$users = $wp_user_query->get_results();
				foreach ($users as $user)
				{
					delete_user_meta( $user->ID, 'flickrocket_email' );
					delete_user_meta( $user->ID, 'flickrocket_password' );
					delete_user_meta( $user->ID, 'flickrocket_timestamp' );
				}
			}
			catch(Exception $ex)
			{
				Flickrocket::log('Error','Error in flick_meta_cleanup: '.$ex.getMessage().' | Stacktrace: '.$ex.getTraceAsString());
			}
		}
		
		public static function flickRocketProjectIDField() 
		{
			add_meta_box(
				'flickrocket_projectid',
					__( 'Digital Content Delivery (incl. DRM) - FlickRocket','woocommerce-digital-content-delivery-with-drm-flickrocket' ), 
				array( get_class(), 'flickrocket_projectid_custom_box' ),
				'product' 
			);
		}
		
		public static function check_cart_contains_digital_items()
		{
			global $woocommerce;

			$contains_digital = false;

			try
			{
				if (empty($woocommerce->cart)) return false;
				
				$cart_contents = $woocommerce->cart->cart_contents;

				foreach($cart_contents as $item => $values) 
				{ 
					$productID	 	= $values['product_id'];
					$variationPID	 = $values['variation_id'];

					$flickRProjectID = get_post_meta( $productID, '_flickrocket_project_key_id', true );
					$flickRLicenseID = get_post_meta( $productID, '_product_license_id', true );
					$flickRVLicenseID = get_post_meta( $variationPID, '_variations_license_id', true );
									
					$flickRLicenseID	= $flickRVLicenseID == '' ? $flickRLicenseID : $flickRVLicenseID;
					
					if ($flickRProjectID != "" )
					{
						$contains_digital = true;
						break;
					}
				}
			}
			catch (Exception $ex)
			{}
			
			return $contains_digital;
		}

		public static function conditional_guest_checkout_based_on_product( $value ) 
		{
			if (self::check_cart_contains_digital_items()) {
				$value = "no";
			}

			return $value;
		}

		//Display project id box
		public static function flickrocket_projectid_custom_box( $post ) 
		{
			$id = $post->ID;
			$is_mirrored = get_post_meta( $id, '_fr_mirrored', true );
			$is_price_defined = get_post_meta( $id, '_fr_price_sync_ok', true );

			$value = get_post_meta( $id, '_flickrocket_project_key_id', true );

			Flickrocket::log("Info","flickrocket_projectid_custom_box: ".$value);

			echo '<label for="flickrocket_project_id">';
			_e( "FlickRocket Product:", 'woocommerce-digital-content-delivery-with-drm-flickrocket' );
			echo '</label> ';

			if ($is_mirrored != 'yes')
			{
				wp_nonce_field( 'flickrocket_projectid_custom_box', 'flickrocket_projectid_custom_box_nonce' );

				self::render_product_box('flickrocket_project_id', $value);
			}
			else
			{
				// Mirrored product, don't show product selection
				$disable_price = $is_price_defined && $is_mirrored ? ' id="fr_fixed_price_product_marker" ' : '';
				echo '<p><b>'.$value.'</b></p>';
				echo '<p'.$disable_price.'>'.__('Product associations for mirrored products must not be changed.','woocommerce-digital-content-delivery-with-drm-flickrocket').'</p>';
			}
		}

		//get projects list select box from flickrocket api 
		public static function render_product_box($fieldName, $productId)
		{
			echo ("<input type='text' id='".$fieldName."' name='".$fieldName."' value='".$productId."'>");

			echo ("<button type='button' id='fr_upload' name='fr_upload' class='button-primary'>Upload content</button>");

			echo '<div style="margin-left:125px;"><div style="margin:10px 0;">'.__('Enter the Flickrocket product id to be associated with this WooCommerce product.','woocommerce-digital-content-delivery-with-drm-flickrocket').'</div></div>';
			
			echo ("<hr />");

			echo ('
<label for="fr_content_type">Content type:</label>
<select name="fr_content_type" id="fr_content_type">
  <option value="0">Please select</option>
  <option value="1">Video (SD)</option>
  <option value="9">Video (HD)</option>
  <option value="7">PDF</option>
  <option value="16">Epub</option>
  <option value="29">HTML Package</option>
  <option value="30">SCORM Package</option>
  <option value="5">Audio Package</option>
  <option value="27">Generic File</option>
</select>
			');

			echo ("<input id='fr_create_product' name='submit' type='submit' value='Create new product in Flickrocket' class='button button-primary' disabled></input");
			echo ("&nbsp;<br />&nbsp;");
		}
		
		public static function woo_add_custom_general_fields() // Simple product
		{
			global $woocommerce, $post;

			$id = $post->ID;
			$is_mirrored = get_post_meta( $id, '_fr_mirrored', true );

			$license_id = get_post_meta( $post->ID, '_product_license_id', true );
			$quality_id = get_post_meta( $post->ID, '_variation_quality', true );
			$num_users = get_post_meta( $post->ID, '_num_users', true );

			echo '<div class="options_group" id="flickrocket_license_area">';

			//Fill quality
			$field['quality'] = array();
			$field['quality'][ 0 ] = __('Only for video: HD quality (if available)','woocommerce-digital-content-delivery-with-drm-flickrocket');
			$field['quality'][ 1 ] = __('Only for video: SD quality','woocommerce-digital-content-delivery-with-drm-flickrocket');
			$field['quality'][ 2 ] = __('Only for non DRM audio: MP3 format','woocommerce-digital-content-delivery-with-drm-flickrocket');
			$field['quality'][ 3 ] = __('Only for non DRM audio: FLAC format (if available)','woocommerce-digital-content-delivery-with-drm-flickrocket');

			if ($is_mirrored != 'yes')
			{
				// Standard product
				$getLicenseSB = Flickrocket::get_licenses();
				
				//Licenses
				if(!empty($getLicenseSB["licenses"]) && count($getLicenseSB["licenses"]) > 0)
				{
					//Fill licenses
					$field['licenses'] = array();
					$field['licenses'][ -1 ] = __('No digital','woocommerce-digital-content-delivery-with-drm-flickrocket');
					$field['licenses'][ 0 ] = __('No license','woocommerce-digital-content-delivery-with-drm-flickrocket');
					foreach($getLicenseSB["licenses"] as $sbValue){
						$field['licenses'][ $sbValue["id"] ] = $sbValue["name"];
					}

					//License drop down
					woocommerce_wp_select( 
						array( 
							'id'          => '_license['.$post->ID.']', 
							'label'       => __('License: ','woocommerce-digital-content-delivery-with-drm-flickrocket'), 
							'description' => __('Defines the usage rights Flickrocket permits.','woocommerce-digital-content-delivery-with-drm-flickrocket'),
							'value'       => $license_id,
							'options' 	  => $field['licenses']
							)
						);
				}

				//Quality drop down
				woocommerce_wp_select( 
					array( 
						'id'          => '_quality['.$post->ID.']', 
						'label'       => __('Quality: ','woocommerce-digital-content-delivery-with-drm-flickrocket'), 
						'description' => __('Content quality (only for HD video / non DRM audio).','woocommerce-digital-content-delivery-with-drm-flickrocket'),
						'value'       => $quality_id,
						'options' 	  => $field['quality']
						)
					);
				
				// Multi user 
				woocommerce_wp_text_input(
					array(
						'id' => '_multiuser['.$post->ID.']', 
						'label' => __('Multi User: ', 'woocommerce-digital-content-delivery-with-drm-flickrocket'), 
						'data_type' => 'decimal', 
						'value' => strval($num_users),
						'desc_tip' => true, 
						'description' => __('Set to higher than one to indicate multiple users', 'woocommerce-digital-content-delivery-with-drm-flickrocket')
					));

			}
			else
			{
				// Mirrored product, don't show license selection

				// Get license details
				$result = Flickrocket::get_license($licenseIDValue);
				if (array_key_exists('license', $result) && array_key_exists(0, $result['license']['locales']))
				{
					echo '<p><b>'.$result['license']['locales'][0]['name'].' ['.$field['quality'][$quality].']</b><br />';
					echo $result['license']['locales'][0]['description'].'</p>';
				}
				echo '<div style="margin:0 0 10px 150px;">'.__('License/quality data for mirrored products must not be changed.','woocommerce-digital-content-delivery-with-drm-flickrocket').'</div>';
			}
			echo '</div>';
		}
		
		// Display license variations field as a selectbox.
		public static function display_license_variations_field( $loop, $variation_data, $variation )
		{
			global $woocommerce, $post;

			$id = $post->ID;
			$is_mirrored = get_post_meta( $id, '_fr_mirrored', true );
			$is_price_defined = get_post_meta( $id, '_fr_price_sync_ok', true );

			$license_id = get_post_meta( $variation->ID, '_variations_license_id', true );
			$quality = get_post_meta( $variation->ID, '_variation_quality', true );
			$num_users = get_post_meta( $variation->ID, '_num_users', true );

			//Fill quality
			$field['quality'] = array();
			$field['quality'][ 0 ] = __('Only for video: HD quality (if available)','woocommerce-digital-content-delivery-with-drm-flickrocket');
			$field['quality'][ 1 ] = __('Only for video: SD quality','woocommerce-digital-content-delivery-with-drm-flickrocket');
			$field['quality'][ 2 ] = __('Only for non DRM audio: MP3 format','woocommerce-digital-content-delivery-with-drm-flickrocket');
			$field['quality'][ 3 ] = __('Only for non DRM audio: FLAC format (if available)','woocommerce-digital-content-delivery-with-drm-flickrocket');

			echo '<div class="options_group" id="flickrocket_license_area">';

			if ($is_mirrored != 'yes')
			{
				// Standard product
				$getLicenseSB = Flickrocket::get_licenses();

				//Fill licenses
				$field['licenses'] = array();
				$field['licenses'][ -1 ] = __('No digital','woocommerce-digital-content-delivery-with-drm-flickrocket');
				$field['licenses'][ 0 ] = __('No license','woocommerce-digital-content-delivery-with-drm-flickrocket');
				foreach($getLicenseSB["licenses"] as $sbValue){
					$field['licenses'][ $sbValue["id"] ] = $sbValue["name"];
				}
				//License drop down
				woocommerce_wp_select( 
					array( 
						'id'          => '_license['.$variation->ID.']',
						'label'       => __('License: ','woocommerce-digital-content-delivery-with-drm-flickrocket'), 
						'description' => __('Defines the usage rights Flickrocket permits.','woocommerce-digital-content-delivery-with-drm-flickrocket'),
						'value'       => $license_id,
						'options' 	  => $field['licenses']
						)
					);

				//Quality drop down
				woocommerce_wp_select( 
					array( 
						'id'          => '_quality['.$variation->ID.']', 
						'label'       => __('Quality: ','woocommerce-digital-content-delivery-with-drm-flickrocket'), 
						'description' => __('Content quality (only for HD video / non DRM audio).','woocommerce-digital-content-delivery-with-drm-flickrocket'),
						'value'       => $quality,
						'options' 	  => $field['quality']
						)
					);

				// Multi user 
				woocommerce_wp_text_input(
					array(
						'id' => '_multiuser['.$variation->ID.']', 
						'label' => __('Multi User: ', 'woocommerce-digital-content-delivery-with-drm-flickrocket'), 
						'data_type' => 'decimal', 
						'value' => strval($num_users),
						'desc_tip' => true, 
						'description' => __('Set to higher than one to indicate multiple users', 'woocommerce-digital-content-delivery-with-drm-flickrocket')
					));
				
			}
			else
			{
				// Don't show licens/quality options for mirrored products

				// Disable price marker for mirrored products with defined prices
				$disable_price = $is_price_defined && $is_mirrored ? ' id="fr_fixed_price_license_marker_'.$loop.'" ' : '';

				// Get license details
				$result = Flickrocket::get_license($license_id);
				if (array_key_exists('license', $result) && array_key_exists(0, $result['license']['locales']))
				{
					echo '<p><b>'.$result['license']['locales'][0]['name'].' ['.$field['quality'][$quality].']</b><br />';
					echo $result['license']['locales'][0]['description'].'</p>';
				}
				echo '<div'.$disable_price.'>'.__('License/quality data for mirrored products must not be changed','woocommerce-digital-content-delivery-with-drm-flickrocket').'</div>';
			}
			echo '</div>';
		}

		public static function woo_add_custom_general_fields_save( $post_id ) //simple product only
		{
			$license = $_POST['_license'][$post_id];
			$quality = $_POST['_quality'][$post_id]; 

			// $num_users = $_POST['_num_users'][$post_id]; 
			$num_users = $_POST['_multiuser'][$post_id]; 

			$flickrocketPT = 'no';
			if (array_key_exists('_flickrocket', $_POST))
			{
				if ($_POST['_flickrocket'] == 'on') $flickrocketPT = 'yes';
				update_post_meta( $post_id, '_flickrocket', esc_attr( $flickrocketPT ) );
			}

			if (!empty($license) && is_numeric($license) && $license > 0 )
			{ 	
				update_post_meta( $post_id, '_product_license_id', esc_attr( $license ) );
			}
			if ($quality != '' && is_numeric($quality)) 
			{
				update_post_meta( $post_id, '_variation_quality', esc_attr( $quality ) );
			}
			if (is_numeric($num_users) && $num_users > 0)
			{
				update_post_meta( $post_id, '_num_users', esc_attr( intval($num_users) ) );
			} 
		}
		 
		public static function fr_save_post( $post_id ) 
		{
			global $woocommerce;

			if( isset($_REQUEST['submit']) && $_REQUEST['submit'] == 'Create new product in Flickrocket')
			{
				// This is create product request, so attempt to create new product
				$post   = get_post( $post_id );

				$title = $post->post_title;
				$content = $post->post_content;

				if (empty($title) || empty($content)) {
					echo '<div class="notice notice-error is-dismissible">
<p>You need to enter a title and description before creating a product in Flickrocket</p>
</div>'; 
					return;
				}

				$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'single-post-thumbnail' );
				$new_product = array(	'product' => 
									array(	'title' => $title,
											'comment' => '',
											'version' => '1',
											'product_type' => intval($_REQUEST['fr_content_type']),
											'drm_protected' => true,
											'locales' => array (
												array(
													'title' => $title,
													'description' => $content,
													'language_id' => 'en',
													'version' => '1',
													'src' => (is_array($image) && array_key_exists(0, $image)) ? $image[0] : ''
												))));

				$product = Flickrocket::create_product($new_product);
				if (array_key_exists("product", $product)) {
					// Success
					$project_id = $product["product"]["product_id"];
					if (Flickrocket::is_project_id_valid($project_id)) {
						update_post_meta( $post->ID, '_flickrocket_project_key_id', $project_id );
					}
				}
				else if (array_key_exists("error", $product)) {
					$error_code = $product['error'];
					$error_text = $product['error_text'];
					$error_msg = $error_text.' ('.$error_code.')';
					update_option('fr_error', $error_msg);
					Flickrocket::log("Error","Error creating new product: ".$error_msg);
				}
			}
			else if ( array_key_exists('flickrocket_project_id',$_POST ))
			{
				$project_id = strtoupper(trim(sanitize_text_field( $_POST['flickrocket_project_id'] )));
				if (Flickrocket::is_project_id_valid($project_id)) {
					update_post_meta( $post_id, '_flickrocket_project_key_id', $project_id );
				}

				Flickrocket::log("Info","fr_save_post: ".$project_id);
			}
		}
		
		public static function flickRocketVariationSave($variationData)
		{
			global $woocommerce;

			$license = $_POST['_license'][ $variationData ];
			if (!empty($license)) update_post_meta( $variationData, '_variations_license_id', $license );

			$quality = $_POST['_quality'][ $variationData ];
			if ($quality != '') update_post_meta( $variationData, '_variation_quality', $quality );

			$num_users = $_POST['_multiuser'][ $variationData ];
			if (is_numeric($num_users)) update_post_meta( $variationData, '_num_users', intval($num_users) );
		}

		public static function flickRocketRenderIframe( $fr_user_email, $success, $showCodeUnlockProminently )
		{
			global $wp_object_cache;

			$html = '';

			try 
			{
				$_SESSION['email'] = $fr_user_email;

				if (!$success)
				{
					// Show error
					$html = '<div id="fr_error_div">'.
								'<div style="background-color:red;color:white;text-align:center;float:left;height:100px;width:100%">'.
									'An error occured during order processing. Please contact support.'.
								'</div>'.
							'</div>';
					return $html;
				}

				$user_id = 0;
				$one_time_password = "";
				$user_token = "";
				if (!empty($fr_user_email))
				{
					$user = get_user_by( 'email', $fr_user_email );
					if ($user === false)
					{
						Flickrocket::log('Info','In flickRocketRenderIframe: Unable to get user: '.$fr_user_email);
					}
					else
					{
						$user_id = $user->ID;
						$one_time_password = get_user_meta( $user_id, 'fr_one_time_password', true );
						$user_token = get_user_meta( $user_id, 'fr_user_token', true );

						Flickrocket::log('Info','In flickRocketRenderIframe | Email: '.$fr_user_email.' | user_id: '.$user_id.' | OTP: '.$one_time_password.' | token: '.$user_token);
					}

				}

				// Get SSO information from backend
				$only_sso_login = Flickrocket::get_sso_info();

				// Build URL
				$domain = get_option('fr_domain','');
				$url = $domain.'?embedded=1';

				if (!empty($user_token))
				{
					if (get_option('fr_use_legacy', 'no') == "yes" && !empty(get_option('flickrocket_theme_id','')))
					{
						//Do Multipass login if user_token is available
						$profile = array();
						$profile['token'] = $user_token;
						$profile['return_to'] = $url;

						$multipass_secret = get_option('fr_multipass_secret','');
						$m = new FlickrocketMultipass($multipass_secret);
						$token = $m->generate_token($profile);

						$url = FR_REST_URL.'/api/customers/login/'.get_option('flickrocket_theme_id','').'/'.$token;

						Flickrocket::log('Info','Multipass login for user '.$fr_user_email.' with token '.$user_token.' to URL '.$url);
					}
					else
					{
						// Obtain token for clientside MyContent
						$refresh_token = '';
						$oauth = Flickrocket::create_oauth($user_token, false);
						try 
						{
							$refresh_token = $oauth["refresh_token"];
						}
						catch(Exception $ex)
						{
							Flickrocket::log('Error','Error creating oauth: '.$refresh_token);
						}
					}
				}
				else 
				{
					Flickrocket::log('Info','Empty user_token');
				}

				if (get_option('fr_use_legacy', 'no') == "yes" && !empty(get_option('flickrocket_theme_id','')))
				{
					Flickrocket::log('Info','Use legacy MyContent');

					$html = '<div id="fr_wait_div">'.
							'<div style="float:left;height:400px;width:100%">'.
								'<iframe style="width:100%;height:400px;border:none;" src="'.plugins_url().'/woocommerce-digital-content-delivery-with-drm-flickrocket/wait.html"></iframe>'.
							'</div>'.
							'</div>'.
							'<div id="fr_content_div" style="display:none;">'.
								'<iframe onload="flickFrameLoaded()" style="width:100%;height:300px;border:none;" allow="encrypted-media '.$domain.'" src="'.$url.'" id="frIframe" allowfullscreen="true" webkitallowfullscreen="true" mozallowfullscreen="true"></iframe>'.
							'</div>';
				}
				else
				{
					// Determine language for MyContentJs

					$lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
					$acceptLang = ['de', 'fr', 'el', 'ja', 'pt']; 
					$lang = in_array($lang, $acceptLang) ? $lang : 'en-us';

					// Check if MyContent JSON is already in cache
					$output = $wp_object_cache->get( 'client_urls', 'Flickrocket');
					if (!$output)
					{
						// Not in cache - Get js file 
						$client_urls_url = FR_MYCONTENTJS_URL.'/client_urls_latest.json';

						$ch = curl_init();
						curl_setopt($ch, constant('CURLOPT_URL'), $client_urls_url);
						curl_setopt($ch, constant('CURLOPT_HTTPGET'), true);
						curl_setopt($ch, constant('CURLOPT_RETURNTRANSFER'), true);
						$output = curl_exec($ch);
						$info = curl_getinfo($ch);
						$curl_error = curl_errno($ch);

						if (is_numeric($info['http_code']) && $info['http_code'] >= 400 )
						{
							// Handle error
							$error = error_get_last();
							$html = '<p>Unable to load client urls: '.$error['message'].' | '.strval($curl_error).'</p>';
							
							Flickrocket::log('Error','Unable to load client urls:: '.$error['message'].' | '.strval($curl_error));
							curl_close($ch);
						}
						else 
						{	
							$client_urls = json_decode($output);
							$wp_object_cache->set( 'client_urls', $output, 'Flickrocket', 600 ); // Update cache (expire in 10 minutes)
							curl_close($ch);
						}
					}
					else 
					{
						// MyContent JSON is already in cache
						$client_urls = json_decode($output);
					}

					$unlock_url = get_permalink( wc_get_page_id( 'shop' ) ) . 'my-account/?fr_unlock=1';

					if (empty($html))
					{
						$html =	'<script>window["_fr_companyId"]='.get_option('fr_company_id', "0").';window["_fr_token"]="'.$refresh_token.'";'.
						'window["_fr_email"]="'.$fr_user_email.'";window["_fr_otp"]="'.$one_time_password.'";'.
						($showCodeUnlockProminently ? 'window["_fr_prominentCodeUnlock"]=1;' : '').
						'window["_fr_unlockUrl"]="'.$unlock_url.'";'.
						'window["_fr_sso"]='.($only_sso_login ? "true" : "false").';'.'</script>'.
						'<mc-root></mc-root>'.
						'<script src="'.FR_MYCONTENTJS_URL.'/'.$lang.'/'.$client_urls->runtime_js.'" defer></script>'.
						'<script src="'.FR_MYCONTENTJS_URL.'/'.$lang.'/'.$client_urls->polyfills_js.'" defer></script>'.
						(FR_DEBUG ? '<script src="'.FR_MYCONTENTJS_URL.'/'.$lang.'/'.$client_urls->vendor_js.'" defer></script>' : '').
						'<script src="'.FR_MYCONTENTJS_URL.'/'.$lang.'/'.$client_urls->main_js.'" defer></script>';

					}
				}
			}
			catch(Exception $ex)
			{
				Flickrocket::log('Error','Exception in flickRocketRenderIframe: '.$ex.getMessage().' | Stacktrace: '.$ex.getTraceAsString());
			}

			return $html;
		}		

		//show payment status complete when use coupon code		
		public static function process_order( $wc_order_id, $wc_order )
		{
			global $woocommerce;

			try
			{
				if ($wc_order->status == 'processing' || $wc_order->status == 'completed')
				{
					if ( get_post_meta( $wc_order_id, '_fr_sent_complete', true ) != '' ) // do nothing if order was already processed
					{
						Flickrocket::log("Info","Duplicate order: ".$wc_order_id);
						return;
					}
					update_post_meta( $wc_order_id, '_fr_sent_complete', time() ); // Mark as being processed 

					$transactionID	= 'FR-WC-'.$wc_order_id;
					$cart_contents = $wc_order->get_items();

					$order = array();
					$quantity_higher_than_one = false;

					foreach($cart_contents as $item => $values) 
					{ 
						$productID	 	= $values['product_id'];
						$quantity		= intval($values['qty']);
						$variationPID	 = $values['variation_id'];

						$flickRProjectID = get_post_meta( $productID, '_flickrocket_project_key_id', true );

						if ($variationPID == 0 || $variationPID == '' )
						{
							// Get simple product data
							$flickRLicenseID = get_post_meta( $productID, '_product_license_id', true );
							$flickRQuality = get_post_meta( $productID, '_variation_quality', true );
							$num_users = get_post_meta( $productID, '_num_users', true );
						}
						else
						{
							// Get variable product data
							$flickRLicenseID = get_post_meta( $variationPID, '_variations_license_id', true );
							$flickRQuality = get_post_meta( $variationPID, '_variation_quality', true );
							$num_users = get_post_meta( $variationPID, '_num_users', true );
						}

						Flickrocket::log("Info","process_order (1): ".$wc_order_id." | ".$productID." | ".$flickRProjectID." | ".$flickRLicenseID);
										
						if ($flickRQuality == null || $flickRQuality == "") $flickRQuality = 0;

						if ($flickRProjectID != "" && 
							$flickRLicenseID != "-1" && $flickRLicenseID != -1 && $flickRLicenseID != '' && $flickRLicenseID != null) // License might be 0 in case of access group products
						{
							$orderitem = new stdClass();
							$orderitem->product_id = strtoupper($flickRProjectID);
							$orderitem->license_id = intval($flickRLicenseID);

							if ($quantity > 1)
							{ 
								$quantity_higher_than_one = true;
							}

							$orderitem->quantity = $quantity;
							if ($flickRQuality < 2)
							{
								// Video
								$orderitem->hd = $flickRQuality == 0 ? true : false;
							}
							else if ($flickRQuality >= 2)
							{
								//Audio
								$orderitem->audio_type = $flickRQuality == 2 ? 0 : 1;
							}

							if (intval($num_users) > 1)
							{ 
								$orderitem->num_users = intval($num_users);
								$quantity_higher_than_one = true;
							}

							$order[] = $orderitem;
						}
					}

					// if option to not credit orders directly, make sure to create codes instead
					if (get_option('fr_credit_orders_directly', 'yes') == 'no')
					{
						$quantity_higher_than_one = true;
					}

					$customer_userID = $wc_order->user_id;
					$order_email = $wc_order->get_billing_email();
					
					$password = null;
					if ( !empty($order) )
					{
						Flickrocket::log("Info","Processing order with FR items | for WC user ID: ".$customer_userID);

						$customer = array( 	'email' => $order_email,
							'first_name' => $wc_order->get_billing_first_name(),
							'last_name' => $wc_order->get_billing_last_name(),
							'company'=> $wc_order->get_billing_company(),
							'addresses' => array(array( 'first_name' => $wc_order->get_billing_first_name(),
														'last_name' => $wc_order->get_billing_last_name(),
														'company'=> $wc_order->get_billing_company(),
														'address1' => $wc_order->get_billing_address_1(),
														'address2' => $wc_order->get_billing_address_2(),
														'city' => $wc_order->get_billing_city(),
														'province' => $wc_order->get_billing_state(),
														'zip' => $wc_order->get_billing_postcode(),
														'country' => $wc_order->get_billing_country()))
							);

						//Check if user already exists
						$errors = Flickrocket::check_customer_exists($order_email, '-some-random-password-TRfdsHG652fdsd'); //ToDo: Empty password fails, to fix in REST API

						Flickrocket::log("Info","Sending order to FR");
						if (empty($errors))
						{
							// New user - generate and use new password

							//Prepare password
							$password = self::generate_password();

							//Send order
							$result = Flickrocket::send_order( $customer, $password, $transactionID, $order, $quantity_higher_than_one, $customer_userID ); 
						}
						else if (array_key_exists("customer", $errors))
						{
							// Customer known and password matches, so keep stored password
							$result = Flickrocket::send_order( $customer, null, $transactionID, $order, $quantity_higher_than_one, $customer_userID );
						}
						else
						{
							//Customer already known, but password does not match, send order
							update_user_meta( $customer_userID, 'fr_one_time_password', '' );
							$result = Flickrocket::send_order( $customer, null, $transactionID, $order, $quantity_higher_than_one, $customer_userID );
						}

						Flickrocket::log("Info","Order result: ".json_encode($result));

						if ( !array_key_exists('error', $result ))
						{
							if ($password != null)
							{
								//New user - store one-time password
								update_user_meta( $customer_userID, 'fr_one_time_password', $password );
								update_user_meta( $customer_userID, 'flickrocket_timestamp', date("Y-m-d H:i:s"));
							}

							$user_token = $result['order']['customer']['token'];
							update_user_meta( $customer_userID, 'fr_user_token', $user_token);
							Flickrocket::log("Info","Updated token | user ID:".$customer_userID." | token: ".$user_token);

							// If code order, store codes
							if ($quantity_higher_than_one)
							{
								Flickrocket::log("Info","Order with code(s)");
								foreach( $wc_order->get_items() as $item_id => $item_obj ){
									$product_id = $item_obj['product_id'];
									$flickRProjectID = strtoupper(get_post_meta( $product_id, '_flickrocket_project_key_id', true ));

									foreach ($result["order"]["line_items"] as $lineitem) {
										if ($flickRProjectID == $lineitem["product_id"]) {
											$code = $lineitem["code"];
											$im = wc_update_order_item_meta($item_id, 'code', $code);
											break;
										}
									}
								}
							}

							$wc_order->update_status( 'completed' );
						}
						else
						{
							update_post_meta($wc_order_id, 'fr_error', True);

							Flickrocket::log("Error","Error sending FR order");

							$error = apply_filters('flickrocket_order_complete_error_text', __('The order failed with error ','woocommerce-digital-content-delivery-with-drm-flickrocket').$result['error'].'('.$result['error_text'].')', $result['error']);

							wc_add_notice( $error, 'error' );

							$result = wp_mail(	get_option('fr_error_mail', 'postmaster@localcost'),
												__('Error sending order to Flickrocket (WooCommerce)','woocommerce-digital-content-delivery-with-drm-flickrocket'),
												$error);
						}
					}
				}
			}
			catch(Exception $ex)
			{
				Flickrocket::log('Error','Exception in process_order: '.$ex.getMessage().' | Stacktrace: '.$ex.getTraceAsString());
			}
			Flickrocket::log("Info","Exit process_order");
			return;
		}

		//Generate random two words password
		public static function generate_password()
		{
			// Define the file
			$file = FW_PATH.'/words.json';
		
			// Read file
			$filedata = file_get_contents($file);;
		
			// Decode the JSON to array
			$words = json_decode( $filedata );

			//Get random words
			$rand1 = mt_rand(0, count($words));
			$rand2 = mt_rand(0, count($words));

			$password = $words[$rand1].' '.$words[$rand2];

			return $password;
		}

		// add filckrocket custom checkbox	
		public static function fr_product_type_options( $types ){
			$types[ 'flickrocket' ] = array(
					'id'            => '_flickrocket',
					'wrapper_class' => 'show_if_simple show_if_variable',
					'label'         => __('FlickRocket','woocommerce-digital-content-delivery-with-drm-flickrocket'),
					'description'   => __('FlickRocket products allow DRM protected digital content access.','woocommerce-digital-content-delivery-with-drm-flickrocket'),
					'default'       => 'no'
				);
			return $types;
		}

	}
	
	//--------------------------- end class ----------------------------------
	global $woocommerce;
	
	if(isset($_REQUEST['fr_action']) && $_REQUEST['fr_action'] == 'checkContent')
	{
		$fr_user_email = $_SESSION['email'];
		
		define( 'FW_PATH2',	dirname(__FILE__) );
		include_once FW_PATH2."/../../../../wp-includes/pluggable.php";

		//Get one time password that might be stored
		$user = get_user_by( 'email', $fr_user_email );
		$user_id = $user->ID;
		// $one_time_password = get_user_meta( $user_id, 'fr_one_time_password', true );
		$user_token = get_user_meta( $user_id, 'fr_user_token', true );

		// Build URL
		$domain = get_option('fr_domain','');
		$url = $domain.'?embedded=1';

		if (!empty($user_token))
		{
			//Do Multipass login if user_token is available
			$profile = array();
			$profile['token'] = $user_token;
			$profile['return_to'] = $url;

			$multipass_secret = get_option('fr_multipass_secret','');
			$m = new FlickrocketMultipass($multipass_secret);
			$token = $m->generate_token($profile);

			$url = FR_REST_URL.'/api/customers/login/'.get_option('flickrocket_theme_id','').'/'.$token;

			Flickrocket::log('Info','Multipass login for user '.$fr_user_email.' with token '.$user_token.' to URL '.$url);
		}

		$responseData['return'] =
				'<div id="fr_div">'.
					'<iframe onload="flickFrameLoaded()" style="width:100%;height:300px;border:none;" allow="encrypted-media '.$domain.'" src="'.$url.'" id="frIframe" allowfullscreen="true" webkitallowfullscreen="true" mozallowfullscreen="true"></iframe>'.
				'</div>';

		echo json_encode($responseData);
		exit;
	}
?>
