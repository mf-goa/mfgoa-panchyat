<?php
session_start();

// ================================
// DEBUG MODE (EARLY)
// ================================
$debug_mode = isset($_GET['debug']) && $_GET['debug'] == 1;
if ($debug_mode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

/* ================================
   DIRECT DB CONNECTION (TEMP)
================================= */
$conn = new mysqli(
    "mysql.hostinger.in",
    "u748742760_mtlm4",
    "0]CfUM0cBzit",
    "u748742760_mf1oa"
);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
$conn->query("SET time_zone = '+05:30'");
date_default_timezone_set('Asia/Kolkata');
if ($debug_mode) {
    echo "<div style='background:#111;color:#0f0;padding:10px;font-size:12px;'>";
    echo "DEBUG: DB Connected<br>";
    echo "</div>";
}

/* ================================
   HARDCODED USERS
================================ */
$admin_users = [
    'admin' => ['password' => 'admin123']
];

/* ================================
   LOGIN
================================ */
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (isset($admin_users[$username]) && $admin_users[$username]['password'] === $password) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $username;
    } else {
        $error = "Invalid credentials";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

/* ================================
   SHOW LOGIN IF NOT AUTHENTICATED
================================ */
if (!isset($_SESSION['admin_logged_in'])):
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex justify-content-center align-items-center vh-100 bg-light">

<div class="card p-4 shadow" style="width:350px;">
<h4 class="mb-3 text-center">Admin Login</h4>

<?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>

<form method="POST">
<input type="text" name="username" class="form-control mb-3" placeholder="Username" required>
<input type="password" name="password" class="form-control mb-3" placeholder="Password" required>
<button name="login" class="btn btn-primary w-100">Login</button>
</form>

</div>
</body>
</html>
<?php
exit;
endif;

/* ================================
   DASHBOARD LOGIC
================================ */
if (!isset($_GET['from_date']) && !isset($_GET['to_date'])) {
    $from_date = date('Y-m-01', strtotime('-2 months'));
    $to_date = date('Y-m-t');
} else {
    $from_date = $_GET['from_date'];
    $to_date = $_GET['to_date'];
}
// Safety check
if ($from_date > $to_date) {
    [$from_date, $to_date] = [$to_date, $from_date];
}

$wado = isset($_GET['wado']) ? intval($_GET['wado']) : null;
$taluka = isset($_GET['taluka']) ? intval($_GET['taluka']) : null;
$panchayat = isset($_GET['panchayat']) ? intval($_GET['panchayat']) : null;

/* ================================
   BUILD WHERE
================================ */
$where = ["DATE(msce.collection_date) BETWEEN ? AND ?"];
$params = [$from_date, $to_date];
$types = "ss";

if ($debug_mode) {
    echo "<div style='background:#111;color:#0f0;padding:10px;font-size:12px;'>";
    echo "DEBUG: Inputs => From: $from_date, To: $to_date, Taluka: ".($taluka??'ALL').", Panchayat: ".($panchayat??'ALL').", Wado: ".($wado??'ALL')."<br>";
    echo "</div>";
}

if ($wado) {
    $where[] = "w.id = ?";
    $params[] = $wado;
    $types .= "i";
}
if ($taluka) {
    $where[] = "mt.id = ?";
    $params[] = $taluka;
    $types .= "i";
}
if ($panchayat) {
    $where[] = "mp.id = ?";
    $params[] = $panchayat;
    $types .= "i";
}

$where_sql = implode(" AND ", $where);

if ($debug_mode) {
    echo "<div style='background:#111;color:#0f0;padding:10px;font-size:12px;'>";
    echo "DEBUG WHERE: $where_sql&lt;br&gt;";
    echo "PARAM TYPES: $types&lt;br&gt;";
    echo "PARAMS: ".implode(',', $params)."&lt;br&gt;";
    echo "</div>";
}

/* ================================
   CSV INJECTION PROTECTION
================================ */
function safe_csv($value){
    if ($value === null) return '';
    return preg_replace('/^[-+=@]/', "'$0", $value);
}

/* ================================
   KPI QUERY
================================ */
$sql_kpi = "
SELECT 
COUNT(msce.segregation_status_id) AS total_collections,
COUNT(DISTINCT msce.household_id) AS serviced_households,
MAX(msce.collection_date) AS last_collection
FROM mf_submit_collection_entry msce
JOIN mf_household mh ON mh.id = msce.household_id
JOIN mf_wado w ON w.id = mh.wado_id
JOIN mf_panchayat mp ON mp.id = w.panchayat_id
JOIN mf_taluka mt ON mt.id = mp.taluka_id
WHERE $where_sql
AND msce.home_status_id IN (1,2)
AND msce.segregation_status_id IS NOT NULL
";

$stmt = $conn->prepare($sql_kpi);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$kpi = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($debug_mode) {
    echo "<div style='background:#111;color:#0f0;padding:10px;font-size:12px;'>";
    echo "DEBUG KPI: ".json_encode($kpi)."<br>";
    echo "</div>";
}

/* TOTAL HOUSEHOLDS KPI */
$sql_total_households = "
SELECT COUNT(*) as total_households
FROM mf_household mh
JOIN mf_wado w ON w.id = mh.wado_id
JOIN mf_panchayat mp ON mp.id = w.panchayat_id
JOIN mf_taluka mt ON mt.id = mp.taluka_id
WHERE mh.status = 1
";
$total_households_where = [];
$total_households_params = [];
$total_households_types = "";
if ($taluka) {
    $total_households_where[] = "mt.id = ?";
    $total_households_params[] = $taluka;
    $total_households_types .= "i";
}
if ($panchayat) {
    $total_households_where[] = "mp.id = ?";
    $total_households_params[] = $panchayat;
    $total_households_types .= "i";
}
if (!empty($total_households_where)) {
    $sql_total_households .= " AND " . implode(" AND ", $total_households_where);
}
$stmt = $conn->prepare($sql_total_households);
if (!empty($total_households_params)) {
    $stmt->bind_param($total_households_types, ...$total_households_params);
}
$stmt->execute();
$total_households = $stmt->get_result()->fetch_assoc()['total_households'];
$stmt->close();

/* COLLECTION SERVICE COMPLIANCE */
$collection_compliance = $total_households > 0 
    ? round(($kpi['serviced_households'] / $total_households) * 100, 1)
    : 0;

/* ================================
   MONTHLY TREND
================================ */
$sql_month = "
SELECT MONTH(msce.collection_date) AS month,
COUNT(DISTINCT msce.household_id) AS serviced
FROM mf_submit_collection_entry msce
JOIN mf_household mh ON mh.id = msce.household_id
JOIN mf_wado w ON w.id = mh.wado_id
JOIN mf_panchayat mp ON mp.id = w.panchayat_id
JOIN mf_taluka mt ON mt.id = mp.taluka_id
WHERE $where_sql
AND msce.home_status_id IN (1,2)
AND msce.segregation_status_id IS NOT NULL
GROUP BY month ORDER BY month
";

$stmt = $conn->prepare($sql_month);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$months = [];
$serviced_data = [];
$month_names = [
    1=>"Jan",2=>"Feb",3=>"Mar",4=>"Apr",
    5=>"May",6=>"Jun",7=>"Jul",8=>"Aug",
    9=>"Sep",10=>"Oct",11=>"Nov",12=>"Dec"
];

// Ensure all months in range are present, even with zeroes
$month_range = [];
$start = strtotime($from_date);
$end = strtotime($to_date);
while ($start <= $end) {
    $m = date('n', $start);
    $month_range[$m] = 0;
    $start = strtotime('+1 month', $start);
}

while ($r = $res->fetch_assoc()) {
    $month_range[$r['month']] = (int)$r['serviced'];
}

$months = [];
$serviced_data = [];
foreach ($month_range as $m => $val) {
    $months[] = $month_names[$m];
    $serviced_data[] = $val;
}
$stmt->close();
if ($debug_mode) {
    echo "<div style='background:#111;color:#0f0;padding:10px;font-size:12px;'>";
    echo "DEBUG Monthly Rows: ".count($months)."<br>";
    echo "</div>";
}

/* ================================
   WADO BREAKDOWN
================================ */
$sql_wado = "
SELECT 
w.name,
COUNT(DISTINCT msce.household_id) AS serviced,
(SELECT COUNT(*) FROM mf_household WHERE status=1 AND wado_id=w.id) AS total_households
FROM mf_submit_collection_entry msce
JOIN mf_household mh ON mh.id = msce.household_id
JOIN mf_wado w ON w.id = mh.wado_id
JOIN mf_panchayat mp ON mp.id = w.panchayat_id
JOIN mf_taluka mt ON mt.id = mp.taluka_id
WHERE $where_sql
AND msce.home_status_id IN (1,2)
AND msce.segregation_status_id IS NOT NULL
GROUP BY w.id
ORDER BY serviced DESC
";

$stmt = $conn->prepare($sql_wado);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$wado_labels = [];
$wado_serviced = [];
$wado_total = [];

while ($r = $res->fetch_assoc()) {
    $wado_labels[] = $r['name'];
    $wado_serviced[] = $r['serviced'];
    $wado_total[] = $r['total_households'];
}
// WADO BREAKDOWN FIX: Ensure chart always has data
if (empty($wado_labels)) {
    $wado_labels = ['No Data'];
    $wado_serviced = [0];
    $wado_total = [0];
}
$stmt->close();
if ($debug_mode) {
    echo "<div style='background:#111;color:#0f0;padding:10px;font-size:12px;'>";
    echo "DEBUG Wado Rows: ".count($wado_labels)."<br>";
    echo "</div>";
}

/* ================================
   SEGREGATION PIE
================================ */
$sql_seg = "
SELECT ss.name, COUNT(DISTINCT msce.household_id) AS total
FROM mf_submit_collection_entry msce
JOIN mf_segregation_status ss ON ss.id = msce.segregation_status_id
JOIN mf_household mh ON mh.id = msce.household_id
JOIN mf_wado w ON w.id = mh.wado_id
JOIN mf_panchayat mp ON mp.id = w.panchayat_id
JOIN mf_taluka mt ON mt.id = mp.taluka_id
WHERE $where_sql
AND msce.home_status_id IN (1,2)
AND msce.segregation_status_id IS NOT NULL
GROUP BY ss.id
";

$stmt = $conn->prepare($sql_seg);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$seg_labels = [];
$seg_data = [];

while ($r = $res->fetch_assoc()) {
    $seg_labels[] = $r['name'];
    $seg_data[] = $r['total'];
}
// SEGREGATION PIE FIX: Ensure chart always has data
if (empty($seg_labels)) {
    $seg_labels = ['No Data'];
    $seg_data = [0];
}
$stmt->close();
if ($debug_mode) {
    echo "<div style='background:#111;color:#0f0;padding:10px;font-size:12px;'>";
    echo "DEBUG Seg Rows: ".count($seg_labels)."<br>";
    echo "</div>";
}

/* CALCULATE SEGREGATION COMPLIANCE */
$total_seg_records = array_sum($seg_data);
$segregated_count = 0;

foreach ($seg_labels as $i => $label) {
    if ($label === 'Segregate') {
        $segregated_count += $seg_data[$i];
    }
}

$seg_compliance = $total_seg_records > 0 
    ? round(($segregated_count / $total_seg_records) * 100, 1) 
    : 0;

/* WADO FILTER LIST */
$wado_list = [];
$wado_list_sql = "
SELECT w.id, w.name
FROM mf_wado w
JOIN mf_panchayat mp ON mp.id = w.panchayat_id
JOIN mf_taluka mt ON mt.id = mp.taluka_id
ORDER BY w.name
";
$res = $conn->query($wado_list_sql);
while($r = $res->fetch_assoc()){
    $wado_list[] = $r;
}

/* WADO SEGREGATION */
$sql_wado_seg = "
SELECT 
    w.id,
    w.name,
    COUNT(DISTINCT CASE WHEN msce.household_id IS NOT NULL THEN msce.household_id END) as total,
    COUNT(DISTINCT CASE WHEN msce.household_id IS NOT NULL AND ss.name = 'Segregate' THEN msce.household_id END) as segregated,
    COUNT(DISTINCT CASE WHEN msce.household_id IS NOT NULL AND ss.name != 'Segregate' THEN msce.household_id END) as not_segregated
FROM mf_wado w
JOIN mf_panchayat mp ON mp.id = w.panchayat_id
JOIN mf_taluka mt ON mt.id = mp.taluka_id
LEFT JOIN mf_household mh ON mh.wado_id = w.id AND mh.status = 1
LEFT JOIN (
    SELECT msce1.*
    FROM mf_submit_collection_entry msce1
    INNER JOIN (
        SELECT household_id, MAX(collection_date) as max_date
        FROM mf_submit_collection_entry
        WHERE DATE(collection_date) BETWEEN ? AND ?
        AND home_status_id IN (1,2)
        AND segregation_status_id IS NOT NULL
        GROUP BY household_id
    ) latest
    ON msce1.household_id = latest.household_id 
    AND msce1.collection_date = latest.max_date
) msce ON msce.household_id = mh.id
LEFT JOIN mf_segregation_status ss ON ss.id = msce.segregation_status_id
WHERE 1=1";

// Apply filters dynamically
$bind_params = [];
$bind_types = "";

if ($taluka) {
    $sql_wado_seg .= " AND mt.id = ?";
    $bind_types .= "i";
    $bind_params[] = $taluka;
}
if ($panchayat) {
    $sql_wado_seg .= " AND mp.id = ?";
    $bind_types .= "i";
    $bind_params[] = $panchayat;
}
if ($wado) {
    $sql_wado_seg .= " AND w.id = ?";
    $bind_types .= "i";
    $bind_params[] = $wado;
}

$sql_wado_seg .= " GROUP BY w.id ORDER BY total DESC";

$stmt = $conn->prepare($sql_wado_seg);

// prepend date params
$bind_types = "ss" . $bind_types;
array_unshift($bind_params, $from_date, $to_date);

$stmt->bind_param($bind_types, ...$bind_params);

$stmt->execute();
$res = $stmt->get_result();

$wado_seg_labels = [];
$wado_seg_total = [];
$wado_seg_yes = [];
$wado_seg_no = [];

$seen_wados = [];
while ($r = $res->fetch_assoc()) {
    if (isset($seen_wados[$r['id']])) continue;
    $seen_wados[$r['id']] = true;

    $wado_seg_labels[] = $r['name'];
    $wado_seg_total[] = $r['total'];
    $wado_seg_yes[] = $r['segregated'];
    $wado_seg_no[] = $r['not_segregated'];
}
$stmt->close();
// Preserve full data for debug validation
$wado_seg_labels_full = $wado_seg_labels;
$wado_seg_total_full = $wado_seg_total;
$wado_seg_yes_full = $wado_seg_yes;
$wado_seg_no_full = $wado_seg_no;
// Limit to top 100 for readability
$limit = 100;
$wado_seg_labels = array_slice($wado_seg_labels, 0, $limit);
$wado_seg_total = array_slice($wado_seg_total, 0, $limit);
$wado_seg_yes = array_slice($wado_seg_yes, 0, $limit);
$wado_seg_no = array_slice($wado_seg_no, 0, $limit);
// WADO SEGREGATION FIX: Ensure chart always has data
if (empty($wado_seg_labels)) {
    $wado_seg_labels = ['No Data'];
    $wado_seg_total = [0];
    $wado_seg_yes = [0];
    $wado_seg_no = [0];
}
if ($debug_mode) {
    echo "<div style='background:#111;color:#0f0;padding:10px;font-size:12px;'>";
    echo "DEBUG Wado Seg Rows: ".count($wado_seg_labels)."<br>";
    echo "</div>";
}

/* ================================
   DETAILED EXPORT (NEW FEATURE)
================================ */
if (isset($_GET['export']) && $_GET['export'] == 'detailed') {

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=collection_detailed_{$from_date}_to_{$to_date}.csv");

$output = fopen("php://output", "w");

// Header row (UPDATED FORMAT)
fputcsv($output, [
    'Sr.No',
    'Date',
    'Time',
    'User Name',
    'Panchayat',
    'Wado',
    'House No.',
    'Head of family',
    'QRCode',
    'Old QR Code',
    'Type',
    'Subtype',
    'Status',
    'Segregation Status',
    'Remark',
    'Latitude',
    'Longitude',
    'New QR Code',
    'Action',
    'Action By',
    'Action Date',
    'Household Latitude',
    'Household Longitude',
    'Household Location'
]);

// NEW: Household-based logic, only latest valid collection per household in range (or blank if none)
$sql = "
SELECT 
DATE_FORMAT(msce.date, '%Y-%m-%d') as collection_date,
TIME(msce.date) as time,
CONCAT(u.fname,' ',u.lname) as user_name,
mp.name as panchayat,
w.name as wado,
mh.hno,
mh.name as head_name,
mh.qr_code,
mh.old_qr_code,
COALESCE(t.name,'Not Defined') as type,
COALESCE(st.name,'Not Defined') as subtype,
CASE 
WHEN msce.home_status_id = 1 THEN 'Open'
WHEN msce.home_status_id = 2 THEN 'Closed'
ELSE ''
END as status,
CONCAT(COALESCE(ss.name,''),' / ',COALESCE(sss.name,'')) as segregation_status,
msce.remark,
msce.latitude,
msce.longitude,
mh.qr_code as new_qr,
mh.action,
CONCAT(ua.fname,' ',ua.lname) as action_by_name,
mh.action_ts,
mh.latitude as hh_lat,
mh.longitude as hh_lng,
mh.location as hh_location

FROM mf_household mh
JOIN mf_wado w ON w.id = mh.wado_id
JOIN mf_panchayat mp ON mp.id = w.panchayat_id
JOIN mf_taluka mt ON mt.id = mp.taluka_id

LEFT JOIN (
    SELECT msce1.*
    FROM mf_submit_collection_entry msce1
    INNER JOIN (
        SELECT household_id, MAX(date) as max_date
        FROM mf_submit_collection_entry
        WHERE DATE(date) BETWEEN ? AND ?
        AND home_status_id IN (1,2)
        AND segregation_status_id IS NOT NULL
        GROUP BY household_id
    ) latest
    ON msce1.household_id = latest.household_id
    AND msce1.date = latest.max_date
) msce ON msce.household_id = mh.id

LEFT JOIN mf_segregation_status ss ON ss.id = msce.segregation_status_id
LEFT JOIN mf_segregation_sub_status sss ON sss.id = msce.segregation_sub_status_id
LEFT JOIN mf_household_subtype st ON st.id = mh.subtype_id
LEFT JOIN mf_household_type t ON t.id = st.type_id
LEFT JOIN mf_user u ON u.id = msce.user_id
LEFT JOIN mf_user ua ON ua.id = mh.action_by

WHERE mh.status = 1
";
if ($wado) {
    $sql .= " AND w.id = ?";
}
if ($taluka) {
    $sql .= " AND mt.id = ?";
}
if ($panchayat) {
    $sql .= " AND mp.id = ?";
}
$sql .= " ORDER BY collection_date DESC";

// Prepare binding
$export_params = [$from_date, $to_date];
$export_types = "ss";
if ($wado) {
    $export_types .= "i";
    $export_params[] = $wado;
}
if ($taluka) {
    $export_types .= "i";
    $export_params[] = $taluka;
}
if ($panchayat) {
    $export_types .= "i";
    $export_params[] = $panchayat;
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($export_types, ...$export_params);
$stmt->execute();
$res = $stmt->get_result();

$sr = 1;

while ($row = $res->fetch_assoc()) {
    fputcsv($output, [
        $sr++,
        safe_csv($row['collection_date']),
        safe_csv($row['time']),
        safe_csv($row['user_name']),
        safe_csv($row['panchayat']),
        safe_csv($row['wado']),
        safe_csv($row['hno']),
        safe_csv($row['head_name']),
        safe_csv($row['qr_code']),
        safe_csv($row['old_qr_code']),
        safe_csv($row['type']),
        safe_csv($row['subtype']),
        safe_csv($row['status']),
        safe_csv($row['segregation_status']),
        safe_csv($row['remark']),
        safe_csv($row['latitude']),
        safe_csv($row['longitude']),
        safe_csv($row['new_qr']),
        safe_csv($row['action']),
        safe_csv($row['action_by_name']),
        safe_csv($row['action_ts']),
        safe_csv($row['hh_lat']),
        safe_csv($row['hh_lng']),
        safe_csv($row['hh_location'])
    ]);
}

fclose($output);
exit;
}

// $conn->close(); // REMOVED: DB is used after this for dropdowns etc.

?>

<!DOCTYPE html>
<html>
<head>
<title>Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>


<body class="p-4 bg-light">

<?php if($debug_mode): ?>
<div class="card p-3 mb-3 border-danger">
<h5 class="text-danger">DEBUG PANEL</h5>

<div class="row">

<div class="col-md-3">
<strong>From:</strong> <?= $from_date ?><br>
<strong>To:</strong> <?= $to_date ?><br>
<strong>Wado:</strong> <?= $wado ? $wado : 'All' ?>
</div>

<div class="col-md-3">
<strong>Total Households:</strong> <?= number_format($total_households) ?><br>
<strong>Total Collections:</strong> <?= number_format($kpi['total_collections']) ?><br>
<strong>Serviced Households:</strong> <?= number_format($kpi['serviced_households']) ?><br>
<strong>Last Collection:</strong> <?= $kpi['last_collection'] ?? 'N/A' ?>
</div>

<div class="col-md-3">
<strong>Collection Compliance %:</strong> <?= $collection_compliance ?>%<br>
<strong>Seg Labels:</strong><br>
<?php foreach($seg_labels as $i=>$label){ echo htmlspecialchars($label)." (".$seg_data[$i].")<br>"; } ?>
<!-- Additional Segregation Validation -->
<strong>Total Seg Records:</strong> <?= $total_seg_records ?><br>
<strong>Segregated Count:</strong> <?= $segregated_count ?><br>
<strong>Seg Compliance % (recalc):</strong> <?= $total_seg_records>0 ? round(($segregated_count/$total_seg_records)*100,1) : 0 ?>%<br>
</div>

<div class="col-md-3">
<strong>Wado Count:</strong> <?= count($wado_labels) ?><br>
<strong>Wado Seg Count (Full):</strong> <?= count($wado_seg_total_full) ?><br>
<?php
$duplicate_names = count($wado_seg_labels_full) - count(array_unique($wado_seg_labels_full));
echo "<strong>Duplicate Wado Names:</strong> ".$duplicate_names."<br>";
?>
<strong>Wado Seg Count (Displayed):</strong> <?= count($wado_seg_labels) ?><br>
<?php
$inactive_wados = count($wado_seg_total_full) - count($wado_labels);
echo "<strong>Inactive Wados (No Collection):</strong> ".$inactive_wados."<br>";
?>
<strong>Wado Seg Data:</strong><br>
<?php foreach($wado_seg_labels as $i=>$label){ 
    $pct = $wado_seg_total[$i] > 0 ? round(($wado_seg_yes[$i] / $wado_seg_total[$i]) * 100,1) : 0;
    if($pct > 100 || $pct < 0){
        echo "<span style='color:red;'>⚠️ INVALID %</span> ";
    }
    echo htmlspecialchars($label)." (".$pct."%)<br>"; 
} ?>
<!-- Wado Seg Totals Check -->
<br><strong>Wado Seg Totals Check:</strong><br>
<?php 
$total_wado_households = array_sum($wado_seg_total_full);
$total_wado_seg = array_sum($wado_seg_yes_full);
$total_wado_non = array_sum($wado_seg_no_full);

echo "Total (sum): ".$total_wado_households."<br>";
echo "Segregated (sum): ".$total_wado_seg."<br>";
echo "Not Segregated (sum): ".$total_wado_non."<br>";
echo "Check (seg + non == total): ".(($total_wado_seg+$total_wado_non)==$total_wado_households ? 'OK' : 'MISMATCH')."<br>";
?>

<!-- Performance Snapshot -->
<br><strong>Performance Snapshot:</strong><br>
<?php
$wado_perf = [];
foreach($wado_seg_labels_full as $i=>$name){
    $total = $wado_seg_total_full[$i];
    $seg = $wado_seg_yes_full[$i];
    $pct = $total > 0 ? ($seg/$total)*100 : 0;
    $wado_perf[$name] = $pct;
}
arsort($wado_perf);
$top = array_slice($wado_perf, 0, 3, true);
asort($wado_perf);
$bottom = array_slice($wado_perf, 0, 3, true);

echo "Top 3 Wados:<br>";
foreach($top as $n=>$p){ echo "$n (".round($p,1)."%)<br>"; }

echo "<br>Bottom 3 Wados:<br>";
foreach($bottom as $n=>$p){ echo "$n (".round($p,1)."%)<br>"; }
?>

<!-- Cross Checks -->
<br><strong>Cross Checks:</strong><br>
<?php
$diff = $kpi['serviced_households'] - $total_seg_records;
echo "Serviced vs Seg Records Diff: ".$diff."<br>";

echo "Households vs Wado Total Diff: ".($total_households - $total_wado_households)."<br>";
?>

<!-- Zero Collection Household Check -->
<br><strong>Zero Collection Households:</strong><br>
<?php
echo "Unserviced Households: ".($total_households - $kpi['serviced_households'])."<br>";
?>

<!-- Data Quality -->
<br><strong>Data Quality:</strong><br>
<?php
$null_seg = 0;
foreach($wado_seg_no_full as $v){ if($v===null) $null_seg++; }
echo "Null Seg Entries: ".$null_seg."<br>";

echo "Zero Household Wados: ".count(array_filter($wado_seg_total_full, fn($v)=>$v==0))."<br>";
?>

<!-- Filter Validation -->
<br><strong>Filter Validation:</strong><br>
<?php
echo "From Date: $from_date<br>";
echo "To Date: $to_date<br>";
?>
</div>

</div>
</div>
<?php endif; ?>

<div class="card p-3 mb-4 shadow-sm">
<form method="GET" class="row g-3 align-items-end">

<div class="col-md-2">
<label>From Date</label>
<input type="date" name="from_date" value="<?= $from_date ?>" class="form-control">
</div>

<div class="col-md-2">
<label>To Date</label>
<input type="date" name="to_date" value="<?= $to_date ?>" class="form-control">
</div>

<div class="col-md-2">
<label class="form-label">Taluka</label>
<select name="taluka" class="form-control">
<option value="">All</option>
<?php
$res = $conn->query("SELECT id,name FROM mf_taluka ORDER BY name");
while($r = $res->fetch_assoc()){
$sel = (isset($taluka) && $taluka == $r['id']) ? "selected" : "";
echo "<option value='{$r['id']}' $sel>".htmlspecialchars($r['name'])."</option>";
}
?>
</select>
</div>

<div class="col-md-2">
<label class="form-label">Panchayat</label>
<select name="panchayat" class="form-control">
<option value="">All</option>
<?php
$res = $conn->query("SELECT id,name FROM mf_panchayat ORDER BY name");
while($r = $res->fetch_assoc()){
$sel = (isset($panchayat) && $panchayat == $r['id']) ? "selected" : "";
echo "<option value='{$r['id']}' $sel>".htmlspecialchars($r['name'])."</option>";
}
?>
</select>
</div>

<div class="col-md-2">
<label class="form-label">Wado</label>
<select name="wado" class="form-control">
<option value="">All</option>
<?php
foreach($wado_list as $w){
$sel = ($wado == $w['id']) ? "selected" : "";
echo "<option value='{$w['id']}' $sel>".htmlspecialchars($w['name'])."</option>";
}
?>
</select>
</div>

<div class="col-md-2">
<button type="submit" class="btn btn-primary w-100">
Apply Filters
</button>
</div>

<div class="col-md-2">
<a href="admin.php" class="btn btn-secondary w-100">
Clear Filters
</a>
</div>

<div class="col-md-2">
<a href="?export=detailed&from_date=<?= htmlspecialchars($from_date) ?>&to_date=<?= htmlspecialchars($to_date) ?><?= $wado ? '&wado='.$wado : '' ?><?= $taluka ? '&taluka='.$taluka : '' ?><?= $panchayat ? '&panchayat='.$panchayat : '' ?>" class="btn btn-dark w-100 mt-1">
⬇ Detailed Report
</a>
</div>

</form>
</div>

<div class="d-flex justify-content-between mb-4">
<h3><?= htmlspecialchars(ucfirst($_SESSION['admin_user'])) ?> Admin Dashboard</h3>
<a href="?logout=1" class="btn btn-danger">Logout</a>
</div>

<div class="row mb-3">

<div class="col-md-2">
<div class="card p-2 text-center shadow-sm">
<small>Total Households</small>
<h4><?= number_format($total_households); ?></h4>
</div>
</div>

<div class="col-md-2">
<div class="card p-2 text-center shadow-sm">
<small>Total Collections</small>
<h4><?= number_format($kpi['total_collections']); ?></h4>
</div>
</div>

<div class="col-md-2">
<div class="card p-2 text-center shadow-sm">
<small>Serviced Households</small>
<h4><?= number_format($kpi['serviced_households']); ?></h4>
</div>
</div>

<div class="col-md-2">
<div class="card p-2 text-center shadow-sm">
<small>Last Collection</small>
<h4><?= $kpi['last_collection'] ?? 'N/A'; ?></h4>
</div>
</div>

<div class="col-md-2">
<div class="card p-2 text-center shadow-sm">
<small>Collection Service Compliance</small>
<h4><?= $collection_compliance ?>%</h4>
</div>
</div>

</div>

<div class="row g-3">

<div class="col-md-12">
<div class="card p-3 shadow-sm h-100">
<h6 class="text-center">Wado Wise Collection</h6>
<div style="overflow-x:auto;">
<canvas id="wadoChart" style="height:<?= max(500, count($wado_labels)*20) ?>px; min-width:<?= max(1000, count($wado_labels)*60) ?>px"></canvas>
</div>
</div>
</div>

<div class="col-md-12">
<div class="row g-3">

<div class="col-md-6">
<div class="card p-3 shadow-sm h-100">
<h6 class="text-center">Monthly Collection Service Trend</h6>
<div style="overflow-x:auto;">
<canvas id="monthlyChart" style="height:350px; min-width:<?= max(600, count($months)*80) ?>px"></canvas>
</div>
</div>
</div>

<div class="col-md-6">
<div class="card p-3 shadow-sm h-100">
<h6 class="text-center">Segregation Level</h6>
<canvas id="segChart" style="height:250px"></canvas>
</div>
</div>

</div>
</div>

</div>

<div class="row mt-3">
<div class="col-md-12">
<div class="card p-3 shadow-sm">
<h6 class="text-center">Wado Wise Segregation Count</h6>
<div style="overflow-x:auto;">
<canvas id="wadoSegChart" style="height:<?= max(500, count($wado_seg_labels)*20) ?>px; min-width:<?= max(1000, count($wado_seg_labels)*60) ?>px"></canvas>
</div>
</div>
</div>
</div>

<script>

Chart.register({
    id: 'valueLabels',
    afterDatasetsDraw(chart) {
        // VALUE LABEL CLUTTER FIX
        if (chart.data.labels.length > 30) return;
        const {ctx} = chart;
        chart.data.datasets.forEach((dataset, i) => {
            const meta = chart.getDatasetMeta(i);
            meta.data.forEach((bar, index) => {
                const value = dataset.data[index];
                ctx.fillStyle = '#000';
                ctx.font = '10px Arial';
                ctx.textAlign = 'center';
                ctx.fillText(value, bar.x, bar.y - (value > 0 ? 5 : -10));
            });
        });
    }
});

new Chart(document.getElementById('monthlyChart'), {
type: 'bar',
data: {
labels: <?= json_encode($months); ?>,
datasets: [
{
label: 'Serviced Households',
data: <?= json_encode($serviced_data); ?>,
backgroundColor: '#4e73df'
},
{
label: 'Total Households',
data: Array(<?= count($serviced_data) ?>).fill(<?= $total_households ?>),
backgroundColor: '#1cc88a'
}
]
},
options: { responsive: true }
});

new Chart(document.getElementById('wadoChart'), {
type: 'bar',
data: {
labels: <?= json_encode($wado_labels); ?>,
datasets: [
{ label: 'Serviced', data: <?= json_encode($wado_serviced); ?> },
{ label: 'Total Households', data: <?= json_encode($wado_total); ?> }
]
},
options: {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { position: 'top' },
        tooltip: { mode: 'index', intersect: false }
    },
    scales: {
        x: { ticks: { font: { size: 11 } } },
        y: { beginAtZero: true }
    }
}
});

new Chart(document.getElementById('segChart'), {
type: 'pie',
data: {
labels: <?= json_encode($seg_labels); ?>,
datasets: [{
data: <?= json_encode($seg_data); ?>
}]
},
options: {
plugins: {
tooltip: {
callbacks: {
label: function(context) {
let total = context.dataset.data.length ? context.dataset.data.reduce((a,b)=>a+b,0) : 0;
let value = context.raw;
let pct = total > 0 ? ((value/total)*100).toFixed(1) : 0;
return context.label + ": " + value + " (" + pct + "%)";
}
}
}
}
}
});

new Chart(document.getElementById('wadoSegChart'), {
type: 'bar',
data: {
labels: <?= json_encode($wado_seg_labels); ?>,
datasets: [
{
label: 'Total',
data: <?= json_encode($wado_seg_total); ?>,
backgroundColor: '#4e73df'
},
{
label: 'Segregate',
data: <?= json_encode($wado_seg_yes); ?>,
backgroundColor: '#1cc88a'
},
{
label: 'Do Not Segregate',
data: <?= json_encode($wado_seg_no); ?>,
backgroundColor: '#e74a3b'
}
]
},
options: {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { position: 'top' },
        tooltip: { mode: 'index', intersect: false }
    },
    scales: {
        x: { ticks: { font: { size: 11 } } },
        y: { beginAtZero: true }
    }
}
});

</script>

</body>
</html>