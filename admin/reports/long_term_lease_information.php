<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
// Resolve location from cookie
$location_id = 0; $client_name = '';
if (isset($_COOKIE['client_cook'])) {
    $md = $_COOKIE['client_cook'];
    if ($stmt = mysqli_prepare($con, 'SELECT c_id, client_name FROM client_registration WHERE md5_client=? LIMIT 1')) {
        mysqli_stmt_bind_param($stmt, 's', $md);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $location_id = (int)$row['c_id'];
            $client_name = $row['client_name'];
        }
        mysqli_stmt_close($stmt);
    }
}

$rows = [];
if ($location_id > 0) {
    $sql = "SELECT 
                l.lease_id,
                b.name,
                b.contact_person,
                b.address,
                b.district,
                b.nic_reg_no,
                b.telephone,
                b.email,
                lr.land_address,
                gn.gn_name,
                lr.sketch_plan_no,
                lr.plc_plan_no,
                lr.survey_plan_no,
                lr.extent_ha,
                (
                    SELECT fv.visite_status 
                    FROM ltl_feald_visits fv 
                    WHERE fv.lease_id = l.lease_id AND fv.status = 1 
                    ORDER BY fv.date DESC, fv.id DESC 
                    LIMIT 1
                ) AS development_status
            FROM leases l
            INNER JOIN beneficiaries b ON b.ben_id = l.beneficiary_id
            INNER JOIN ltl_land_registration lr ON lr.land_id = l.land_id
            LEFT JOIN gn_division gn ON gn.gn_id = lr.gn_id
            WHERE l.location_id = ?
            ORDER BY b.name";
    if ($st = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($st, 'i', $location_id);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        while ($rs && ($r = mysqli_fetch_assoc($rs))) { $rows[] = $r; }
        mysqli_stmt_close($st);
    }
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>Long Term Lease Information</title>
<style>
  body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #222; }
  .header { text-align: left; margin-bottom: 10px; }
  .header h2 { margin: 0; font-size: 18px; }
  .sub { font-size: 12px; color: #555; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #353535ff; padding: 6px; vertical-align: top; }
  th { background: #f0f0f0; text-align: left; }
  @media print {
    @page { size: A4 landscape; margin: 10mm; }
    .noprint { display: none; }
  }
</style>
</head>
<body>
  <div class="header">
    <h2>Long Term Lease Information</h2>
    <div class="sub"> <?= h($client_name) ?> | Generated: <?= h($today) ?></div>
  </div>

  <table>
    <thead>
      <tr>
        <th>SN</th>
        <th>Name</th>
        <th>Address</th>
        <th>District</th>
        <th>NIC/Reg. No</th>
        <th>Telephone</th>
        <th>Email</th>
        <th>Land Address</th>
        <th>GN Name</th>
        <th>Sketch Plan No</th>
        <th>PLC Plan No</th>
        <th>Survey Plan No</th>
        <th>Extent (ha)</th>
        <th>Development Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="14" style="text-align:center; color:#888;">No records found.</td></tr>
      <?php else: $sn=1; foreach($rows as $r): ?>
        <tr>
          <td><?= $sn++ ?></td>
          <td><?php $nm = trim((string)$r['contact_person']); echo $nm !== '' ? h($nm) : h($r['name']); ?></td>
          <td><?= h($r['address']) ?></td>
          <td><?= h($r['district']) ?></td>
          <td><?= h($r['nic_reg_no']) ?></td>
          <td><?= h($r['telephone']) ?></td>
          <td><?= h($r['email']) ?></td>
          <td><?= h($r['land_address']) ?></td>
          <td><?= h($r['gn_name']) ?></td>
          <td><?= h($r['sketch_plan_no']) ?></td>
          <td><?= h($r['plc_plan_no']) ?></td>
          <td><?= h($r['survey_plan_no']) ?></td>
          <td><?= h($r['extent_ha']) ?></td>
          <td><?= h($r['development_status']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <div class="noprint" style="margin-top:10px; text-align:right;">
    <button onclick="window.print()" style="padding:6px 12px;">Print</button>
  </div>

  <script>window.onload = function(){ window.print(); };</script>
</body>
</html>
