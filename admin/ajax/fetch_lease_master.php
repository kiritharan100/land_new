<?php
include '../../db.php';
include '../../auth.php';
$result = mysqli_query($con, "SELECT * FROM lease_master ORDER BY lease_type_id DESC");
$output = '';
while($row = mysqli_fetch_assoc($result)){
    $output .= '<tr>
        <td>'.$row['lease_type_id'].'</td>
        <td>'.$row['lease_type_name'].'</td>
        <td>'.$row['purpose'].'</td>
        <td>'.$row['base_rent_percent'].'%</td>
        <td>'.(isset($row['economy_rate']) ? $row['economy_rate'].'%' : '').'</td>
        <td>'.(isset($row['economy_valuvation']) ? $row['economy_valuvation'] : '').'</td>
        <td>'.$row['duration_years'].' yrs</td>
        <td>Every '.$row['revision_interval'].' yrs +'.$row['revision_increase_percent'].'%</td>
        <td>'.$row['penalty_rate'].'%</td>
        <td>'.($row['allow_interest_waiver'] ? "Yes":"No").'</td>
        <td>'.$row['effective_from'].'</td>
        <td><button class="btn btn-sm btn-info editBtn" data-id="'.$row['lease_type_id'].'">Edit</button></td>
    </tr>';
}
echo $output;
