=== Plugin Name ===
Contributors: FlickRocket
Donate link: https://flickrocket.com/
Tags: video, audio, ebook, DRM, content, digital rights management, apps, iOS, Android, Windows, MacOSX, Kindle, SmartTV
Requires at least: 3.0.1
Tested up to: 6.5.4
Stable tag: trunk
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sales and rentals of (optionally DRM protected) digital content such as video (HD+SD), DVD, audio books, ebooks (epub and PDF) and packaged content

== Description ==

This extension enables you to sell and rent (optionally DRM protected) digital content such as DVDs (incl. all menus, bonus material, etc.), video (HD+SD), audio books, ebooks (epub and PDF) and packaged content such as HTML, Flash, images, etc.

**Windows, MacOSX, iOS, Android, Kindle and SmartTV**

Customers can consume the content on virtually all platforms and access the content also on multiple devices. While doing so the content is transparently end-to-end protected and you can freely define usage time frames, device limitations, regional limitations, and much more.

**Everything included for getting started**

You can encode, package, encrypt and upload your content right within WooCommerce/Wordpress or use desktop software (free download for Windows and Mac) to get your content ready for sale. The content distribution is done via our content delivery network (CDN) to ensure a high bandwidth distribution to a world wide audience.

**More features for content stores**

This extension includes not only the backend features to set up your content but also shop frontend features so you can present trailers and about guiding people after the checkout. It also includes a digital locker for customer to log in and access their purchased content at a later time.

**Easy to use DRM control**

You don't need to be an expert in DRM to get your content sales up can running. DRM is applied automatically during upload and you can select from various pre-defined licenses. You can even offer customers the option to select between licenses (e.g. download-to-own or rental) at different prices.

**Digital Content Marketplace**

In addition to your own content, use the Digital Content Marketplace to resell content from others or offer your content for 3rd parties to sell and earn royalties from each sale.

**More Information**

For more information about FlickRocket see [www.flickrocket.com](https://www.flickrocket.com/ "The best e-commerce platform for content").
For more information about the plug-in see [www.flickrocket.com/en/woocommerce-extension](https://www.flickrocket.com/en/woocommerce-extension/ "FlickRocket Guide to WooCommerce Plug-In")
For a video about how the plugin is obtained, installed, configured and used, see [youtu.be/946I_6BPx0c](https://youtu.be/946I_6BPx0c "WooCommerce/Wordpress digital content sales with DRM using FlickRocket")

== Installation ==

1. Make sure Wordpress and WooCommerce are installed
2. Go to Plug-ins -> New Plug-in and install the plug-in
3. Activate the plug-in

== Frequently Asked Questions ==

= Can the plug-in work just with Wordpress but without WooCommerce? =

No, WooCommerce is required.

= Do I need a FlickRocket account to use the plug-in? =

You can test the plug-in by using the pre-installed Sandbox account. However, for productive use you need to sign up to FlickRocket. The free BASIC account is enough to use the plug-in.

= Is there any cost for using the plug-in =

The plug-in is provided free. If you want to use it, you need to sign up to a FlickRocket account. If you go with the free BASIC account there is no cost. There is a cost for the PREMIUM accouts (ask us for details).

= Which kind of content is supported by FlickRocket? =

Unfortunately the space here is not enough to list all kinds of supported content. Some examples are available on the [FlickRocket information page](https://www.flickrocket.com/en/information-en/ "FlickRocket information"). If you are unsure, just contact us.

= Can the content be consumed on every platform? =

At time of this writing we support Windows, MacOSX, iOS, Android, Kindle, SmartTV and more.

== Screenshots ==

1. Shop view in sample theme
2. Product details view with license selection (optional)
3. Button for Digital Cntent access after purchase complete
4. Customizable player installation page (active OS on top)
5. iPad content overview - tile mode (landscape)
6. iPad content overview - list mode (landscape)
7. iPad during video play (landscape)
8. iPad during video play with DVD navigation (landscape)
9. iPhone content overview - tile mode (portrait)
10. iPhone content overview - list mode (portrait)

== Changelog ==

= 4.74
* Use of new analytics API

= 4.73
* Cleanup 
* Fix for bringing "My Content" back to "My Account" menu 

= 4.70
* Removed role/group functionality
* Fixed product creation in Flickrocket from WooCommerce product page in case no image was uplaoded

= 4.69
* Added credential information to "new order" email for shop owner

= 4.68
* Check variable that might be null in case of error not to cause exception
* Added code remption link to "My Content" for cutomers without digital content

= 4.67
* Fix handling external unlock code links for users with WooCommerce account but no digital products yet

= 4.66
* Fix for better handling of uninitialized license associations for physical products

= 4.65
* Added support for HTML package product sync
* Added support for SCORM product sync
* Added support for generic file product sync

= 4.64
* More logging

= 4.63
* Added setting for logging
* Added option to force code creation instead of applying order directly into customer account, even for single product/single user orders
* Added "Digital delivery powered by Flickrocket" link

= 4.62
* More logging

= 4.61
* Bugfix

= 4.60
* Bugfix

= 4.59
* Performance optimizations

= 4.58
* Added new license option "No digital" for physical product variants

= 4.57
* Added more logging

= 4.56
* Added support for access group products
* Added more logging
* Minor fixes

= 4.54
* Fix for "My Account / My Content"

= 4.53
* Fix for multi-user orders

= 4.50
* Fix for multi-user orders

= 4.49
* Added transmit of WooComerce specific unlock URL to MyContentJs for rendering invite templates for unlock code invites

= 4.48
* Added anonymous "My Content" section for code redemption to /my-account page when opened with URL parameter "fr_unlock=1"

= 4.47
* Added uppercase for Flickrocket project_id in order (no functional difference)

= 4.46
* Added validation for Flickrocket project_id field

= 4.45
* Added new signup
* Added Flickrocket product creation and upload 

= 4.44
* Product sync speed optimizations for products with many licenses and many prices

= 4.43
* Fixes for multi-user sync and save

= 4.42
* Added more logging for sync

= 4.41
* Added portuguese

= 4.40
* Fix for critical error

= 4.39
* Fix for critical error

= 4.38
* Added support for Single Sign On
* Added support for multi user licenses
* Added japanese language to MyContentJs

= 4.37
* Added french and greek language to MyContentJs

= 4.36
* Replaced file_get_contents with CURL to avoid security blocking of file_get_contents

= 4.35
* Added error logging for being unable to retrieve client urls for 'MyContentJs'

= 4.34
* Test

= 4.33
* Updated "tested to" Wordpress
* Removed redundant session_start
* Added manin menu entry in live mode

= 4.32
* Prevented caching on MyContentJS Updates
* Added Rental vs. permanent statistics

= 4.31
* Fix to load vendor.js only in debug

= 4.30
* Swiched to MyContentJS to content access
* Removed warnings about themes and support
* Removes theme settings and usage 
* Added fault tolerance for issuing Oauth warning
* Added statistics
* Bundled Flickrocket pages in new top level menu

= 4.23
* Fix for sending out content access emails for variations

= 4.22
* Removed directDB access for postmeta data

= 4.21
* Addeed "fr_modify_order_email_instructions" filter for allowing changes to insertion text into order emails

= 4.20
* OAuth changes

= 4.19
* Mautic fix

= 4.18
* Added mautic

= 4.17
* Fix for sending email for simple products

= 4.16
* Fix for sending email in mixed digital/physical variation products

= 4.15
* Logging now by FR company
* Added HTML package support

= 4.14
* Added more product types
* Added sync for collections

= 4.13
* Changed option to non autoload

= 4.12
* Changes for new 3rd party theme witch defaults to "My content" page

= 4.11
* Fixed license language issue
* Fixed theme misdetection

= 4.10
* Added access group functionality to WP users (users can be manually assigned to groups by admins)

= 4.9
* Flush cache on token exchange complete
* More logging

= 4.8
* Better customer notification on order processing error
* Added function to get Flickrocket specific order data (e.g. for email customization)
* Extending sales emails with digital content access information now optional

= 4.7
* Added separate Oauth logging for multiple instances on the same host

= 4.6
* Additional logging and notifications for different OAuth scenarios

= 4.5
* Additional OAuth token exchange precautions

= 4.4
* OAuth notifications now include state data

= 4.3
* Fix OAuth notifications only on valid company data
* Fix attempt for rare double orders

= 4.2
* Added preparations for server based loast oauth sync detection/notifcation

= 4.1
* Fix to avoid rare timing related double orders

= 4.0
* Added content marketplace
* Removed potentially offending words from random password generator
* Better logging

= 3.18
* Prepared for localization

= 3.17
* Fixed OAuth token reathorize problem

= 3.16
* Fixed problem interrupting execution when getting FR product list to display for WC products (happens for some PHP configurations)

= 3.15
* Fixed issue where Flickrocket options were not displayed for variable products in backend, unless "Flickrocket" checkbox was used
* Added retry on backend timeouts or temporary maintenance

= 3.14
* Fixed problem when Jquery-UI wasn't loaded
* Added code orders for oders with > 1 quantity of digital products

= 3.13
* Fixed potential settings loss problem
* Removed separate digital content access email and added information in standard emails instead
* Prepared code orders

= 3.12
* Fix for saving settings

= 3.11
* Changes for using new dev environment
* Settings no show theme name
* Fix for problem with syncing when no WooCommerce products are set up
* Fix for encrypted media streaming policy change with iframes
* Using original "Save" button in settings

= 3.10
* Fix for callback error when gtting data for player frame

= 3.9
* Fix for paged FR product fetch

= 3.8
* Fix for Flickrocket checkbox becoming auto-enabled for simple products
* Code cleanup

= 3.7
* Fix for making sure orders with invalid ("0") licenses (e.g. physical) are not sent to Flickrocket for processing

= 3.6
* Product loading for sync now paged to avoid timeouts in shops with many products

= 3.5
* Fix for mixed physical/digital variable products causing order with invalid license 

= 3.4
* Detection for missing cURL

= 3.3
* Fix for sync progress going to 100%

= 3.2
* Added category sync
* Added removal of webhooks on uninstall

= 3.1
* Minor fixes, cleanup

= 3.0
* Changed account based SOAP to token based REST interface
* Authentication now via OAuth / no more storage of user credentials
* Content access account now separate from shop account although starts with identical email.
* Content access one time credentials displayed after purchase and sent via email
* Optional automatic sync of products from Flickrocket to Woocommerce
* Option for manual selective sync of products from Flickrocket to WooCommerce

= 2.19
* Fixed SD flag mixup for variable HD orders
* Made admin.js only load in backend

= 2.17
* Removed dependency on sandbox server for live operation

= 2.16
* Removed Fr ProductID from Coupons

= 2.15
* Minor cleanup

= 2.14
* Modified order status handling

= 2.13
* Fix for variable product selection 
* fix for saving variable product settings

= 2.12
* Added SD option for HD products

= 2.11
* Added separate email for digital orders

= 2.10
* Cleanup

= 2.09
* New check for php-soap being installed

= 2.08
* Fixed crash issue related to missing php-soap

= 2.07
* Fixed several issues with PayPal payments
* Code cleanup

= 2.0
* Fixed dynamic login URLs based on company
* Removed "Check" button in settings - no longer required
* Extended fields on settings page so no values are visually “cut off”
* Added Sync Secret to Settings
* Added optional email/password change sync adapter (URL is http(s)://<domain>/wp-content/plugins/woocommerce-digital-content-delivery-with-drm-flickrocket/flickrocket_sync.php
* "My Content" no accessed via iframe instead of button
* Fixed sending orders only for FlickRocket products
* Added "My Content" iframe on after order page
* Fixed protocol issues (https vs. https) with "My Content" integration
* Fixed issue with special characters in password
* Added help information specific to sandbox usage
* Fixed "My orders" handling in certain scenarios 
* Extended settings with sync information
* Requires now only account if FlickRocket product is in carts
* Upload wizard displayed only in live mode (can't be using in sandbox)
* Various wording changes
* Code cleanup

= 1.9
* Minor fix

= 1.8
* Minor fix

= 1.7
* Fix for mixed physical/digital shopping carts

= 1.6
* Fixed for order status issue with non-digital products

= 1.5
* Replaced "Content Access" text and botton with player access iframe 

= 1.4
* Minor bug fix in domain handling

= 1.3
* Minor bug fix for accounts without projects/themes

= 1.2 =
* UI cleanup

= 1.1 =
* UI cleanup
* Theme selection as drop down
* Reset password functionality added

= 1.0 =
* Public release version

== Upgrade Notice ==

= 4.74
* Use of new analytics API

= 4.73
* Cleanup 
* Fix for bringing "My Content" back to "My Account" menu 

= 4.70
* Removed role/group functionality
* Fixed product creation in Flickrocket from WooCommerce product page in case no image was uplaoded

= 4.69
* Added credential information to "new order" email for shop owner

= 4.68
* Check variable that might be null in case of error not to cause exception
* Added code remption link to "My Content" for cutomers without digital content

= 4.67
* Fix handling external unlock code links for users with WooCommerce account but no digital products yet

= 4.66
* Fix for better handling of uninitialized license associations for physical products

= 4.65
* Added support for HTML package product sync
* Added support for SCORM product sync
* Added support for generic file product sync

= 4.64
* More logging

= 4.63
* Added setting for logging
* Added option to force code creation instead of applying order directly into customer account, even for single product/single user orders
* Added "Digital delivery powered by Flickrocket" link

= 4.62
* More logging

= 4.61
* Bugfix

= 4.60
* Bugfix

= 4.59
* Performance optimizations

= 4.58
* Added new license option "No digital" for physical product variants

= 4.57
* Added more logging

= 4.56
* Added support for access group products
* Added more logging
* Minor fixes

= 4.54
* Fix for "My Account / My Content"

= 4.53
* Fix for multi-user orders

= 4.50
* Fix for multi-user orders

= 4.49
* Added transmit of WooComerce specific unlock URL to MyContentJs for rendering invite templates for unlock code invites

= 4.48
* Added anonymous "My Content" section for code redemption to /my-account page when opened with URL parameter "fr_unlock=1"

= 4.47
* Added uppercase for Flickrocket project_id in order (no functional difference)

= 4.46
* Added validation for Flickrocket project_id field

= 4.45
* Added new signup
* Added Flickrocket product creation and upload 

= 4.44
* Product sync speed optimizations for products with many licenses and many prices

= 4.43
* Fixes for multi-user sync and save

= 4.42
* Added more logging for sync

= 4.41
* Added portuguese

= 4.40
* Fix for critical error

= 4.39
* Fix for critical error

= 4.38
* Added support for Single Sign On
* Added support for multi user licenses

= 4.37
* Added french and greek language to MyContentJs

= 4.36
* Replaced file_get_contents with CURL to avoid security blocking of file_get_contents

= 4.35
* Added error logging for being unable to retrieve client urls for 'MyContentJs'

= 4.34
* Test

= 4.33
* Updated "tested to" Wordpress
* Removed redundant session_start
* Added manin menu entry in live mode

= 4.32
* Prevented caching on MyContentJS Updates
* Added rental vs. permanent statistics

= 4.31
* Fix to load vendor.js only in debug

= 4.30
* Swiched to MyContentJS to content access
* Removed warnings about themes and support
* Removes theme settings and usage 
* Added fault tolerance for issuing Oauth warning
* Added statistics
* Bundled Flickrocket pages in new top level menu

= 4.23
* Fix for sending out content access emails for variations

= 4.22
* Removed directDB access for postmeta data

= 4.21
* Addeed "fr_modify_order_email_instructions" filter for allowing changes to insertion text into order emails

= 4.20
* OAuth changes

= 4.19
* Mautic fix

= 4.18
* Added mautic

= 4.17
* Fix for sending email for simple products

= 4.16
* Fix for sending email in mixed digital/physical variation products

= 4.15
* Logging now by FR company
* Added HTML package support

= 4.14
* Added more product types
* Added sync for collections

= 4.13
* Changed option to non autoload

= 4.12
* Changes for new 3rd party theme witch defaults to "My content" page

= 4.11
* Fixed license language issue
* Fixed theme misdetection

= 4.10
* Added access group functionality to WP users (users can be manually assigned to groups by admins)

= 4.9
* Flush cache on token exchange complete
* More logging

= 4.8
* Better customer notification on order processing error
* Added function to get Flickrocket specific order data (e.g. for email customization)
* Extending sales emails with digital content access information now optional

= 4.7
* Added separate Oauth logging for multiple instances on the same host

= 4.6
* Additional logging and notifications for different OAuth scenarios

= 4.5
* Additional OAuth token exchange precautions

= 4.4
* OAuth notifications now include state data

= 4.3
* Fix OAuth notifications only on valid company data
* Fix attempt for rare double orders

= 4.2
* Added preparations for server based loast oauth sync detection/notifcation

= 4.1
* Fix to avoid rare timing related double orders

= 4.0
* Added content marketplace
* Removed potentially offending words from random password generator
* Better logging

= 3.18
* Prepared for localization

= 3.17
* Fixed OAuth token reathorize problem

= 3.16
* Fixed problem interrupting execution when getting FR product list to display for WC products (happens for some PHP configurations)

= 3.15
* Fixed issue where Flickrocket options were not displayed for variable products in backend, unless "Flickrocket" checkbox was used
* Added retry on backend timeouts or temporary maintenance

= 3.14
* Fixed problem when Jquery-UI wasn't loaded
* Added code orders for oders with > 1 quantity of digital products

= 3.13
* Fixed potential settings loss problem
* Removed separate digital content access email and added information in standard emails instead
* Prepared code orders

= 3.12
* Fix for saving settings

= 3.11
* Changes for using new dev environment
* Settings no show theme name
* Fix for problem with syncing when no WooCommerce products are set up
* Fix for encrypted media streaming policy change with iframes
* Using original "Save" button in settings

= 3.10
* Fix for callback error when gtting data for player frame

= 3.9
* Fix for paged FR product fetch

= 3.8
* Fix for Flickrocket checkbox becoming auto-enabled for simple products
* Code cleanup

= 3.7
* Fix for making sure orders with invalid ("0") licenses (e.g. physical) are not sent to Flickrocket for processing

= 3.6
* Product loading for sync now paged to avoid timeouts in shops with many products

= 3.5
* Fix for mixed physical/digital variable products causing order with invalid license 

= 3.4
* Detection for missing cURL

= 3.3
* Fix for sync progress going to 100%

= 3.2
* Added category sync
* Added removal of webhooks on uninstall

= 3.1
* Minor fixes, cleanup

= 3.0
* Changed account based SOAP to token based REST interface
* Authentication now via OAuth / no more storage of user credentials
* Content access account now separate from shop account although starts with identical email.
* Content access one time credentials displayed after purchase and sent via email
* Optional automatic sync of products from Flickrocket to Woocommerce
* Option for manual selective sync of products from Flickrocket to WooCommerce

= 2.19
* Fixed SD flag mixup for variable HD orders
* Made admin.js only load in backend

= 2.17
* Removed dependency on sandbox server for live operation

= 2.16
* Removed Fr ProductID from Coupons

= 2.15
* Minor cleanup

= 2.14
* Modified order status handling

= 2.13
* Fix for variable product selection 
* fix for saving variable product settings

= 2.12
* Added SD option for HD products

= 2.11
* Added separate email for digital orders

= 2.10
* Cleanup

= 2.09
* New check for php-soap being installed

= 2.08
* Fixed crash issue related to missing php-soap

= 2.07
* Fixed several issues with PayPal payments
* Code cleanup

= 2.0
* Fixed dynamic login URLs based on company
* Removed "Check" button in settings - no longer required
* Extended fields on settings page so no values are visually “cut off”
* Added Sync Secret to Settings
* Added optional email/password change sync adapter (URL is http(s)://<domain>/wp-content/plugins/woocommerce-digital-content-delivery-with-drm-flickrocket/flickrocket_sync.php
* "My Content" no accessed via iframe instead of button
* Fixed sending orders only for FlickRocket products
* Added "My Content" iframe on after order page
* Fixed protocol issues (https vs. https) with "My Content" integration
* Fixed issue with special characters in password
* Added help information specific to sandbox usage
* Fixed "My orders" handling in certain scenarios 
* Extended settings with sync information
* Requires now only account if FlickRocket product is in carts
* Upload wizard displayed only in live mode (can't be using in sandbox)
* Various wording changes
* Code cleanup

= 1.9
* Minor fix

= 1.8
* Minor fix

= 1.7
* Fix for mixed physical/digital shopping carts

= 1.6
* Fixed for order status issue with non-digital products

= 1.5
* Replaced "Content Access" text and botton with player access iframe 

= 1.4
* Minor bug fix in domain handling

= 1.3
* Minor bug fix for account without projects/themes

= 1.2 =
* UI cleanup

= 1.1 =
* UI cleanup
* Theme selection as drop down
* Reset password functionality added

= 1.0 =
* Public release version

