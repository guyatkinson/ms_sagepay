<?php

require_once('simpletest/autorun.php');
require_once('simpletest/web_tester.php');
#require_once('ms_sagepay.module');

define('SITE', 'http://yoursite.com/');
define('USERNAME', 'testuser');
define('PASSWORD', 'pass');
define('MEMBERSHIP_PLAN', 'Plan');

// TODO define  membership text

// See http://www.simpletest.org/en/web_tester_documentation.html

// NB These tests require the Membership Plan being set to expire immediately on cancellation:
//   In Membership Plan -> Advanced Settings area, set "When to expire the user" to "On Cancellation".
// They also require SagePay Transaction Mode to be set to simulator

class TestSagepaySimulator extends WebTestCase {

    function testCheckoutOK() {
        $this->simulatorCheckout('ok');
    }

/*  
 * Helper
*/

    function simulatorCheckout($sagepayResponse) {
        $this->get(SITE);
        $this->setField('name', USERNAME);
        $this->setField('pass', PASSWORD);

        $this->click('Log in');

        $this->get(SITE . 'membership/purchase/1');
        $this->assertNoPattern('/There are no products in your cart./');
        $this->assertNoPattern('/not authorized/');  // If this occurs,
            // the membership was not cancelled by a previous test, or "When to expire" is not set to "On Cancellation" as above
        $this->assertText(MEMBERSHIP_PLAN);
        $this->assertNoPattern('/Choose Payment Method/');
        $this->click('Continue');

        $this->assertText('Using simulator mode.');
        if (strstr($this->getBrowser()->getContent(), 'Select which method you')) {
            // USERNAME has one or more saved payment profiles
            $this->click('Use a new card');
        }
        $this->setField('cc_first_name', 'First');
        $this->setField('cc_last_name', 'Last');
        $this->setField('billing_address1', 'b1');
        $this->setField('billing_zip', 'pc1 1aa');
        $this->setField('billing_email', 'test@example.com');
        #$this->setHttpsProtocol('ssl'); # https://test.sagepay.com doesn't support tls
        $this->click('Continue');

        $this->assertTitle('Simulator - Server Payment Page');
        $url_parts = parse_url($this->getUrl());
        parse_str($url_parts['query'], $params);
        $url = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];
        $url2 = preg_replace('/\.asp/', '2.asp', $url);
        $params['clickedButton'] = 'proceed'; # The Simulator pages assume Javascript; we use POST to do the click.
        $this->post($url2, $params);

        $this->assertTitle('Simulator - Server Transaction Information Page');
        $this->assertText('Transaction registered and user successfully redirected to the payment pages.');
#$this->showSource(); //TODO
        $transaction_id = get_param('TransactionID', $this->getBrowser()->getContent());
        $params['TransactionID'] = $transaction_id;
        $params['clickedButton'] = 'proceed';
        $url3 = preg_replace('/\.asp/', '3.asp', $url);
        $this->post($url3, $params);
        #$this->getBrowser()->submitFormByIndex(0, array('clickedButton' => 'proceed'));

        // $url3:
        $this->assertTitle('Simulator - Server Notification Options');
        $this->assertText('Server Status to send to the Notification URL');
        $params = array();
        $params['TransactionID'] = $transaction_id;
        $params['AddressResult'] = 'MATCHED';
        $params['PostCodeResult'] = 'MATCHED';
        $params['CV2Result'] = 'MATCHED';
        $params['Secure'] = 'OK';
        $params['cardtype'] = 'VISA';
        $params['addressstatus'] = 'NONE';
        $params['payerstatus'] = 'VERIFIED';
        $params['GiftAid'] = 'ON';
        $params['Signature'] = 'REAL';
        $params['clickedButton'] = $sagepayResponse;
        $this->post($url3, $params);

        // $url3:
        $this->assertTitle('Simulator - Server Notification Options');
        $this->assertText('Data to be Sent to your Notification URL');
print "*** notify ***\n";
#$this->showSource(); //TODO
        $params = array();
        $params['TransactionID'] = $transaction_id;
        $params['Notification'] = $this->getBrowser()->getField('Notification');
        #$params['Notification'] = get_param('Notification', $this->getBrowser()->getContent());
        $params['clickedButton'] = 'notify';
print_r($params);
        $this->post($url3, $params);

        // $url3:
        $this->assertTitle('Simulator - Server Notification Options');
        $this->assertText('Raw Reponse from your Notification URL');
        $this->assertText('Status=OK');
#        $this->assertText('Status=INVALID');
print "*** redirect ***\n";
#$this->showSource(); //TODO
        //TODO assertText(OK|error URL)
        $params = array();
        $params['TransactionID'] = $transaction_id;
        $params['RedirectURL'] = get_param('RedirectURL', $this->getBrowser()->getContent());
        $params['clickedButton'] = 'redirect';
print_r($params);
        $this->post($url3, $params);

        $this->assertPattern('/Thank You/');
        #$this->assertText('Order Status: ..');

        // Cancel membership
        $this->get(SITE . 'user');  // From User menu block->My account
        $this->click('Membership');
        #$uid = $this->get_uid(); 
#print "uid=$uid\n";
        $this->click('Cancel Membership');
        $this->click('Confirm');
        #delete_payment_profile($uid);

        // TODO make function w params['..'] for action, success|error, diff page on sitefor error - check msg
        //   assertStatus,  assertReturnPageTitle
    }

    /*
     * Helper to get the user id from the Membership info URL -
     *   the function must be called in the context of this URL.
     */
    function get_uid() {
        $this->assertText('Current Memberships');
        $membershipUrl = $this->getUrl();
        $this->assertEqual(preg_match("'/user/(\d*)/member-info'", $membershipUrl, $matches), 1);
        return $matches[1];
    }
}

    function get_param($name, $str) {
        $matches = array();
        // e.g.: <input type="hidden" value="http://SITE/ms_sagepay/thank-you/ZXXi.." name="RedirectURL">
        if (preg_match('/<input type="hidden" (.*?name="' . $name . '".*?)>/', $str, $matches) == 0) return '';
        foreach (explode(' ', $matches[1]) as $key_value) {
            $pieces = array();
            $pieces = explode('=', $key_value);
            if (isset($pieces[0]) && $pieces[0] == 'value') {
                return str_replace('"', '', $pieces[1]);
            }
        }
    }

/*
 * Helper to delete a payment profile (it's OK if there aren't any).
 */
function delete_payment_profile($uid) {
    if ($uid AND $payment_profile = ms_sagepay_get_default_payment_profile($uid)) {
        ms_sagepay_delete_payment_profile($payment_profile);
    }
    return;
}

?>

