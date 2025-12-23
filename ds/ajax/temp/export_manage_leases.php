<?php
include '../../db.php';

$location_id = intval($_GET['location_id'] ?? 0);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=leases_' . date('YmdHis') . '.csv');

$out = fopen('php://output', 'w');
// header
// CSV header: include lease fields, selected land_registration fields, beneficiary fields, plus balances
fputcsv($out, [
    'Lease ID','Lease Number','Land ID','Beneficiary ID','Location ID','Valuation Amount','Annual Rent %','Revision Period','Revision %','Start Date','End Date','Status','Created By','Created On','Valuation Date','Duration Years','Lease Type ID','Type Of Project','Name Of The Project',
    'Land Address','DS Division','GN Division','Land Area','Scaled By','Hectares','Latitude','Longitude','LCG Area','LCG Area Unit','LCG Hectares','LCG Plan No','Val Area','Val Area Unit','Val Hectares','Val Plan No','Survey Area','Survey Area Unit','Survey Hectares','Survey Plan No',
    'Beneficiary ID','Beneficiary Name','Is Individual','Contact Person','Beneficiary Address','District','DS Division ID','DS Division Text','GN Division ID','GN Division Text','NIC/Reg No','DOB','Nationality','Telephone','Email','Beneficiary Created By','Beneficiary Created On','Beneficiary Status',
    'Balance Rent','Balance Penalty'
]);

// Select lease columns, land_registration columns, beneficiaries columns and computed balances
$sql = "SELECT 
    l.lease_id, l.lease_number, l.land_id, l.beneficiary_id, l.location_id,
    l.valuation_amount, l.annual_rent_percentage, l.revision_period, l.revision_percentage, l.start_date, l.end_date, l.status, l.created_by, l.created_on, l.valuation_date, l.duration_years, l.lease_type_id, l.type_of_project, l.name_of_the_project,
    land.address AS land_address, land.ds_id AS land_ds_id, cr.client_name AS land_ds_name, g.gn_name AS land_gn_name, land.land_area, land.scaled_by, land.hectares, land.latitude, land.longitude,
    land.lcg_area, land.lcg_area_unit, land.lcg_hectares, land.lcg_plan_no, land.val_area, land.val_area_unit, land.val_hectares, land.val_plan_no,
    land.survey_area, land.survey_area_unit, land.survey_hectares, land.survey_plan_no,
    ben.ben_id AS ben_id, ben.name AS ben_name, ben.is_individual, ben.contact_person, ben.address AS ben_address, ben.district, ben.ds_division_id, ben.ds_division_text, ben.gn_division_id, ben.gn_division_text, ben.nic_reg_no, ben.dob, ben.nationality, ben.telephone, ben.email, ben.created_by AS ben_created_by, ben.created_on AS ben_created_on, ben.status AS ben_status,
    (SELECT COALESCE(SUM((IFNULL(s.annual_amount,0) - IFNULL(s.paid_rent,0))),0) FROM lease_schedules s WHERE s.lease_id = l.lease_id AND s.end_date < CURDATE()) AS rent_balance,
    (SELECT COALESCE(SUM((IFNULL(s.panalty,0) - IFNULL(s.panalty_paid,0))),0) FROM lease_schedules s WHERE s.lease_id = l.lease_id AND s.end_date < CURDATE()) AS penalty_balance
    FROM leases l
    LEFT JOIN land_registration land ON l.land_id = land.land_id
    LEFT JOIN gn_division g ON land.gn_id = g.gn_id
    LEFT JOIN client_registration cr ON land.ds_id = cr.c_id
    LEFT JOIN beneficiaries ben ON l.beneficiary_id = ben.ben_id
    WHERE l.location_id = ?
    ORDER BY l.created_on DESC";

$stmt = $con->prepare($sql);
$stmt->bind_param('i', $location_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    // write row in same order as header
    fputcsv($out, [
        // Lease fields
        $row['lease_id'] ?? '',
        $row['lease_number'] ?? '',
        $row['land_id'] ?? '',
        $row['beneficiary_id'] ?? '',
        $row['location_id'] ?? '',
        number_format((float)($row['valuation_amount'] ?? 0),2),
        $row['annual_rent_percentage'] ?? '',
        $row['revision_period'] ?? '',
        $row['revision_percentage'] ?? '',
        $row['start_date'] ?? '',
        $row['end_date'] ?? '',
        $row['status'] ?? '',
        $row['created_by'] ?? '',
        $row['created_on'] ?? '',
        $row['valuation_date'] ?? '',
        $row['duration_years'] ?? '',
        $row['lease_type_id'] ?? '',
        $row['type_of_project'] ?? '',
        $row['name_of_the_project'] ?? '',

        // Land fields
        $row['land_address'] ?? '',
        $row['land_ds_name'] ?? $row['land_ds_id'] ?? '',
        $row['land_gn_name'] ?? '',
        $row['land_area'] ?? '',
        $row['scaled_by'] ?? '',
        $row['hectares'] ?? '',
        $row['latitude'] ?? '',
        $row['longitude'] ?? '',
        $row['lcg_area'] ?? '',
        $row['lcg_area_unit'] ?? '',
        $row['lcg_hectares'] ?? '',
        $row['lcg_plan_no'] ?? '',
        $row['val_area'] ?? '',
        $row['val_area_unit'] ?? '',
        $row['val_hectares'] ?? '',
        $row['val_plan_no'] ?? '',
        $row['survey_area'] ?? '',
        $row['survey_area_unit'] ?? '',
        $row['survey_hectares'] ?? '',
        $row['survey_plan_no'] ?? '',

        // Beneficiary fields
        $row['ben_id'] ?? $row['beneficiary_id'] ?? '',
        $row['ben_name'] ?? '',
        $row['is_individual'] ?? '',
        $row['contact_person'] ?? '',
        $row['ben_address'] ?? '',
        $row['district'] ?? '',
        $row['ds_division_id'] ?? '',
        $row['ds_division_text'] ?? '',
        $row['gn_division_id'] ?? '',
        $row['gn_division_text'] ?? '',
        $row['nic_reg_no'] ?? '',
        $row['dob'] ?? '',
        $row['nationality'] ?? '',
        $row['telephone'] ?? '',
        $row['email'] ?? '',
        $row['ben_created_by'] ?? '',
        $row['ben_created_on'] ?? '',
        $row['ben_status'] ?? '',

        // Balances
        number_format((float)($row['rent_balance'] ?? 0),2),
        number_format((float)($row['penalty_balance'] ?? 0),2)
    ]);
}
fclose($out);
exit;

?>
