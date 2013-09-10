<?php

require_once('simpletest/autorun.php');
require_once('simpletest/unit_tester.php');
require_once('ms_sagepay.module');

class TestSagepay extends UnitTestCase {
    
  // mode      3DSecureStatus  CAVV      result
  // simulator     OK          present   OK
  // simulator     OK          -         doesn't occur on simulator, for now
  // simulator     not OK      present   "OK" (ignoring that the simulator is out of step with live|test for now)
  // live|test     OK          present   OK
  // live|test     OK          -         ERROR CS80: missing CAVV
  // live|test     not OK      -         OK
  // live|test     -           -         ERROR CS80: missing 3DSecureStatus

  function testMd5IsOk() {
    $post = get_post();
    $post['VPSSignature'] = '95CB2673910F1A5734D7BBF58EDC6D85';
    $extras = array();
    $this->assertEqual(ms_sagepay_check_MD5($post, $extras), 'OK');
  }
    
  function testMd5_3DSecureStatusOkCavvPresentIsOk() {
    $post = get_post();
    $post['3DSecureStatus'] = 'OK';
    $post['VPSSignature'] = '69CF9C90740F0500AACADD6517DB8B2E';
    $extras = array();
    $this->assertEqual(ms_sagepay_check_MD5($post, $extras), 'OK');
  }

  function testMd5_3DSecureStatusNotOkCavvPresentGivesOkButSimulatorIsOutOfStepWithLive() {
    $post = get_post();
    $post['3DSecureStatus'] = 'NOTCHECKED';
    $post['VPSSignature'] = 'DE2AA2E67734CD7160EED962F317B420';
    $extras = array();
    $this->assertEqual(ms_sagepay_check_MD5($post, $extras), 'OK');
  }

  function testMd5_3DSecureStatusOkCavvAbsentGivesError() {
    $post = get_post();
    $post['3DSecureStatus'] = 'OK';
    unset($post['CAVV']);
    $post['VPSSignature'] = 'a';
    $extras = array();
    $this->assertPattern('/ERROR CS80: missing CAVV/', ms_sagepay_check_MD5($post, $extras));
  }

  function testMd5_3DSecureStatusNotOkCavvAbsentIsOk() {
    $post = get_post();
    $post['3DSecureStatus'] = 'NOTCHECKED';
    unset($post['CAVV']);
    $post['VPSSignature'] = 'B2ACAC0F31F13225152AD3F1054B0387';
    $extras = array();
    $this->assertEqual(ms_sagepay_check_MD5($post, $extras), 'OK');
  }

  function testMd5_3DSecureStatusAbsentCavvAbsentGivesError() {
    $post = get_post();
    unset($post['3DSecureStatus']);
    unset($post['CAVV']);
    $post['VPSSignature'] = 'a';
    $extras = array();
    $this->assertPattern('/ERROR CS80: missing 3DSecureStatus/', ms_sagepay_check_MD5($post, $extras));
  }
}

/*
 * Helper to set up a $post hash
 */

function get_post() {
  $keys = array('VPSTxId','VendorTxCode','Status','TxAuthNo','VendorName','AVSCV2','SecurityKey',
    'AddressResult','PostCodeResult','CV2Result','GiftAid','3DSecureStatus','CAVV','AddressStatus',
    'PayerStatus','CardType','Last4Digits');
  $post = array();

  foreach ($keys as $key) {
    $post[$key] = $key . '.value';
  }
  return $post;
}

