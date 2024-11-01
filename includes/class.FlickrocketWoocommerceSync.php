<?php
//if(!session_id()){ ob_start(); session_start();}

class FlickrocketWoocommerceSync
{
	public static function init()
	{
		// Hooks

		// Called to get Flickrocket products
		add_action( 'admin_post_nopriv_GetFrProducts', array( get_class(), 'GetFrProducts' ), 1, 1 );
		add_action( 'admin_post_GetFrProducts', array( get_class(), 'GetFrProducts' ), 1, 1 );

		// Called to get WC products
		add_action( 'admin_post_nopriv_GetWcProducts', array( get_class(), 'GetWcProducts' ), 1, 1 );
		add_action( 'admin_post_GetWcProducts', array( get_class(), 'GetWcProducts' ), 1, 1 );

		// Called per product syned in the manual sync loop from the admin
		add_action( 'admin_post_nopriv_fr_product_sync', array( get_class(), 'incoming_fr_product_sync' ), 1, 1 );
		add_action( 'admin_post_fr_product_sync', array( get_class(), 'incoming_fr_product_sync' ), 1, 1 );

		// Called from the webhook for each product create/change/delete
		add_action( 'admin_post_nopriv_fr_auto_product_sync', array( get_class(), 'incoming_fr_auto_product_sync' ), 1, 1 );
		add_action( 'admin_post_fr_auto_product_sync', array( get_class(), 'incoming_fr_auto_product_sync' ), 1, 1 );
	}
		

	public static function GetFrProducts( $Page ) 
	{
		// Enumerate all products
		$result = Flickrocket::get_products( $Page );

		$output['frproducts'] = $result['products'];

		status_header(200);
		wp_send_json( $output );
	}

	public static function GetWcProducts( $Page ) 
	{
		// Get Woocommerce products to allow filtering duplicates
		$result['tpproducts'] = null;
		$args = array ( 
			'post_type' => array('product', 'product_variation'),
			'posts_per_page'  => -1,
			'page' => $Page,
			'meta_query' => array( 
				array( 
					'key' => '_flickrocket_project_key_id', 
					'compare' => '='
				), 
				), 
			);
		$meta_query = new WP_Query( $args );
		
		foreach ($meta_query->posts as $index => $wooproduct)
		{
			$value = get_post_meta( $wooproduct->ID, '_flickrocket_project_key_id', true );
			$result['tpproducts'][] = $value;
		}

		status_header(200);
		wp_send_json( $result );
	}

	// Incoming auto product sync from webhook
	public static function incoming_fr_auto_product_sync()
	{
		if (get_option('fr_sync_products', 'no') == 'yes')
		{
			//ToDo: Validate hook

			$hookdata_raw = file_get_contents('php://input');
			$hookdata = json_decode($hookdata_raw, true);
			$product_id = $hookdata["data"]["id"];
			$long_product_id = $hookdata["data"]["long_id"];
			$action = $hookdata["scope"];

			if (strpos($action, 'create') !== false || strpos($action, 'update') !== false)
			{
				Flickrocket::log("Info", "Incoming product sync request: ".$hookdata_raw);
				self::queue_sync_request($product_id, $hookdata_raw);
			}
			else if (strpos($action, 'delete') !== false)
			{
				self::queue_delete_request($long_product_id, $hookdata_raw);
				Flickrocket::log("Info", "Incoming product delete request: ".$hookdata_raw);
			}
		}
		status_header(200);
	}

	// Save sync job to folder
	public static function queue_sync_request($product_id, $hookdata_raw)
	{
		self::ensure_queue_directory();
		$result = file_put_contents(FW_PATH.'/jobs/queued/'.$product_id.'.sync', $hookdata_raw );
	}

	// Save delete job to folder
	public static function queue_delete_request($long_product_id, $hookdata_raw)
	{
		self::ensure_queue_directory();
		$result = file_put_contents(FW_PATH.'/jobs/queued/'.$long_product_id.'.delete', $hookdata_raw );
	}

	// Check folder structure and create folders if it doesn't exist
	public static function ensure_queue_directory()
	{
		if (!file_exists(FW_PATH.'/jobs')) {
			mkdir(FW_PATH.'/jobs', 0777, true);
		}
		if (!file_exists(FW_PATH.'/jobs/queued')) {
			mkdir(FW_PATH.'/jobs/queued', 0777, true);
		}
		if (!file_exists(FW_PATH.'/jobs/done')) {
			mkdir(FW_PATH.'/jobs/done', 0777, true);
		}
	}

	public static function process_product_delete($webhook_json)
	{
		global $wpdb, $woocommerce;

		Flickrocket::log("Info", "Performing product delete request");
		$long_product_id = $webhook_json["data"]["long_id"];

		//Find product
		$args = array ( 
			'post_type' => array('product', 'product_variation'),
			'posts_per_page'  => -1,
			'meta_query' => array( 
				array( 
					'key' => '_flickrocket_project_key_id',
					'value' => $long_product_id,
					'compare' => '='
				), 
				), 
			);
		$meta_query = new WP_Query( $args );
		if (count($meta_query->posts) > 0)
		{
			//Product found, delete it now
			$post_id = $meta_query->posts[0]->ID;
			$success = wp_delete_post($post_id);
		}
	}

	public static function incoming_fr_product_sync()
	{
		//Process post data
		$postdata = stripslashes( $_POST['postdata'] );
		$product = json_decode($postdata, true);

		$result = self::perform_product_sync($product, array(), array());

		status_header(200);
		wp_send_json( $result );
	}

	public static function process_product_sync($webhook_json, $licensecache, $pricecache)
	{
		$product = Flickrocket::get_product($webhook_json["data"]["id"]);
		if ( array_key_exists('error', $product) ) return false;
		
		$result = self::perform_product_sync($product["product"], $licensecache, $pricecache);
	}

	public static function find_price($all_prices, $price_id)
	{
		foreach ($all_prices["prices"] as $price)
		{
			if ($price["id"] == $price_id) return $price;
		}

		Flickrocket::log("Error", "Price not found in list of prices: ".$price_id);
	}


	public static function find_license($all_licenses, $license_id)
	{
		foreach ($all_licenses["licenses"] as $license)
		{
			if ($license["id"] == $license_id) return $license;
		}

		Flickrocket::log("Error", "License not found in list of licenses: ".$license_id);
	}

	public static function get_localized_license_name($license)
	{
		// $result = $license['license']['name']; // Default name
		$result = $license['name']; // Default name

		// Handle localizations
		$language_id = 1;
		$locale = strtolower(get_locale());

		if (strpos($locale, 'de') !== false) 
			$language_id = 2;
		else if (strpos($locale, 'fr') !== false) 
			$language_id = 12;
		else if (strpos($locale, 'it') !== false) 
			$language_id = 10;
		else if (strpos($locale, 'es') !== false) 
			$language_id = 8;
		else if (strpos($locale, 'pt') !== false) 
			$language_id = 6;

		// foreach($license['license']['locales'] as $liclocale)
		foreach($license['locales'] as $liclocale)
		{
			if ($language_id == $liclocale['language_id'])
			{
				// Found correct locale
				$result = $liclocale['name'];
				break;
			}
		}
		return $result;
	}

	public static function perform_product_sync ($product, $licensecache, $pricecache)
	{
		global $wpdb, $woocommerce;

		Flickrocket::log("Info", "Performing product sync request with locale: ".get_locale());

		try
		{
		// Allow only meaningful products
		$pt = $product["product_type"];
		if (($pt != 1 && $pt != 4 && $pt != 5 && $pt != 7 && $pt != 8 && $pt != 9 && $pt != 16 && $pt != 26 && $pt != 27 && $pt != 29 && $pt != 30) || $product["valid"] == false) return;

		$is_update = false;
		
		//Check if product already exists
		$args = array ( 
			'post_type' => array('product', 'product_variation'),
			'post_status' => array('publish', 'draft'),
			'posts_per_page'  => -1,
			'meta_query' => array( 
				array( 
					'key' => '_flickrocket_project_key_id',
					'value' => $product['product_id'],
					'compare' => '='
				), 
				), 
			);
		$meta_query = new WP_Query( $args );
		if (count($meta_query->posts) > 0)
		{
			$is_update = true;
			$original_post = $meta_query->posts[0];
			$post_id = $meta_query->posts[0]->ID;
		}

		// Check if product is released
		$released = false;
		$publish_date_string = $product["published_at"];
		$publish_date = strtotime($publish_date_string);
		if ($publish_date > strtotime('2001-01-01'))
		{
			$released = true;
		}

		// Check if content files have been uploaded/is processed and get preview
		$content_available = false;
		$preview = "";
		if ( count($product["content_files"]) > 0 )
		{
			foreach ($product["content_files"] as $file)
			{
				if ($file["valid"] == 1)
				{
					if ($file["content_type"] == 2 || $file["content_type"] == 7 || $file["content_type"] == 10)
					{
						// Trailer/Preview
						$preview = $file['download_url'];
					}
					else
					{
						// Real content 
						$content_available = true;
						if ($preview != '') break; //break early if preview already found
					}
				}
			} 
		} 

		$fr_activate_products = 'no';
		if ($released && $content_available) $fr_activate_products = get_option('fr_activate_products', 'no');
		$auto_activate = $fr_activate_products == 'yes' ? 'publish' : 'draft';
		
		if ( !array_key_exists(0, $product["locales"] )) 
		{
			$description = '';
		}
		else
		{
			$description = $product["locales"][0]["description"];
		}

		if (!$is_update)
		{
			Flickrocket::log("Debug", "New product");

			//Create product
			$post_id = wp_insert_post(array(
				'post_title' => $product["title"],
				'post_content' => $description,
				'post_status' => 'draft', //Always create as draft
				'post_type' => "product"));

			//Product ID
			$product_id = $product["product_id"];
			update_post_meta( $post_id, '_flickrocket_project_key_id', $product_id );

			Flickrocket::log("Info", "perform_product_sync: ".$product_id." | ".$post_id);
		}
		if ( $preview != '' ) update_post_meta( $post_id, '_flickrocket_preview', $preview );

		if (count($product["price_license_binding"]) == 1)
		{
			Flickrocket::log("Debug", "Simple product");

			//Simple product (one license/price) ===========================================================================
			wp_set_object_terms( $post_id, 'simple', 'product_type' ); 

			//License
			$license_id = $product["price_license_binding"][0]["license_id"];
			if (empty($license_id))
			{
				$license_id = 0; // If no license given, default to 0
				Flickrocket::log("Debug", "Simple product | No license specified");
			}

			if (!array_key_exists($license_id, $licensecache))
			{
				//Get license from server
				$result = Flickrocket::get_license($license_id);
				$licensecache[$license_id] = self::get_localized_license_name($result);
			}
			update_post_meta( $post_id, '_product_license_id', $license_id );

			Flickrocket::log("Debug", "Simple product | License retrieved");

			//Quality (video/non DRM audio only)
			$quality = '';
			if ($product["product_type"] == 9)
			{
				if (array_key_exists('hd', $product["price_license_binding"][0]))
				{
					$format = $product["price_license_binding"][0]["hd"];
					$quality = $format == true ? 0 : 1;
				}
			}
			else if ($product["product_type"] == 23 || $product["product_type"] == 24) // Audio album or Audio Track
			{
				if (array_key_exists('audio_type', $product["price_license_binding"][0]))
				{
					$format = $product["price_license_binding"][0]["audio_type"];
					$quality = $format == 0 ? 2 : 3; // 2 = MP3 | 3 = FLAC
				}
			}
			update_post_meta( $post_id, '_variation_quality', $quality ); // 0 = HD / 1 = SD / 2 = MP3 / 3 = FLAC

			//Multi Users
			$num_users = 1;
			if (array_key_exists('num_users', $product["price_license_binding"][0]))
			{
				$num_users = $product["price_license_binding"][0]["num_users"];
			}
			update_post_meta( $post_id, '_num_users', $num_users ); 

			//Price
			$price_id = $product["price_license_binding"][0]["price_id"];
			if (!array_key_exists($price_id, $pricecache))
			{
				//Get license from server
				$result = Flickrocket::get_price($price_id);
				if (!empty($result) && array_key_exists('price', $result) && array_key_exists('currencies', $result['price']))
				{
					$usd = null;
					$eur = null;
					$found = false;
					foreach ($result['price']['currencies'] as $currency)
					{
						$woo_currency = strtolower(get_option('woocommerce_currency'));
						if ($currency["currency"] == $woo_currency)
						{
							$pricecache[$price_id] = $currency["price"] / 100;
							update_post_meta( $post_id, '_regular_price', $pricecache[$price_id] );
							update_post_meta( $post_id, '_price', $pricecache[$price_id] );
							$found = true;
							break;
						}
						else if ($currency["currency"] == "eur")
						{
							$eur = $currency["price"] / 100;
						}
						else if ($currency["currency"] == "usd")
						{
							$usd = $currency["price"] / 100;
						}
					}
					if (!$found)
					{
						//Correct currency not found
						$rates = Flickrocket::get_currency_rate( get_option('woocommerce_currency') );
						if ($usd != null)
						{
							if (!empty($rates)) $pricecache[$price_id] = round($rates['usd'] * $usd,2); // Use currency exchange rate based on usd
							else $pricecache[$price_id] = $usd;
							
							update_post_meta( $post_id, '_regular_price', $pricecache[$price_id] );
							update_post_meta( $post_id, '_price', $pricecache[$price_id] );
						}
						else if ($eur != null)
						{
							if (!empty($rates)) $pricecache[$price_id] = round($rates['eur'] * $eur,2); // Use currency exchange rate based on usd
							else $pricecache[$price_id] = $eur;

							update_post_meta( $post_id, '_regular_price', $pricecache[$price_id] );
							update_post_meta( $post_id, '_price', $pricecache[$price_id] );
						}

						// Price in correct currency was not found, set marker to allow changes in UI for mirrored products
						update_post_meta( $post_id, '_fr_price_sync_ok', false );
					}
					else
					{
						// Price in correct currency was found and set, set marker to disable changes in UI for mirrored products
						update_post_meta( $post_id, '_fr_price_sync_ok', true );
					}
				}
				Flickrocket::log("Debug", "Simple product | Price handled");
			}
			else
			{
				update_post_meta( $post_id, '_regular_price', $pricecache[$price_id] );
			}

			// Update product post
			if ($is_update)
			{
				$original_post->post_title = $product["title"];
				$original_post->post_content = $description;
			}
			else
			{
				$original_post = get_post($post_id);
				
			}
			if ($auto_activate =='publish') $original_post->post_status = $auto_activate;
			$result =  wp_update_post($original_post);
		}
		else if (count($product["price_license_binding"]) > 1)
		{
			Flickrocket::log("Debug", "Variable product");

			//Variable product (multiple licenses/prices) ======================================================================
			wp_set_object_terms($post_id, 'variable', 'product_type');

			$licenseNames = array();
			$licenseIds = array();
			$qualities = array();
			$num_users_array = array();

			//Delete existing variations and attributes if update
			if ($is_update)
			{
				$wpdb->query($wpdb->prepare ("UPDATE wp_posts   
					SET post_status = 'trash'   
					WHERE   
						post_type = 'product_variation' AND   
						post_status = 'publish' AND   
						post_parent = %s", $post_id ));
				
				$result = delete_post_meta($post_id, '_product_attributes');
			}

			// Get all licenses for company
			$all_licenses = Flickrocket::get_licenses();

			Flickrocket::log("Debug", "Variable product | Licenses retrieved");

			//Licenses
			foreach ($product["price_license_binding"] as $licbind)
			{
				$license_id = $licbind["license_id"];
				if (!empty($license_id))
				{
					//if ($licensecache[$license_id] == null)
					if (!array_key_exists($license_id, $licensecache))
					{
						// $result = Flickrocket::get_license($license_id);
						$result = self::find_license($all_licenses, $license_id);

						$licensecache[$license_id] = self::get_localized_license_name($result);
					}

					//Got existing licenses
					$licenseExt = '';
					$quality = '';
					
					if ($product["product_type"] == 9) // Video (HD)
					{
						if (array_key_exists('hd', $licbind))
						{
							if ($licbind['hd'] == true)
							{
								$licenseExt = ' : HD';
								$quality = 0;
							}
							else 
							{
								$licenseExt = ' : SD';
								$quality = 1;
							}
						}
					}
					else if ($product["product_type"] == 23 || $product["product_type"] == 24) // Audio album or Audio Track
					{
						if (array_key_exists('audio_type', $licbind))
						{
							if ($licbind['audio_type'] == 0 )
							{
								$licenseExt = ' : MP3';
								$quality = 2;
							}
							else if ($licbind['audio_type'] == 1 )
							{
								$licenseExt = ' : FLAC';
								$quality = 3;
							}
							
						}
					}

					//Multi users
					$num_users = 1;
					if (array_key_exists('num_users', $licbind))
					{
						$num_users = $licbind['num_users'];
						$licenseExt .= ' : '.$num_users.' users';
					}

					// Store for later use
					$licenseNames[] = $licensecache[$license_id].$licenseExt;
					$licenseIds[] = $license_id;
					$qualities[] = $quality;
					$num_users_array[] = $num_users;
				}
			}

			//Price
			$prices = array();
			$price_found = array();
			$rates = Flickrocket::get_currency_rate( get_option('woocommerce_currency') );

			// Get all prices for company
			$all_prices = Flickrocket::get_prices();

			Flickrocket::log("Debug", "Variable product | Got currency rate and prices");

			foreach ($product["price_license_binding"] as $pricebind)
			{
				$price_id = $pricebind["price_id"];
				if (!array_key_exists($price_id, $pricecache))
				{
					
					// $result = Flickrocket::get_price($price_id);
					$result = self::find_price($all_prices, $price_id);

					if (!empty($result))
					{
						$usd = null;
						$eur = null;
						$found = false;
						// foreach ($result['price']['currencies'] as $currency)
						foreach ($result['currencies'] as $currency)
						{
							$woo_currency = strtolower(get_option('woocommerce_currency'));
							if ($currency["currency"] == $woo_currency)
							{
								$pricecache[$price_id] = $currency["price"] / 100;
								$prices[] = $pricecache[$price_id];
								$found = true;
								break;
							}
							else if ($currency["currency"] == "eur")
							{
								$eur = $currency["price"] / 100;
							}
							else if ($currency["currency"] == "usd")
							{
								$usd = $currency["price"] / 100;
							}
						}
						if (!$found)
						{
							//Correct currency not found
							if ($usd != null)
							{
								if (!empty($rates)) $pricecache[$price_id] = round($rates['usd'] * $usd,2); // Use currency exchange rate based on usd
								else $pricecache[$price_id] = $usd;

								$prices[] = $pricecache[$price_id];
							}
							else if ($eur != null)
							{
								if (!empty($rates)) $pricecache[$price_id] = round($rates['eur'] * $eur,2); // Use currency exchange rate based on usd
								else $pricecache[$price_id] = $eur;

								$prices[] = $pricecache[$price_id];
							}
							$price_found[] = false;
						}
						else
						{
							$price_found[] = false;
						}
					}
				}
				else
				{
					//Use price from cache
					$prices[] = $pricecache[$price_id];
					$price_found[] = true;
				}
			}

			Flickrocket::log("Debug", "Variable product | Before create attributes and variations");

			//Create Attributes and variations
			$result = wp_set_object_terms($post_id, implode('|', $licenseNames), 'license');
			
			$product_attributes = array();
			$product_attributes['license'] = array(
				'name' => 'License',
				'value' => implode('|', $licenseNames),
				'position' => 0,
				'is_visible' => 0,
				'is_variation' => 1,
				'is_taxonomy' => 0
			);
			$result = update_post_meta($post_id, '_product_attributes', $product_attributes);

			foreach ($licenseNames as $index => $license) {
				
				//Quality (video only)
				$quality = $qualities[$index];
				$num_users = $num_users_array[$index];

				//Build variation post
				$licenseClean = preg_replace("/[^0-9a-zA-Z_-] +/", "", $license);
				$post_name = 'p-'.$post_id.'-l-'.$licenseIds[$index].'-q-'.$quality.'-u-'.$num_users; // 0 = HD / 1 = SD / 2 = MP3 / 3 = FLAC

				$my_post = array(
					'post_title' => 'License ' . $license . ' for #' . $post_id,
					'post_name' => $post_name,
					'post_status' => 'publish',
					'post_parent' => $post_id,
					'post_type' => 'product_variation',
					'guid' => home_url() . '/?product_variation=' . $post_name
				);
				$attID = $wpdb->get_var("SELECT count(post_title) FROM $wpdb->posts WHERE post_name like '".$post_name."'");
				if ($attID < 1) {
					$attID = wp_insert_post($my_post);
				}
				update_post_meta($attID, 'attribute_license', $license);
				update_post_meta($attID, '_price', $prices[$index]);
				update_post_meta($attID, '_regular_price', $prices[$index]);
				update_post_meta($attID, '_sku', $post_name);
				update_post_meta($attID, '_virtual', 'yes');
				update_post_meta($attID, '_downloadable', 'no');
				update_post_meta($attID, '_manage_stock', 'no');
				update_post_meta($attID, '_stock_status', 'instock');
				update_post_meta($attID, '_stock', 999999);

				update_post_meta( $attID, '_variations_license_id', $licenseIds[$index] );
				update_post_meta( $attID, '_variation_quality', $quality ); // 0 = HD / 1 = SD / 2 = MP3 / 3 = FLAC
				update_post_meta( $attID, '_num_users', $num_users ); 

				$mirrored = $product["mirrored"] != true ? 'no' : 'yes';
				update_post_meta( $attID, '_fr_mirrored', $mirrored );

				if ($price_found[$index] == true)
				{
					// Price in correct currency was found and set, set marker to disable changes in UI for mirrored products
					update_post_meta( $attID, '_fr_price_sync_ok', true );
				}
				else
				{
					// Price in correct currency was not found, set marker to allow changes in UI for mirrored products
					update_post_meta( $attID, '_fr_price_sync_ok', false );
				}
			}

			// Update product post
			
			Flickrocket::log("Debug", "Variable product | Before update product post");

			if ($is_update)
			{
				$original_post->post_title = $product["title"];
				$original_post->post_content = $description;
			}
			else
			{
				$original_post = get_post($post_id);
				
			}
			if ($auto_activate =='publish') $original_post->post_status = $auto_activate;

			$result =  wp_update_post($original_post);

		} // ====================================================================================================================

		//Generic settings
		update_post_meta( $post_id, '_flickrocket', 'yes' );
		update_post_meta( $post_id, '_virtual', 'yes' );
		update_post_meta( $post_id, '_fr_mirrored', $product["mirrored"] != true ? 'no' : 'yes' );
		update_post_meta( $post_id, '_sku', $product["version"]);

		//Handle categories

		Flickrocket::log("Debug", "Before handle categories");

		if (count($product["categories"]) > 0 )
		{
			//Get FR categories
			$fr_categories = Flickrocket::get_categories();
			if (!array_key_exists('error', $fr_categories) && count($fr_categories) > 0)
			{
				//Loop through all categories of product
				foreach ($product["categories"] as $categoryOfProduct)
				{
					//Loop through all categories of shop
					foreach ($fr_categories as $shopCategory)
					{
						if ($categoryOfProduct['category_id'] == $shopCategory['id'])
						{
							//Category found - check if exists within 3rd party shop
							$wooCatId = get_cat_ID( $shopCategory['title'] );
							if ($wooCatId == 0)
							{
								//Category doesn't exist, create now
								$result = $newCat = wp_insert_term(
									$shopCategory['title'], 
									'product_cat', // the taxonomy
									array(
									  'description'=> $shopCategory['title'],
									  'slug' => 'new-category'	));
								if (!is_wp_error($result)) $wooCatId = $newCat['term_id'];
							}
							//Assign category
							if ($wooCatId != 0) wp_set_object_terms( $post_id, $wooCatId, 'product_cat' );
							break;
						}
					}
				}
			}
		}

		//add image

		Flickrocket::log("Debug", "Before handle image");

		if (array_key_exists(0, $product["locales"]) && array_key_exists('src', $product["locales"][0]) && $product["locales"][0]["src"] != '')
		{
			$result = self::upload_image_to_product( $post_id, $product["locales"][0]["src"], str_replace('/', '_', $product["title"]).'_'.$product["product_id"] );
		}

		Flickrocket::log("Debug", "Product sync success");
	}
	catch(Exception $ex)
	{
		Flickrocket::log("Error", "perform_product_sync | Message: ".$ex->getMessage()." | Trace: ".$ex->getTraceAsString());
	}

		return '{ "status": "OK" }';
	}

	public static function upload_image_to_product($post_id, $url, $filename) {

		global $wpdb;
		include(ABSPATH . "wp-admin/includes/admin.php");
		
		try {

			if ($url != "") 
			{
				$file = array();
				$file['name'] = $filename.'.png';
				$file['tmp_name'] = download_url($url);
			
				if (is_wp_error($file['tmp_name'])) {
					@unlink($file['tmp_name']);
					var_dump( $file['tmp_name']->get_error_messages( ) );
				} else {
					$attach_id = media_handle_sideload($file, $post_id);
					if ($attach_id != 0)
					{
						$result = set_post_thumbnail( $post_id, $attach_id );
					} 
				}
			}
		}
		catch (Exception $ex)
		{
		}
		return true;
	}

	public static function get_product_type($product_type)
	{
		if ($product_type == 1) return "Video (SD)";
		if ($product_type == 3) return "Website access";
		if ($product_type == 4) return "Software | Generic";
		if ($product_type == 5) return "Audio Collection";
		if ($product_type == 6) return "Physical Product";
		if ($product_type == 7) return "PDF";
		if ($product_type == 8) return "Product Collection";
		if ($product_type == 9) return "Video (HD)";
		if ($product_type == 16) return "ePub";
		if ($product_type == 17) return "Service";
		if ($product_type == 18) return "App";
		if ($product_type == 19) return "Theme";
		if ($product_type == 20) return "Certificate";
		if ($product_type == 21) return "Variation";
		if ($product_type == 22) return "Video (VR)";
		if ($product_type == 23) return "Audio Track";
		if ($product_type == 24) return "Audio Album";
		if ($product_type == 26) return "Access Group";
		if ($product_type == 27) return "Generic File";
		if ($product_type == 28) return "Template";
		if ($product_type == 29) return "HTML Package";
		if ($product_type == 30) return "SCORM Package";
		return 'Unknown';
	}

}	

?>
