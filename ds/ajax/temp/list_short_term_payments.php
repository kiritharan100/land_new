<?php
require('../../db.php');
session_start();

if (empty($_SESSION['username'])) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Unauthorized</div>';
    exit;
}

$lease_id = intval($_GET['lease_id'] ?? 0);
if ($lease_id <= 0) {
    echo '<div class="alert alert-warning">Invalid lease ID.</div>';
    exit;
}

try {
    $sql = "SELECT payment_id, payment_date, reference_number, lease_amount_paid, penalty_amount_paid, total_amount, receipt_number, payment_notes
            FROM short_term_lease_payments
            WHERE st_lease_id = ?
            ORDER BY payment_date DESC, payment_id DESC";
    $stmt = $con->prepare($sql);
    $stmt->bind_param('i', $lease_id);
    $stmt->execute();
    $res = $stmt->get_result();

    ob_start();
?>
<div class="table-responsive">
  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>Date</th>
        <th>Reference Number</th>
        <th>Lease Paid</th>
        <th>Penalty Paid</th>
        <th>Total Payment</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($res && $res->num_rows > 0): ?>
        <?php while ($p = $res->fetch_assoc()): 
            $isCancelled = (float)$p['total_amount'] == 0 || (stripos((string)$p['receipt_number'], 'CANCELLED') !== false);
        ?>
          <tr class="<?php echo $isCancelled ? 'table-warning' : '';?>">
            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($p['payment_date']))); ?></td>
            <td>
              <?php echo htmlspecialchars($p['reference_number'] ?: '-'); ?>
              <?php if ($isCancelled): ?>
                <span class="badge badge-danger ml-1">Cancelled</span>
              <?php endif; ?>
            </td>
            <td class="text-right">LKR <?php echo number_format((float)$p['lease_amount_paid'], 2); ?></td>
            <td class="text-right">LKR <?php echo number_format((float)$p['penalty_amount_paid'], 2); ?></td>
            <td class="text-right"><strong>LKR <?php echo number_format((float)$p['total_amount'], 2); ?></strong></td>
            <td>
              <?php if ($isCancelled): ?>
                <button class="btn btn-sm btn-secondary" disabled>Cancelled</button>
              <?php else: ?>
                <button class="btn btn-sm btn-danger btn-cancel-payment" data-payment-id="<?php echo (int)$p['payment_id']; ?>">
                  <i class="fa fa-times"></i> Cancel
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
          <tr><td colspan="6"><div class="alert alert-info mb-0">No payments recorded for this lease.</div></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php
    echo ob_get_clean();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<div class="alert alert-danger">Error loading payments: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
