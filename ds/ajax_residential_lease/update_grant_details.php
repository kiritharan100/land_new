<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

function json_response(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

function clean_date(?string $value): ?string
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

$rl_lease_id = isset($_POST['rl_lease_id']) ? (int) $_POST['rl_lease_id'] : 0;
$outright_grants_number = trim($_POST['outright_grants_number'] ?? '');
$outright_grants_date = clean_date($_POST['outright_grants_date'] ?? null);

if ($rl_lease_id <= 0) {
    json_response(false, 'Lease ID is required.');
}

// Verify lease exists
if ($stmtCheck = $con->prepare('SELECT rl_lease_id FROM rl_lease WHERE rl_lease_id = ? LIMIT 1')) {
    $stmtCheck->bind_param('i', $rl_lease_id);
    $stmtCheck->execute();
    $res = $stmtCheck->get_result();
    if (!$res || $res->num_rows === 0) {
        $stmtCheck->close();
        json_response(false, 'Lease not found.');
    }
    $stmtCheck->close();
}

$sql = "UPDATE rl_lease SET outright_grants_number = ?, outright_grants_date = ? WHERE rl_lease_id = ?";

if ($stmt = $con->prepare($sql)) {
    $stmt->bind_param('ssi', $outright_grants_number, $outright_grants_date, $rl_lease_id);

    if ($stmt->execute()) {
        $stmt->close();
        json_response(true, 'Grant details updated successfully.');
    }

    $err = $stmt->error;
    $stmt->close();
    json_response(false, 'Failed to update grant details. ' . $err);
}

json_response(false, 'Database error: ' . $con->error);

