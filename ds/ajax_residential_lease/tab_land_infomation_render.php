<?php
// Renders the Land Information tab partial for Residential lease (rl_land_registration)
header('Content-Type: text/html; charset=UTF-8');
require_once dirname(__DIR__, 2) . '/db.php';

// Resolve client/location context from cookie
$selected_client = isset($_COOKIE['client_cook']) ? $_COOKIE['client_cook'] : '';
$location_id = '';
$client_name = '';
$coordinates = '[]';
if ($selected_client !== '') {
    $sqlClient = "SELECT c_id, client_name, coordinates FROM client_registration WHERE md5_client = ? LIMIT 1";
    if ($stmtC = mysqli_prepare($con, $sqlClient)) {
        mysqli_stmt_bind_param($stmtC, 's', $selected_client);
        mysqli_stmt_execute($stmtC);
        $resC = mysqli_stmt_get_result($stmtC);
        if ($resC && ($rowC = mysqli_fetch_assoc($resC))) {
            $location_id = $rowC['c_id'];
            $client_name = $rowC['client_name'];
            $coordinates = $rowC['coordinates'] !== '' ? $rowC['coordinates'] : '[]';
        }
        mysqli_stmt_close($stmtC);
    }
}

// Resolve beneficiary ID
$ben_id = null;
if (isset($_GET['ben_id']) && ctype_digit($_GET['ben_id'])) {
    $ben_id = (int) $_GET['ben_id'];
} else {
    $md5_ben_id = isset($_GET['id']) ? $_GET['id'] : '';
    if ($md5_ben_id !== '') {
        $sqlBen = "SELECT rl_ben_id FROM rl_beneficiaries WHERE md5_ben_id = ? LIMIT 1";
        if ($stmtB = mysqli_prepare($con, $sqlBen)) {
            mysqli_stmt_bind_param($stmtB, 's', $md5_ben_id);
            mysqli_stmt_execute($stmtB);
            $resB = mysqli_stmt_get_result($stmtB);
            if ($resB && ($rowB = mysqli_fetch_assoc($resB))) {
                $ben_id = (int) $rowB['rl_ben_id'];
            }
            mysqli_stmt_close($stmtB);
        }
    }
}

include __DIR__ . '/tab_land_infomation.php';
