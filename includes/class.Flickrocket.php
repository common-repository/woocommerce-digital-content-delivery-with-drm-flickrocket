<?php

class Flickrocket
{
	public static function FrRestCall($url, $data, $method)
	{
		// Try all 20 seconds up to 5 minutes in case server returns 503 error
		for ($retryCount = 0; $retryCount < 10; $retryCount++)
		{
			self::log('Info','REST start: '.$url.' | method: '.$method);

			//Check if token is still valid
			$current_expiry = get_option('fr_access_token_expiry', false);
			if ($current_expiry < time() + 300 ) self::FrExchangeToken(); //Handle token expiration

			$ch = curl_init();
			curl_setopt($ch, constant('CURLOPT_URL'), $url);
			
			if ($method == 'POST' || $method == 'PUT')
			{
				curl_setopt($ch, constant('CURLOPT_'.$method), true);
				$postdata = json_encode($data);
				curl_setopt($ch, constant('CURLOPT_POSTFIELDS'), $postdata);
			}
			else if ($method == 'GET')
			{
				curl_setopt($ch, constant('CURLOPT_HTTPGET'), true);
			}
			else if ($method == 'DELETE')
			{
				curl_setopt($ch, constant('CURLOPT_CUSTOMREQUEST'), 'DELETE');
			}
			$access_token = get_option('fr_access_token','');
			$headers = ['Content-Type: application/json',
						'Authorization: Bearer '.$access_token];
			curl_setopt($ch, constant('CURLOPT_HTTPHEADER'), $headers);
			curl_setopt($ch, constant('CURLOPT_RETURNTRANSFER'), true);

			$output = curl_exec($ch);
			$info = curl_getinfo($ch);
			$curl_error = curl_errno($ch);
			curl_close($ch);

			

			//Handle timeout or server maintenance error by retry
			if (is_numeric($info['http_code']) && $info['http_code'] == 503 || $curl_error == 28)
			{
				self::log('Error','REST end (error): '.$url.' | method: '.$method);

				sleep(5); //Try again in 5 seconds
				continue;
			}
			else if (is_numeric($info['http_code']) && $info['http_code'] >= 400 )
			{
				// Final error
				self::log('Error','REST end (final error): '.$url.' | method: '.$method);

				$error = array('error' => $info["http_code"]);
				$error['error_text'] = $output;
				return $error;
			}

			self::log('Info','REST end (success): '.$url.' | method: '.$method);
			break;
		}

		$json = json_decode($output, true);
		return $json;
	}

	public static function FrExchangeToken()
	{
		$result = false;
		try 
		{
			$lock_file = path_join(get_temp_dir(), 'fr_lock.file');
			$fp = fopen($lock_file, 'c'); //Critical section
			if (flock($fp, LOCK_EX)) 
			{
				try
				{
					$current_expiry = get_option('fr_access_token_expiry', false);
					if (!empty($current_expiry))
					{
						if  ($current_expiry < time() + 300 )
						{
							$output = '';
							$token = '';
							for ($i = 0; $i < 3; $i++)  // Try up to three times
							{
								// Token expired and needs replacement
								$url = FR_OAUTH_URL.'/token';
								$token = get_option('fr_refresh_token', false);
								$data = 'grant_type=refresh_token&refresh_token='.$token.'&client_id='.FR_CLIENT_ID;

								self::log('Info','Start exchange token: '.$token);
							
								$ch = curl_init();
								curl_setopt($ch, constant('CURLOPT_URL'), $url);
								curl_setopt($ch, constant('CURLOPT_POST'), true);
								curl_setopt($ch, constant('CURLOPT_POSTFIELDS'), $data);
								$headers = ['Content-Type: application/x-www-form-urlencoded; charset=utf-8'];
								curl_setopt($ch, constant('CURLOPT_HTTPHEADER'), $headers);
								curl_setopt($ch, constant('CURLOPT_RETURNTRANSFER'), true);

								$output = curl_exec($ch);
								$info = curl_getinfo($ch);
								curl_close($ch);
								if ($info['http_code'] == 503)
								{
									sleep(5 + i * 5); // Server temporarily not available -> try again
									continue;
								}
								else if ($info['http_code'] > 200)
								{
									break; // Probably permanent error -> don't retry
								}
								else
								{
									// Handle success
									$json = json_decode($output, true);

									$refresh_token = $json["refresh_token"];
									$access_token = $json["access_token"];
									$expiration = $json[".expires"];
									$access_token_expiry = strtotime($expiration);
									
									if (!empty($refresh_token) && !empty($access_token) && !empty($expiration))
									{
										update_option( 'fr_refresh_token', $refresh_token, false);
										update_option( 'fr_access_token', $access_token, false);
										update_option( 'fr_access_token_expiry', $access_token_expiry, false);
										update_option( 'fr_oauth_err_count', 0, false);
										update_option( 'fr_error','', false);
										wp_cache_flush();
										$result = true;

										self::log('Info','Exchange token success: '.$refresh_token);
										break;
									}
								}
							}
							if (!$result)
							{
								// Handle error
								if (strpos($output, 'invalid_grant') !== false)
								{
									$num_oauth_err = get_option('fr_oauth_err_count', 0);
									if ($num_oauth_err > 3)
									{
										self::fr_report_token_info($token, 0); // Report problem
										self::log('Error','Token exchange failed with invalid_grant');
									}
									
									// Increase error count
									$num_oauth_err += 1;
									update_option( 'fr_oauth_err_count', $num_oauth_err, false);
								}
								else
								{
									self::log('Error','Token exchange failed with generic error');
								}
								update_option( 'fr_error', __('The plugin "WooCommerce Digital Content Delivery (incl. DRM) - FlickRocket" encountered a potential credential problem. Please check your <a href="/wp-admin/admin.php?page=wc-settings&tab=flickrocket">login data</a>.',
									'woocommerce-digital-content-delivery-with-drm-flickrocket'), false);
							}
						}
						else
						{
							// Token still valid
							$result = true;
							self::log('Info','Exchange token still valid: '.$refresh_token);
						}
					}
					else
					{
						// No token available yet, ask for login
						update_option( 'fr_error', __('WooCommerce Digital Content Delivery (incl. DRM) - FlickRocket: <a href="/wp-admin/admin.php?page=wc-settings&tab=flickrocket"> Login required</a>','woocommerce-digital-content-delivery-with-drm-flickrocket'), false);
						self::log('Info','Exchange token does not exist yet');
					}
				}
				catch (Exception $ex2)
				{
					self::log('Error','Exchange token detail exception: '.$ex2.getMessage());
				}
				flock($fp, LOCK_UN); // release the lock
			}
			else
			{
				// Unable to get lock
				self::log('Error','Unable to get exchange token lock');
			}
			self::log('Info','Exchange token end');
		}
		catch (Exception $ex)
        {
            self::log('Error','Exchange token main exception: '.$ex.getMessage());
        }
        fclose($fp); //release the lock
		return $result;
	}

	public static function fr_report_token_info($token, $action) 
	{
		global $wpdb, $FlickPluginCurrentVersion;

		$url = FR_REST_URL.'/api/tokenfailed.json';
		$company_id = intval(get_option('fr_company_id', 0));

		// Send notification only if valid company data and token
		if ($company_id != 0 && !empty($token))
		{
			$data['action'] = $action; // 0 = token exchange problem (invalid_grant) | 1 = initial token exchange success
			$data['token'] = $token;
			$data['company_id'] = $company_id;
			$data['machinename'] = gethostname().'/'.$wpdb->prefix; 
			$data['client_id'] = FR_CLIENT_ID;
			$data['version'] = $FlickPluginCurrentVersion;

			$message = '';
			if ($action == 0) $message = 'invalid_grant';
			$data['message'] =  $message;

			$postdata = json_encode($data);
		
			$ch = curl_init();
			curl_setopt($ch, constant('CURLOPT_URL'), $url);
			curl_setopt($ch, constant('CURLOPT_POST'), true);
			curl_setopt($ch, constant('CURLOPT_POSTFIELDS'), $postdata);
			curl_setopt($ch, constant('CURLOPT_HTTPHEADER'), ['Content-Type: application/json']);
			curl_setopt($ch, constant('CURLOPT_RETURNTRANSFER'), true);

			$output = curl_exec($ch);
			$info = curl_getinfo($ch);
			$curl_error = curl_errno($ch);

			if ($info['http_code'] != 200)
			{
				// Log error
				self::log('Error','Error reporting token for company_id: '.$company_id);
			}

			curl_close($ch);
		}
	}

	public static function fr_oauth_callback($code, $companyid)
	{
		$my_company_id = intval($companyid);

		$url = FR_OAUTH_URL.'/token';
		$data = 'grant_type=refresh_token&refresh_token='.$code.'&client_id='.FR_CLIENT_ID;

		$ch = curl_init();
		curl_setopt($ch, constant('CURLOPT_URL'), $url);
		curl_setopt($ch, constant('CURLOPT_POST'), true);
		curl_setopt($ch, constant('CURLOPT_POSTFIELDS'), $data);
		curl_setopt($ch, constant('CURLOPT_RETURNTRANSFER'), true);
		$headers = ['Content-Type: application/x-www-form-urlencoded; charset=utf-8'];
		curl_setopt($ch, constant('CURLOPT_HTTPHEADER'), $headers);

		$content = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);
		if ($info['http_code'] != 200) return false;
		
		$json = json_decode($content, true);

		$refresh_token = $json["refresh_token"];
		$access_token = $json["access_token"];

		$expiration = $json[".expires"];
		$access_token_expiry = strtotime($expiration);
		
		update_option( 'fr_company_id', $my_company_id, false);
		update_option( 'fr_refresh_token', $refresh_token, false);
		update_option( 'fr_access_token', $access_token, false);
		update_option( 'fr_access_token_expiry', $access_token_expiry, false);
		update_option( 'fr_oauth_err_count', 0, false);
		update_option( 'fr_error','', false);

		self::fr_report_token_info($code, 1); // Report success

		return true;
	}

	// Obtain token for client side 
	public static function create_oauth( $user_token, $offline = false)
	{
		$str = '';
		if ($offline)
		{
			$str = '&access_type=offline';
		}
		$url = FR_REST_URL.'/api2/customers/CreateOAuth.json?token='.urlencode($user_token).
			'&scopes=player'.$str.'&client_id=FluxPlayer.1509240500.apps.flickrocket.com';
		
		self::log('Info','URL: '.$url);

		$result = self::FrRestCall($url, null, 'GET');
		return $result;
	}

	//get one time key for email 
	public static function get_one_time_key_for_email( $email )
	{
		$url = FR_REST_URL."/api2/onetime_key.json?value=".urlencode($email);
		$result = self::FrRestCall($url, null, 'GET');
		return $result;
	}


	//check flickrocket user exists 
	public static function check_customer_exists( $flickRUserEmail, $flickRUserPSW )
	{
		$url = FR_REST_URL.'/api2/customers.json?email='.urlencode($flickRUserEmail).'&password='.urlencode($flickRUserPSW);
		$result = self::FrRestCall($url, null, 'GET');
		return $result;
	}

	// Get apps 
	public static function get_apps()
	{
		$url = FR_REST_URL.'/api/apps.json';
		$result = self::FrRestCall($url, null, 'GET');
		if (array_key_exists('app', $result)) return array('apps' => array($result['app'])); // Handle case for only one product
		return $result;
	}

	// Get SSO information from backend
	public static function get_sso_info()
	{
		$only_sso_login = false;
		if (get_option('fr_use_sso', 'no') == "yes")
		{
			$only_sso_login = false;
			$company = self::get_company_infos("");
			if (!empty($company))
			{
				if (!empty($company["company"]))
				{
					$only_sso_login = $company["company"]["only_sso_login"];
				}
			}
		}
		return $only_sso_login;
	}
	
	// Get company information 
	public static function get_company_infos($fields)
	{
		$url = FR_REST_URL.'/api/companies.json';
		if ($fields != '') $url.='?fields='.$fields;
		$result = self::FrRestCall($url, null, 'GET');
		return $result;
	}

	//create order
	public static function send_order( $customer, $password, $transactionID, $order, $quantity_higher_than_one, $customer_userID)
	{
		$json = array(
			'order' => array(
				'order_number' => $transactionID,
				'confirmed' => true,
				'email' => $customer['email'],
				// 'theme_setting_id' => intval(get_option('flickrocket_theme_id', null)),
				'line_items' => $order,
				'customer_notification' => 0, // 0 = Customers don't receive email from the backend
				'password_option' => 1,  // 0 = Default / 1 = one time password
				'generate_codes' => $quantity_higher_than_one
			));

		if (get_option('fr_use_sso', 'no') == "yes")
		{
			$company_id = get_option('fr_company_id', 0);

			// Add SSO information
			$sso_info = array(
				'platform_id' => 2, // WooCommerce = 2 
				'shop_ident' => 'wc-'.$company_id, // Woocommerce has no multiple shops per instance
				'customer_id' => $customer_userID,
			);
			$json['order']['sso_info'] = $sso_info;

			$sso_origin_shop = array(
				'sso_origin_platform_id' => 2, // WooCommerce = 2 
				'shop_ident' => 'wc-'.$company_id, // Woocommerce has no multiple shops per instance
				'platform_name' => get_bloginfo( 'name' ). " Shop",
				'platform_login_url' => wc_get_page_permalink( 'myaccount' ),
			);
			$json['order']['sso_origin_shop'] = $sso_origin_shop;
		}

		if ($password != null)
		{
			// New customer
			$url = FR_REST_URL.'/api/orders.json';

			$json['order']['customer'] = $customer;
			$json['order']['customer']['password'] = $password;
			$json['order']['customer']['password_confirmation'] = $password;
		}
		else
		{
			// existing customer
			$url = FR_REST_URL.'/api/orders.json?email='.$customer['email'];
			//$json['order']['customer'] = array 'email' => $customer['email']);
			$json['order']['customer'] = $customer;
		}

		self::log('Info','URL: '.$url);
		self::log('Info','JSON: '.json_encode($json));
		
		$result = Flickrocket::FrRestCall($url, $json, 'POST');

		return $result; 
	}
	
	public static function get_products( $Page )
	{
		$url = FR_REST_URL.'/api/products.json?fields=id,locales,product_id,price_license_binding,product_type,title,valid,published_at,content_files,mirrored,version,categories&Page='.$Page;
		$result = self::FrRestCall($url, null, 'GET');
		return $result;
	}

	public static function get_all_products()
	{
		$url = FR_REST_URL.'/api/products.json?fields=id,locales,product_id,price_license_binding,product_type,title,valid,published_at,content_files,mirrored,version,categories';
		$result = self::FrRestCall($url, null, 'GET');
		return $result;
	}

	public static function get_currency_rate( $currency )
	{
		$url = FR_REST_URL.'/api/currency_rates.json';
		$result = self::FrRestCall($url, null, 'GET');

		//Parse currencies
		$oneEurEquals_n_currency = 0;
		$oneEurEquals_n_usd = 0;

		foreach ($result['currency_rates'] as $rate)
		{
			if (strtolower($currency) == strtolower($rate['currency']))	$oneEurEquals_n_currency = $rate['rate_to_eur'];
			if (strtolower($rate['currency']) == 'usd')	$oneEurEquals_n_usd = $rate['rate_to_eur'];
			if ( $oneEurEquals_n_currency != 0 && $oneEurEquals_n_usd != 0 ) break;
		}

		$rates = array();
		if ($oneEurEquals_n_currency != 0)
		{
			// Currency was found
			$rates['eur'] = $oneEurEquals_n_currency;
			$rates['usd'] = $oneEurEquals_n_currency / $oneEurEquals_n_usd;
		}

		return $rates;
	}

	public static function get_categories()
	{
		$url = FR_REST_URL.'/api/categories.json';
		$result = self::FrRestCall($url, null, 'GET');
		if (array_key_exists('categories', $result)) return $result['categories'];
		if (array_key_exists('category', $result)) return $result['category'];
		return $result;
	}

	public static function get_product($short_product_id)
	{
		$url = FR_REST_URL.'/api/products/'.$short_product_id.'.json?fields=id,locales,product_id,price_license_binding,product_type,title,valid,published_at,content_files,mirrored,version,categories';
		$result = self::FrRestCall($url, null, 'GET');
		return $result;
	}

	//get flickrocket license data
	public static function get_licenses()
	{
		$url = FR_REST_URL.'/api/licenses.json?fields=id,name,locales';
		$ret = self::FrRestCall($url, null, 'GET');

		return $ret;
	}
	
	// get individual license
	public static function get_license($license_id)
	{
		$url = FR_REST_URL.'/api/licenses/'.$license_id.'.json?fields=name,locales';
		$result = Flickrocket::FrRestCall($url, null, 'GET');
		return $result;
	}

	// get all prices
	public static function get_prices()
	{
		$url = FR_REST_URL.'/api/prices.json';
		$result = Flickrocket::FrRestCall($url, null, 'GET');
		return $result;
	}

	// get individual price
	public static function get_price($price_id)
	{
		$url = FR_REST_URL.'/api/prices/'.$price_id.'.json';
		$result = Flickrocket::FrRestCall($url, null, 'GET');
		return $result;
	}

	// get product count
	public static function get_product_count()
	{
		$url = FR_REST_URL.'/api/products/count.json';
		$result = Flickrocket::FrRestCall($url, null, 'GET');
		return $result;
	}
	
	//get flickrocket theme settings
	public static function get_theme_settings()
	{
		$url = FR_REST_URL.'/api/themesettings.json?fields=id,domain,name,enabled';
		$themes = self::FrRestCall($url, null, 'GET');

		if (array_key_exists('theme_settings', $themes)) return $themes["theme_settings"];
		return array(); // Return empty array on error
	}

	//get LicenseHistory
	public static function get_license_history($start_date, $end_date) // '2021-05-04';
	{
		$url = FR_REST_URL.'/api2/shop/license_history.json?created_at_min='.$start_date.'&created_at_max='.$end_date.'&responsetype=EChartAndJSONTableInclude';
		$data = self::FrRestCall($url, null, 'GET');

		if (is_array($data)) return $data;
		return null; // Return null on error
	}

	//get GetFluxPlayerDevices
	public static function get_player_devices($start_date, $end_date) // '2021-05-04';
	{
		$url = FR_REST_URL.'/api2/shop/player_usage.json?created_at_min='.$start_date.'&created_at_max='.$end_date.'&responsetype=EChartAndJSONTableInclude';
		$data = self::FrRestCall($url, null, 'GET');

		if (is_array($data)) return $data;
		return array(); // Return empty array on error
	}

	//get GetFluxPlayerContentUsage
	public static function get_content_use($start_date, $end_date) // '2021-05-04';
	{
		$url = FR_REST_URL.'/api2/shop/flux_player_content_usage.json?created_at_min='.$start_date.'&created_at_max='.$end_date.'&responsetype=EChartAndJSONTableInclude';
		$data = self::FrRestCall($url, null, 'GET');

		if (is_array($data)) return $data;
		return array(); // Return empty array on error
	}

	//get liceses types (rental vs. permanent)
	public static function get_license_types($start_date, $end_date) // '2021-05-04';
	{
		$url = FR_REST_URL.'/api2/shop/license_purchases.json?created_at_min='.$start_date.'&created_at_max='.$end_date.'&responsetype=EChartAndJSONTableInclude';
		$data = self::FrRestCall($url, null, 'GET');

		if (is_array($data)) return $data;
		return array(); // Return empty array on error
	}
	
	// //get content access groups
	// public static function get_access_groups()
	// {
	// 	$url = FR_REST_URL.'/api2/accessgroups.json';
	// 	$groups = self::FrRestCall($url, null, 'GET');

	// 	if (is_array($groups) && array_key_exists('accessgroups', $groups)) 
	// 		return $groups["accessgroups"];
	// 	return array(); // Return empty array on error
	// }

	//get content access group users
	public static function get_access_group_users()
	{
		$url = FR_REST_URL.'/api2/access_group_users.json';
		$group_users = self::FrRestCall($url, null, 'GET');

		if (array_key_exists('group_users', $group_users)) return $group_users["group_users"];
		return array(); // Return empty array on error
	}

	// //add user to content access group
	// public static function add_user_to_access_group( $email, $group_id )
	// {
	// 	$ga = array(	"group_user" => array(	"group_id" => $group_id,
	// 											"email" => $email,
	// 											"valid" => true));

	// 	$url = FR_REST_URL.'/api2/access_group_users.json';
	// 	$result = self::FrRestCall($url, $ga, 'POST');
	// 	return $result;
	// }

	// public static function remove_user_from_access_group( $id )
	// {
	// 	$url = FR_REST_URL.'/api2/access_group_users/'.$id.'.json';
	// 	$result = self::FrRestCall($url, null, 'DELETE');
	// 	return $result;
	// }

	// //create new customer (e.g. for groups)
	// public static function create_customer( $customer )
	// {
	// 	$json = array( 'customer' => $customer );
	// 	$url = FR_REST_URL.'/api2/customers.json';
	// 	$result = Flickrocket::FrRestCall($url, $json, 'POST');
	// 	return $result; 
	// }

	//create product
	public static function create_product( $product_json )
	{
		$url = FR_REST_URL.'/api2/products.json';
		$result = Flickrocket::FrRestCall($url, $product_json, 'POST');
		return $result;
	}

	//get webhooks
	public static function get_webhooks()
	{
		$url = FR_REST_URL.'/api/webhooks.json';
		$result = Flickrocket::FrRestCall($url, null, 'GET');
		if (array_key_exists('webhooks', $result)) return $result['webhooks'];
		if (array_key_exists('webhook', $result)) return $result['webhook'];
		return $result;
	}

	//check project_id valid
	public static function is_project_id_valid($project_id)
	{
		$project_id = strtolower(trim($project_id));
        $result = preg_match('/^[0-9a-f]{4}[-][0-9a-f]{4}[-][0-9a-f]{4}[-][0-9a-f]{4}$/', $project_id);
        if ($result) {
            return true;
        }
        return false;
	}

	//set webhook
	public static function set_webhook($scope, $destination, $valid)
	{
		$url = FR_REST_URL.'/api/webhooks.json';
		$data = array( 'webhook' => array(	'scope' => $scope,
											'destination' => $destination,
											'is_valid' => $valid ));
		$result = Flickrocket::FrRestCall($url, $data, 'POST');
		return $result;
	}

	//delete webhook
	public static function delete_webhook($id)
	{
		$url = FR_REST_URL.'/api/webhooks/'.$id.'.json';
		$result = Flickrocket::FrRestCall($url, null, 'DELETE');
		return $result;
	}

	// Mautic
	public static function mautic($url, $contact)
	{
		$result = array();

		try
		{
			$ch = curl_init();
			curl_setopt($ch, constant('CURLOPT_URL'), FR_REST_URL.$url);

			curl_setopt($ch, constant('CURLOPT_POST'), true);
			$postdata = json_encode($contact);
			curl_setopt($ch, constant('CURLOPT_POSTFIELDS'), $postdata);
			
			$headers = ['Content-Type: application/json'];
			curl_setopt($ch, constant('CURLOPT_HTTPHEADER'), $headers);
			curl_setopt($ch, constant('CURLOPT_RETURNTRANSFER'), true);

			$output = curl_exec($ch);
			$info = curl_getinfo($ch);
			$curl_error = curl_errno($ch);
			curl_close($ch);

			if (is_numeric($info['http_code']) && $info['http_code'] == 200 )
			{
				//$result = json_decode(json_decode($output), true); 
				$result = json_decode($output, true);
			}
		}
		catch (Exception $ex)
		{
		}

		return $result;
	}
	
	public static function log($level, $message)
	{
		try
		{
			$logging = get_option('fr_logging', "0");
			if ($logging == "1" && $level != 'Error')
				return;

			$json = array(
				'file' => date('Y-m-d').'.txt',
				'path' => get_option('fr_company_id', "0"),
				'machinename' => 'WooCommerce',
				'row' => round(microtime(true) * 1000),
				'message' => date(DATE_ISO8601).'|'.$level.'|'.$message,
			);

			$ch = curl_init();
			curl_setopt($ch, constant('CURLOPT_URL'), 'https://logging.flickrocket.com/api/LogMe.json');
			
			curl_setopt($ch, constant('CURLOPT_POST'), true);
			$postdata = json_encode($json);
			curl_setopt($ch, constant('CURLOPT_POSTFIELDS'), $postdata);

			$headers = ['Content-Type: application/json'];
			curl_setopt($ch, constant('CURLOPT_HTTPHEADER'), $headers);
			curl_setopt($ch, constant('CURLOPT_RETURNTRANSFER'), true);

			$output = curl_exec($ch);
			$info = curl_getinfo($ch);
			$curl_error = curl_errno($ch);
			curl_close($ch);
		}
		catch (Exception $ex)
		{
		}
	}
}

?>
