<?php
require_once(__DIR__ . '/../../../config.php');
require_login();

global $USER, $CFG, $DB;

$courseid = optional_param('id', 0, PARAM_INT);
if ($courseid <= 0) {
    print_error('invalidcourse', 'error', '', null, 'A valid Course ID must be provided.');
}

// =========================================================================
// FETCH CUSTOM FIELDS VIA NATIVE MOODLE API
// =========================================================================
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
// =========================================================================

if ($cohortid <= 0 || $raw_price <= 0) {
    print_error('missingpaymentconfig', 'error', '', null, 'Course missing Price or Cohort configurations.');
}

$merchantTrns = $USER->id . ':' . $cohortid;

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>[SANDBOX SIMULATOR] Secure Checkout Pass</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); text-align: center; max-width: 450px; width: 100%; border-top: 5px solid #ffc107; }
        h2 { color: #222; margin-top: 0; }
        p { color: #555; font-size: 0.95rem; line-height: 1.5; }
        .meta-box { background: #f8f9fa; padding: 15px; border-radius: 6px; text-align: left; font-family: monospace; font-size: 0.9rem; margin: 20px 0; color: #333; border: 1px solid #e9ecef; }
        .btn { background: #28a745; color: white; border: none; padding: 14px 28px; font-size: 1.1rem; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%; box-shadow: 0 4px 6px rgba(40,167,69,0.15); transition: background 0.2s; }
        .btn:hover { background: #218838; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Viva Wallet Local Simulator</h2>
        <p>You have been redirected here because <code>viva_mock_mode</code> is enabled in your configuration file. No data has left your server.</p>

        <div class="meta-box">
            <strong>Simulation Payload Data:</strong><br>
            • Cost: <?php echo number_format($raw_price, 2); ?> DKK<br>
            • Student Moodle ID: <?php echo $USER->id; ?><br>
            • Enrolment Cohort ID: <?php echo $cohortid; ?><br>
            • Structured Metadata: <?php echo $merchantTrns; ?>
        </div>

        <button class="btn" onclick="triggerSimulatedWebhook()">Confirm Simulated Payment</button>
    </div>

    <script>
    function triggerSimulatedWebhook() {
        const mockPayload = {
            "EventTypeID": 1796,
            "EventData": {
                "MerchantTrns": "<?php echo $merchantTrns; ?>",
                "StatusID": "F"
            }
        };

        // Targets the adjusted local endpoint location path
        fetch('/local/vivawallet/webhook.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(mockPayload)
        })
        .then(response => {
            if (response.ok) {
                window.location.href = '/my/';
            } else {
                alert('Fulfillment communication error inside webhook file.');
            }
        })
        .catch(err => alert('Simulation pipeline transport crash: ' + err));
    }
    </script>
</body>
</html>
<?php
exit;
