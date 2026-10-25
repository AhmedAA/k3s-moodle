<?php
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/cohort/lib.php');

global $DB, $CFG;

// -------------------------------------------------------------------------
// 1. GET Request: Viva Webhook URL Verification Ping
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    echo json_encode(['Key' => $CFG->viva_webhook_key ?? '']);
    exit();
}

// -------------------------------------------------------------------------
// 2. POST Request: Viva Payment Notification Event
// -------------------------------------------------------------------------
$input = file_get_contents('php://input');
$data  = json_decode($input, true);

if (!empty($data) && isset($data['EventData'])) {
    $eventData    = $data['EventData'];
    $status       = $eventData['StatusId'] ?? $eventData['StatusID'] ?? '';
    $merchantTrns = $eventData['MerchantTrns'] ?? '';

    // Status 'F' = Finished / Successfully Paid in Viva Wallet
    if ($status === 'F' && !empty($merchantTrns)) {
        $parts = explode(':', $merchantTrns);
        if (count($parts) === 2) {
            $userid   = intval($parts[0]);
            $cohortid = intval($parts[1]);

            if ($userid > 0 && $cohortid > 0) {
                // Check if user is already enrolled in the target cohort
                $already_enrolled = $DB->record_exists('cohort_members', [
                    'cohortid' => $cohortid,
                    'userid'   => $userid
                ]);

                if (!$already_enrolled) {
                    cohort_add_member($cohortid, $userid);
                    error_log("[Viva Webhook Success] Enrolled User ID $userid into Cohort ID $cohortid");
                }
            }
        }
    }
}

// Always respond with 200 OK to acknowledge receipt to Viva's server
header('HTTP/1.1 200 OK');
header('Content-Type: application/json');
echo json_encode(['status' => 'success']);
