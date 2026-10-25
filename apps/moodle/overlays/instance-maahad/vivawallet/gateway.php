<?php
require_once(__DIR__ . '/../../../config.php');
require_login();

global $USER, $CFG;

$courseid = optional_param('id', 0, PARAM_INT);
if ($courseid <= 0) {
    throw new moodle_exception('invalidcourse', 'error', '', null, 'A valid Course ID must be provided.');
}

// 1. MOCK MODE GUARD
if (!empty($CFG->viva_mock_mode)) {
    redirect(new moodle_url('/local/vivawallet/simulator.php', ['id' => $courseid]));
}

// 2. FETCH CUSTOM FIELDS VIA MOODLE API
$handler = \core_customfield\handler::get_handler('core_course', 'course');
$customdata = $handler->get_instance_data($courseid, true);

$raw_price = 0;
$cohortid = 0;

foreach ($customdata as $data) {
    $shortname = $data->get_field()->get('shortname');
    if ($shortname === 'semesterprice') {
        $raw_price = floatval($data->get_value());
    }
    if ($shortname === 'mappedcohortid') {
        $cohortid = intval($data->get_value());
    }
}

if ($cohortid <= 0 || $raw_price <= 0) {
    throw new moodle_exception('missingpaymentconfig', 'error', '', null, 'Course missing Price or Cohort configurations.');
}

$price_in_cents = intval(round($raw_price * 100));

// Determine environment hostnames
$is_demo      = (!empty($CFG->viva_api_url) && strpos($CFG->viva_api_url, 'demo') !== false);
$accounts_url = $is_demo ? 'https://demo-accounts.vivapayments.com/connect/token' : 'https://accounts.vivapayments.com/connect/token';
$orders_url   = $is_demo ? 'https://demo-api.vivapayments.com/checkout/v2/orders' : 'https://api.vivapayments.com/checkout/v2/orders';
$checkout_url = $is_demo ? 'https://demo.vivapayments.com/web/checkout?ref='        : 'https://www.vivapayments.com/web/checkout?ref=';

// -------------------------------------------------------------------------
// STEP A: Fetch OAuth 2.0 Access Token
// -------------------------------------------------------------------------
$ch1 = curl_init($accounts_url);
curl_setopt($ch1, CURLOPT_POST, 1);
curl_setopt($ch1, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch1, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch1, CURLOPT_USERPWD, $CFG->viva_client_id . ':' . $CFG->viva_client_secret);
curl_setopt($ch1, CURLOPT_TIMEOUT, 15);
curl_setopt($ch1, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

$token_res  = curl_exec($ch1);
$token_code = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
curl_close($ch1);

$token_json   = json_decode($token_res, true);
$access_token = isset($token_json['access_token']) ? $token_json['access_token'] : '';

if (empty($access_token)) {
    throw new moodle_exception('vivatokenfail', 'error', '', null, "Viva OAuth Token Request Failed (HTTP $token_code). Check Client ID and Client Secret.");
}

// -------------------------------------------------------------------------
// STEP B: Create Payment Order with Customer Context
// -------------------------------------------------------------------------
$post_data = [
    'amount'       => $price_in_cents,
    'customerTrns' => 'Semester Access Registration',
    'merchantTrns' => $USER->id . ':' . $cohortid,
    'sourceCode'   => (string)$CFG->viva_source_code,
    'customer'     => [
        'email'    => $USER->email,
        'fullName' => fullname($USER)
    ]
];

$ch2 = curl_init($orders_url);
curl_setopt($ch2, CURLOPT_POST, 1);
curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($post_data));
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $access_token
]);
curl_setopt($ch2, CURLOPT_TIMEOUT, 15);
curl_setopt($ch2, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

$order_res  = curl_exec($ch2);
$order_code = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

// Force JSON string preservation for large 16-digit integer orderCodes
$order_json = json_decode($order_res, true, 512, JSON_BIGINT_AS_STRING);
$orderCode  = isset($order_json['orderCode']) ? (string)$order_json['orderCode'] : '';

if (empty($orderCode)) {
    $err = isset($order_json['message']) ? $order_json['message'] : $order_res;
    throw new moodle_exception('vivaorderfail', 'error', '', null, "Viva Order Creation Failed (HTTP $order_code): " . $err);
}

// -------------------------------------------------------------------------
// STEP C: Redirect Student to Viva Smart Checkout Portal
// -------------------------------------------------------------------------
redirect($checkout_url . $orderCode);
