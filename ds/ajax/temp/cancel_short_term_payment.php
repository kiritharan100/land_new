<?php
require('../../db.php');
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$payment_id = intval($_POST['payment_id'] ?? 0);
if ($payment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
    exit;
}

try {
    // Fetch payment with lease linkage
    $q = "SELECT p.payment_id, p.st_lease_id, p.payment_date, p.lease_amount_paid, p.penalty_amount_paid, p.total_amount, p.receipt_number, p.reference_number,
                 stl.amount_paid, stl.penalty_paid, stl.total_paid, stl.lease_amount, stl.penalty_amount
          FROM short_term_lease_payments p
          JOIN short_term_leases stl ON stl.st_lease_id = p.st_lease_id
          WHERE p.payment_id = ? FOR UPDATE";
    $stmt = $con->prepare($q);
    if (!$stmt) throw new Exception('Prepare failed: ' . $con->error);
    $stmt->bind_param('i', $payment_id);
    if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
    $r = $stmt->get_result();
    $row = $r->fetch_assoc();
    if (!$row) throw new Exception('Payment not found');

    // Already cancelled?
    if (floatval($row['total_amount']) == 0 || (stripos((string)$row['receipt_number'], 'CANCELLED') !== false)) {
        echo json_encode(['success' => true, 'message' => 'Payment already cancelled.']);
        exit;
    }

    $con->begin_transaction();

    $leaseId = (int)$row['st_lease_id'];
    $leasePaid = (float)$row['lease_amount_paid'];
    $penPaid = (float)$row['penalty_amount_paid'];
    $totPaid = (float)$row['total_amount'];

    // Update payment row to mark as cancelled and zero amounts (so aggregates exclude it)
    $newReceipt = trim(($row['receipt_number'] ?? ''));
    if ($newReceipt !== '' && stripos($newReceipt, 'CANCELLED') === false) {
        $newReceipt .= ' (CANCELLED)';
    } else if ($newReceipt === '') {
        $newReceipt = 'CANCELLED';
    }

    $noteSuffix = ' [Cancelled on ' . date('Y-m-d H:i') . ' by ' . ($_SESSION['username'] ?? 'system') . ']';

    $upPay = $con->prepare("UPDATE short_term_lease_payments
                             SET lease_amount_paid = 0,
                                 penalty_amount_paid = 0,
                                 total_amount = 0,
                                 receipt_number = ?,
                                 payment_notes = CONCAT(COALESCE(payment_notes,''), ?) 
                             WHERE payment_id = ?");
    if (!$upPay) throw new Exception('Prepare update payment failed: ' . $con->error);
    $upPay->bind_param('ssi', $newReceipt, $noteSuffix, $payment_id);
    if (!$upPay->execute()) throw new Exception('Failed to update payment: ' . $upPay->error);

    // Update lease aggregates (subtract amounts)
    $newAmtPaid = max(0, floatval($row['amount_paid']) - $leasePaid);
    $newPenPaid = max(0, floatval($row['penalty_paid']) - $penPaid);
    $newTotPaid = max(0, floatval($row['total_paid']) - $totPaid);

    $remaining = max(0, (float)$row['lease_amount'] + (float)$row['penalty_amount'] - $newTotPaid);
    $status = ($remaining <= 0) ? 'Paid' : (($newTotPaid > 0) ? 'Partially Paid' : 'Unpaid');

    $upLease = $con->prepare("UPDATE short_term_leases
                               SET amount_paid = ?,
                                   penalty_paid = ?,
                                   total_paid = ?,
                                   payment_status = ?,
                                   updated_on = NOW()
                               WHERE st_lease_id = ?");
    if (!$upLease) throw new Exception('Prepare update lease failed: ' . $con->error);
    $upLease->bind_param('dddsi', $newAmtPaid, $newPenPaid, $newTotPaid, $status, $leaseId);
    if (!$upLease->execute()) throw new Exception('Failed to update lease: ' . $upLease->error);

    $con->commit();

    if (function_exists('UserLog')) {
        UserLog('Short-Term Payments', 'Cancel Payment', 'Cancelled payment ID ' . $payment_id . ' for lease ' . $leaseId . ' amount ' . $totPaid);
    }

    echo json_encode(['success' => true, 'message' => 'Payment cancelled successfully.']);
} catch (Throwable $e) {
    if ($con && $con->errno === 0 && $con->in_transaction) {
        $con->rollback();
    }
    error_log('cancel_short_term_payment error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
