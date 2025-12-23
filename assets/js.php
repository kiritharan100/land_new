<?php
if (defined('RENEWAL_CHECK_RAN')) {
    return; // Already executed once
}
define('RENEWAL_CHECK_RAN', true);



$url = "https://dtecstudio.com/license/epplc_renewal.json";

// Try to fetch JSON
$json_data = @file_get_contents($url);

// Ignore if URL unreachable
if ($json_data === FALSE) {
    return;
}

$data = json_decode($json_data, true);

// Ignore if JSON invalid
if (!is_array($data)) {
    return;
}

$renewal_date = $data['next_renewal_date'] ?? null;
$stop_service = strtolower($data['stop_service'] ?? "no"); // yes/no
$today = date("Y-m-d");

// Validate date
if (!$renewal_date || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $renewal_date)) {
    return;
}

// ----------------------------------------------------
// 1. Warning alert if renewal date passed
// ----------------------------------------------------
 if ($today > $renewal_date) {
    echo "<script>
        Swal.fire({
            icon: 'warning',
            title: 'Maintenance Expired',
            html: `
                System Maintenance renewal date has passed. Please renew as soon as possible.<br><br>
                You may continue using the system without a valid maintenance agreement, but several risks apply:
                <ul style=\"text-align:left;\">
                    <li>No security checks or vulnerability patching</li>
                    <li>No regular backups</li>
                    <li>No error log monitoring</li>
                    <li>No bug fixes or functional updates</li>
                    <li>No performance optimization</li>
                    <li>No database tuning</li>
                    <li>No system recovery support</li>
                    <li>No new features or compatibility updates</li>
                    <li>No priority technical support</li>
                </ul>
            `,
            confirmButtonText: 'OK'
        });
    </script>";
}


// ----------------------------------------------------
// 2. Stop service enforcement
// ----------------------------------------------------
// If stop_service = YES AND date passed → block access
if ($stop_service === "yes" && $today > $renewal_date) {

    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Service Disabled',
            text: 'Your service has been disabled. Please contact support.',
            confirmButtonText: 'OK'
        }).then(() => {
            window.location.href = 'about:blank';
        });
    </script>";

    exit; // stop system completely
}

// If stop_service = no OR date not passed → allow
?>
