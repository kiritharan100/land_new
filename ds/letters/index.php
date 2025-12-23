<?php
// Sample report endpoint: replace with real report logic.
header('Content-Type: text/html; charset=utf-8');
$report = $_GET['report'] ?? 'unknown';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sample Report: <?php echo htmlspecialchars($report); ?></title>
<link rel="stylesheet" href="../assets/newtheme.css">
<style>
 body{font-family:Arial, sans-serif; margin:20px;}
 h2{margin-top:0;}
 table{border-collapse:collapse;width:100%;margin-top:15px;}
 th,td{border:1px solid #ccc;padding:6px 8px;font-size:13px;}
 th{background:#f5f5f5;}
 .meta span{display:inline-block;margin-right:15px;}
 @media print{.no-print{display:none;}}
</style>
</head>
<body>
<h2>Sample Report: <?php echo htmlspecialchars(str_replace('_',' ', $report)); ?></h2>
<div class="meta">
  <span><strong>Generated:</strong> <?php echo date('Y-m-d H:i'); ?></span>
  <?php foreach($_GET as $k=>$v): if($k==='report') continue; ?>
    <span><strong><?php echo htmlspecialchars(ucwords(str_replace('_',' ', $k))); ?>:</strong> <?php echo htmlspecialchars($v); ?></span>
  <?php endforeach; ?>
</div>
<p>This is a placeholder for <strong><?php echo htmlspecialchars($report); ?></strong>. Implement database queries and layout here.</p>
<table>
  <thead><tr><th>#</th><th>Example Field A</th><th>Example Field B</th><th>Example Field C</th></tr></thead>
  <tbody>
  <?php for($i=1;$i<=8;$i++): ?>
    <tr>
      <td><?php echo $i; ?></td>
      <td>A<?php echo $i; ?></td>
      <td>B<?php echo $i; ?></td>
      <td>C<?php echo $i; ?></td>
    </tr>
  <?php endfor; ?>
  </tbody>
</table>
<div class="no-print" style="margin-top:20px;">
  <button onclick="window.print()" class="btn btn-primary">Print</button>
  <button onclick="window.close()" class="btn btn-secondary">Close</button>
</div>
</body>
</html>