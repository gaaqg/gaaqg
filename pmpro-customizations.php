<?php
/**
 * Show next payment date under 'Expiration' field in PMPro account page.
 * Add this code to your PMPro Customizations Plugin - https://www.paidmembershipspro.com/create-a-plugin-for-pmpro-customizations/
 * Works for PayPal Express and Stripe payment gateways.
 * www.paidmembershipspro.com
 */
// Change the expiration text to show the next payment date instead of the expiration date
// This hook is setup in the wp_renewal_dates_setup function below
function my_pmpro_expiration_text($expiration_text) {
    global $current_user;
    $next_payment = pmpro_next_payment();
        
    if( $next_payment ){
        $expiration_text = date_i18n( get_option( 'date_format' ), $next_payment );
    }
    
    return $expiration_text;
}
// Change "expiration date" to "renewal date"
// This hook is setup in the wp_renewal_dates_setup function below
function change_expiration_date_to_renewal_date($translated_text, $original_text, $domain) {
    if($domain === 'paid-memberships-pro' && $original_text === 'Expiration')
        $translated_text = 'Renewal Date';
    
    return $translated_text;
}
// Logic to figure out if the user has a renewal date and to setup the hooks to show that instead
function wp_renewal_dates_setup() {
    global $current_user, $pmpro_pages;
    
    // in case PMPro is not active
    if(!function_exists('pmpro_getMembershipLevelForUser'))
        return;
    
    // If the user has an expiration date, tell PMPro it is expiring "soon" so the renewal link is shown
    $membership_level = pmpro_getMembershipLevelForUser($current_user->ID);            
    if(!empty($membership_level) && !pmpro_isLevelRecurring($membership_level))
        add_filter('pmpro_is_level_expiring_soon', '__return_true');    
    
    if( is_page( $pmpro_pages[ 'account' ] ) ) {
        // If the user has no expiration date, add filter to change "expiration date" to "renewal date"        
        if(!empty($membership_level) && (empty($membership_level->enddate) || $membership_level->enddate == '0000-00-00 00:00:00'))
            add_filter('gettext', 'change_expiration_date_to_renewal_date', 10, 3);        
        
        // Check to see if the user's last order was with PayPal Express, else assume it was with Stripe.
        // These filters make the next payment calculation more accurate by hitting the gateway
        $order = new MemberOrder();
        $order->getLastMemberOrder( $current_user->ID );
        if( !empty($order) && $order->gateway == 'paypalexpress') {
            add_filter('pmpro_next_payment', array('PMProGateway_paypalexpress', 'pmpro_next_payment'), 10, 3);    
        }else{
            add_filter('pmpro_next_payment', array('PMProGateway_stripe', 'pmpro_next_payment'), 10, 3);    
        }
    }
    add_filter('pmpro_account_membership_expiration_text', 'my_pmpro_expiration_text');    
}
add_action('wp', 'wp_renewal_dates_setup', 11);



/**
*  see https://www.paidmembershipspro.com/choose-when-to-display-the-renew-link-to-members-who-sign-up-for-a-membership-level-with-an-expiration-date/ 
* This will show the renewal date link within the number of days or less than the members expiration that you set in the code gist below.
* Add this code to your PMPro Customizations Plugin - https://www.paidmembershipspro.com/create-a-plugin-for-pmpro-customizations/
*/
function show_renewal_link_after_X_days( $r, $level ) {
    if ( empty( $level->enddate ) ) {
        return false;
    }

    $days = 30;

    $now = current_time( 'timestamp' );
    if ($now + ( $days * 3600 * 24 ) >= $level->enddate) {
        $r = true;
    } else {
        $r = false;
    }
    return $r;
}
add_filter( 'pmpro_is_level_expiring_soon', 'show_renewal_link_after_X_days', 10, 2 );


/**
* Filter the settings of email frequency sent when using the Extra Expiration Warning Emails Add On
* https://www.paidmembershipspro.com/add-ons/extra-expiration-warning-emails-add-on/
*
* Update the $settings array to your list of number of days => ''. 
* Read the Add On documentation for additional customization using this filter.
*/
function custom_pmproeewe_email_frequency( $settings = array() ) {
	$settings = array(
		7 => '',
		15 => '',
		30 => '',
	);
	return $settings;
}
add_filter( 'pmproeewe_email_frequency_and_templates', 'custom_pmproeewe_email_frequency', 10, 1 );

function load_css_for_level_checkout(){

	global $pmpro_pages;

	/* To have that code only show discount code fields for existing users, you can replace the $_REQUEST['level'] check with a call to pmpro_hasMembershipLevel() */
	/* if ( is_page( $pmpro_pages['checkout'] ) && isset( $_REQUEST['level'] ) == '1' ) { */
	// if ( is_page( $pmpro_pages['checkout'] ) && !pmpro_hasMembershipLevel(4)) {
	if ( is_page( $pmpro_pages['checkout'] ) && !pmpro_hasMembershipLevel(4)) {
		?>
		<style type="text/css">
			#other_discount_code_p {display: none;}
			#other_discount_code_tr {display: table-row !important;}
		</style>
		<?php
	}

}
add_action( 'wp_footer', 'load_css_for_level_checkout', 10 );

function my_pmpro_email_headers_admin_emails($headers, $email) {
    if(strpos($email->template, "_admin") !== false) {
        $headers[] = "Bcc:" . "membership@gaaqg.com";
        $headers[] = "Bcc:" . "pjwhaletail@gmail.com";
    }
    return $headers;
}
add_filter("pmpro_email_headers", "my_pmpro_email_headers_admin_emails", 10, 2);
