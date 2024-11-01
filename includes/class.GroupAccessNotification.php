<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'GroupAccessNotification' ) ) {
	return;
}

/**
 * Class GroupAccessNotification
 */
class GroupAccessNotification extends WC_Email {

	/**
	 * Create an instance of the class.
	 *
	 * @access public
	 * @return void
	 */
	function __construct() {
    // Email slug we can use to filter other data.
		$this->id          = 'group_access_notification';
		$this->title       = __( 'Group Access Notification', 'woocommerce-digital-content-delivery-with-drm-flickrocket' );
		$this->description = __( 'An email sent to a user when access to a content group is granted.', 'woocommerce-digital-content-delivery-with-drm-flickrocket' );
    // For admin area to let the user know we are sending this email to customers.
		$this->customer_email = true;
		$this->heading     = __( 'Content Access Granted', 'woocommerce-digital-content-delivery-with-drm-flickrocket' );
		// translators: placeholder is {blogname}, a variable that will be substituted when email is sent out
		$this->subject     = sprintf( _x( '[%s] Content Access Granted', 'default email subject for group access notifications sent to users', 'woocommerce-digital-content-delivery-with-drm-flickrocket' ), '{blogname}' );
    
    // Template paths.
		$this->template_html  = 'emails/group-access-notification.php';
		$this->template_plain = 'emails/plain/group-access-notification.php';
		$this->template_base  = EMAIL_PATH . 'templates/';
    
	// Action to which we hook onto to send the email.

		add_action( 'send_group_notification', array( $this, 'trigger' ), 10, 5 );

		parent::__construct();
	}
	
    /**
	 * Trigger Function that will send this email to the customer.
	 *
	 * @access public
	 * @return void
	 */
	function trigger( $recipient, $email, $first_name, $last_name, $password ) {
		
		$this->recipient = $recipient;
		$this->login = $email;
		$this->first_name = $first_name;
		$this->last_name = $last_name;
		$this->password = $password;

		if ( ! $this->is_enabled() ) {
			return;
		}

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
    }

    /**
	 * Get content html.
	 *
	 * @access public
	 * @return string
	 */
	public function get_content_html() {
		$template = wc_get_template_html( $this->template_html, array(
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'			=> $this,
				'login'			=> $this->login,
				'recipient'		=> $this->recipient,
				'first_name'	=> $this->first_name,
				'last_name'		=> $this->last_name,
				'password'		=> $this->password
			), '', $this->template_base );
		return $template;
	}

	/**
	 * Get content plain.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		$template = wc_get_template_html( $this->template_plain, array(
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => true,
				'email'			=> $this,
				'login'			=> $this->login,
				'recipient'		=> $this->recipient,
				'first_name'	=> $this->first_name,
				'last_name'		=> $this->last_name,
				'password'		=> $this->password
			), '', $this->template_base );
		return $template;
	}
}
