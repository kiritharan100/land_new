<?php include 'header.php'; ?>

<?php
if(isset($_REQUEST['saved'])){
    echo"
    <script>
        notify('success', 'Success', 'Settings updated successfully'); </script>";
}
// Create OIL if not exists
$check_oil = mysqli_query($con, "SELECT * FROM product_master WHERE p_id = 0");
if (mysqli_num_rows($check_oil) == 0) {
    mysqli_query($con, "INSERT INTO product_master (p_id, p_code, p_name, p_cat, p_unit, last_price, status)
                        VALUES (0, 'OIL-0', 'OIL', 'Oil', 'L', 0.00, 1)");
    UserLog('1', 'System Setup', 'Default OIL created with p_id = 0');
}

// Fetch letter_head data
$row = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM letter_head WHERE id = 1"));
?>

<div class="content-wrapper">
  <div class="container-fluid">
    <div class="main-header">
      <h4>System Settings</h4>
    </div>

    <div class="card">
      <div class="card-block">
        <form method="post" action="ajax/save_setting.php" class="processing_form">
          <table class="table table-bordered">
            <tr>
              <th style="width:30%;">Entity Name (External Use)</th>
              <td><input type="text" name="entity" class="form-control" value="<?= htmlspecialchars($row['entity']) ?>"></td>
            </tr>
            <tr>
              <th>Entity Name (Internal Use)</th>
              <td><input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($row['company_name']) ?>"></td>
            </tr>
            <tr>
              <th>Address</th>
              <td><textarea name="address" class="form-control"><?= htmlspecialchars($row['address']) ?></textarea></td>
            </tr>
            <tr>
              <th>Email Address</th>
              <td><input type="text" name="email" class="form-control" value="<?= htmlspecialchars($row['email']) ?>"></td>
            </tr>
            <tr>
              <th>Telephone Number</th>
              <td><input type="text" name="telephone" class="form-control" value="<?= htmlspecialchars($row['telephone']) ?>"></td>
            </tr>
            <tr>
              <th>VAT Number</th>
              <td><input type="text" name="vat_no" class="form-control" value="<?= htmlspecialchars($row['vat_no']) ?>"></td>
            </tr>
            <tr>
              <th>Registration Number</th>
              <td><input type="text" name="reg_no" class="form-control" value="<?= htmlspecialchars($row['reg_no']) ?>"></td>
            </tr>
            <tr>
              <th>VAT Invoice Prefix</th>
              <td><input type="text" name="invoice_prefix" class="form-control" value="<?= htmlspecialchars($row['invoice_prefix']) ?>"></td>
            </tr>
            <tr>
              <th>TIN Number</th>
              <td><input type="text" name="VAT" class="form-control" value="<?= htmlspecialchars($row['VAT']) ?>"></td>
            </tr>
            <tr>
              <th>Device Approval by Admin</th>
              <td>
                <select name="admin_device_approval" class="form-control">
                  <option value="1" <?= $row['admin_device_approval'] == '1' ? 'selected' : '' ?>>Yes</option>
                  <option value="0" <?= $row['admin_device_approval'] == '0' ? 'selected' : '' ?>>No</option>
                </select>
              </td>
            </tr>
            <tr>
              <th>Admin Mobile Number</th>
              <td><input type="text" name="gm_mobile" class="form-control" value="<?= htmlspecialchars($row['gm_mobile']) ?>"></td>
            </tr>
            <tr>
              <th>System Email</th>
              <td><input type="text" name="system_email" class="form-control" <?php if($user_id > 1) echo "readonly"; ?> value="<?= htmlspecialchars($row['system_email']) ?>"></td>
            </tr>
             <tr>
              <th>Doain</th>
              <td><input type="text" name="domain" class="form-control" <?php if($user_id > 1) echo "readonly"; ?> value="<?= htmlspecialchars($row['domain']) ?>"></td>
            </tr>
            
            <tr>
              <td colspan="2" class="text-right">
                <button type="submit" class="btn btn-success processing_button">
                  <i class="fa fa-save"></i> Save Settings
                </button>
              </td>
            </tr>
          </table>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
