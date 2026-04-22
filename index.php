<?php
session_start();

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

/* ================================
   HARDCODED USERS
================================ */
$users = [
    'bicholim' => ['password' => 'bicho123', 'panchayat_id' => 225],
    'sankhali' => ['password' => 'sank123', 'panchayat_id' => 227],

    'bhironda' => ['password' => 'bhironda123', 'panchayat_id' => 191],
    'keri' => ['password' => 'keri123', 'panchayat_id' => 199],
    'morlem' => ['password' => 'morlem123', 'panchayat_id' => 190],
    'pissurlem' => ['password' => 'pissurlem123', 'panchayat_id' => 195],
    'honda' => ['password' => 'honda123', 'panchayat_id' => 198],
    'poriem' => ['password' => 'poriem123', 'panchayat_id' => 200],
    'nagargaon' => ['password' => 'nagargaon123', 'panchayat_id' => 189],
    'dongurli' => ['password' => 'dongurli123', 'panchayat_id' => 193],
    'mauxi' => ['password' => 'mauxi123', 'panchayat_id' => 188],
    'guleli_sattari' => ['password' => 'guleli123', 'panchayat_id' => 187],
    'savordem' => ['password' => 'savordem123', 'panchayat_id' => 181],
    'cotorem' => ['password' => 'cotorem123', 'panchayat_id' => 192],
    'valpoi_council' => ['password' => 'valpoi123', 'panchayat_id' => 224],

    'surla' => ['password' => 'surla123', 'panchayat_id' => 219],
    'pale' => ['password' => 'pale123', 'panchayat_id' => 205],
    'velguem' => ['password' => 'velguem123', 'panchayat_id' => 215],
    'navelim' => ['password' => 'navelim123', 'panchayat_id' => 206],
    'harvalem' => ['password' => 'harvalem123', 'panchayat_id' => 201],
    'cudnem' => ['password' => 'cudnem123', 'panchayat_id' => 220],
    'amona' => ['password' => 'amona123', 'panchayat_id' => 214],
    'sarvan' => ['password' => 'sarvan123', 'panchayat_id' => 221],
    'piligao' => ['password' => 'piligao123', 'panchayat_id' => 204],
    'naroa' => ['password' => 'naroa123', 'panchayat_id' => 203],
    'sirigao' => ['password' => 'sirigao123', 'panchayat_id' => 194],
    'mayem' => ['password' => 'mayem123', 'panchayat_id' => 216],
    'ona_maulinguem' => ['password' => 'ona123', 'panchayat_id' => 213],
    'mulgaon' => ['password' => 'mulgaon123', 'panchayat_id' => 197],
    'salem' => ['password' => 'salem123', 'panchayat_id' => 196],
    'mencurem' => ['password' => 'mencurem123', 'panchayat_id' => 180],
    'latambarcem' => ['password' => 'latambarcem123', 'panchayat_id' => 222],
    'adwalpal' => ['password' => 'adwalpal123', 'panchayat_id' => 177],
    'bicholim_council' => ['password' => 'bicholim123', 'panchayat_id' => 225],
    'sanquelim_council' => ['password' => 'sanquelim123', 'panchayat_id' => 227]
];

/* ================================
   LOGIN
================================ */
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (isset($users[$username]) && $users[$username]['password'] === $password) {
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['panchayat_id'] = $users[$username]['panchayat_id'];
    } else {
        $error = "Invalid credentials";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

/* ================================
   SHOW LOGIN IF NOT AUTHENTICATED
================================ */
if (!isset($_SESSION['logged_in'])):
?>
<!DOCTYPE html>
<html>
<head>
<title>Panchayat Dashboard Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex justify-content-center align-items-center vh-100 bg-light">

<div class="card p-4 shadow" style="width:350px;">
<h4 class="mb-3 text-center">Panchayat Login</h4>

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

$panchayat_id = $_SESSION['panchayat_id'];
if (!isset($_GET['from_date']) && !isset($_GET['to_date'])) {
    $from_date = date('Y-m-01', strtotime('-2 months'));
    $to_date = date('Y-m-t');
} else {
    $from_date = $_GET['from_date'];
    $to_date = $_GET['to_date'];
}

$wado = isset($_GET['wado']) ? intval($_GET['wado']) : null;

/* ================================
   BUILD WHERE
================================ */
$where = ["DATE(msce.collection_date) BETWEEN ? AND ?", "mp.id = ?"];
$params = [$from_date, $to_date, $panchayat_id];
$types = "ssi";

if ($wado) {
    $where[] = "w.id = ?";
    $params[] = $wado;
    $types .= "i";
}

$where_sql = implode(" AND ", $where);

/* ================================
   CSV INJECTION PROTECTION
================================ */
function safe_csv($value){
    if ($value === null) return '';
    return preg_replace('/^[-+=@]/', "'$0", $value);
}

/* ================================
   EXPORT TO EXCEL
================================ */

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
WHERE $where_sql
AND msce.home_status_id IN (1,2)
AND msce.segregation_status_id IS NOT NULL
";

$stmt = $conn->prepare($sql_kpi);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$kpi = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* TOTAL HOUSEHOLDS KPI */
$sql_total_households = "
SELECT COUNT(*) as total_households
FROM mf_household mh
JOIN mf_wado w ON w.id = mh.wado_id
JOIN mf_panchayat mp ON mp.id = w.panchayat_id
WHERE mh.status = 1 AND mp.id = ?
";

$stmt = $conn->prepare($sql_total_households);
$stmt->bind_param("i", $panchayat_id);
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
SELECT 
MONTH(msce.collection_date) AS month,
COUNT(DISTINCT msce.household_id) AS serviced,
(SELECT COUNT(*) FROM mf_household mh2 
 JOIN mf_wado w2 ON w2.id = mh2.wado_id
 WHERE mh2.status = 1 AND w2.panchayat_id = mp.id) AS total_households
FROM mf_submit_collection_entry msce
JOIN mf_household mh ON mh.id = msce.household_id
JOIN mf_wado w ON w.id = mh.wado_id
JOIN mf_panchayat mp ON mp.id = w.panchayat_id
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
$total_household_trend = [];
$month_names = [
1=>"Jan",2=>"Feb",3=>"Mar",4=>"Apr",
5=>"May",6=>"Jun",7=>"Jul",8=>"Aug",
9=>"Sep",10=>"Oct",11=>"Nov",12=>"Dec"
];

$month_range = [];
$start = strtotime($from_date);
$end = strtotime($to_date);

while ($start <= $end) {
    $m = date('n', $start);
    $month_range[$m] = ['serviced' => 0, 'total' => 0];
    $start = strtotime('+1 month', $start);
}

while ($r = $res->fetch_assoc()) {
    $month_range[$r['month']] = [
        'serviced' => (int)$r['serviced'],
        'total' => (int)$r['total_households']
    ];
}

$months = [];
$serviced_data = [];
$total_household_trend = [];

foreach ($month_range as $m => $val) {
    $months[] = $month_names[$m];
    $serviced_data[] = $val['serviced'];
    $total_household_trend[] = $val['total'];
}
$stmt->close();

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

$wado_labels = [];
$wado_serviced = [];
$wado_total = [];
while ($r = $res->fetch_assoc()) {
    $wado_labels[] = $r['name'];
    $wado_serviced[] = $r['serviced'];
    $wado_total[] = $r['total_households'];
}
$stmt->close();

// WADO BREAKDOWN FIX
if (empty($wado_labels)) {
    $wado_labels = ['No Data'];
    $wado_serviced = [0];
    $wado_total = [0];
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

$seg_labels = [];
$seg_data = [];
while ($r = $res->fetch_assoc()) {
    $seg_labels[] = $r['name'];
    $seg_data[] = $r['total'];
}
$stmt->close();

// SEGREGATION PIE FIX
if (empty($seg_labels)) {
    $seg_labels = ['No Data'];
    $seg_data = [0];
}

/* CALCULATE SEGREGATION COMPLIANCE */
$total_seg_records = array_sum($seg_data);
$segregated_count = 0;

foreach ($seg_labels as $i => $label) {
    if (strtolower($label) === 'segregate') {
        $segregated_count += $seg_data[$i];
    }
}

$seg_compliance = $total_seg_records > 0 
    ? round(($segregated_count / $total_seg_records) * 100, 1) 
    : 0;

/* WADO FILTER LIST */
$wado_list = [];
$stmt = $conn->prepare("
SELECT w.id, w.name
FROM mf_wado w
JOIN mf_panchayat mp ON mp.id = w.panchayat_id
WHERE mp.id = ?
ORDER BY w.name
");
$stmt->bind_param("i",$panchayat_id);
$stmt->execute();
$res = $stmt->get_result();
while($r = $res->fetch_assoc()){
    $wado_list[] = $r;
}
$stmt->close();

/* WADO SEGREGATION */
// WADO SEG FIX
// WADO SEG FIX
 $sql_wado_seg = "
SELECT 
    w.name,
    COUNT(DISTINCT mh.id) as total,
    COUNT(DISTINCT CASE WHEN ss.name = 'Segregate' THEN mh.id END) as segregated,
    COUNT(DISTINCT CASE 
        WHEN msce.household_id IS NOT NULL 
        AND (ss.name != 'Segregate' OR ss.name IS NULL)
        THEN mh.id 
    END) as not_segregated
FROM mf_wado w
JOIN mf_panchayat mp ON mp.id = w.panchayat_id
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
WHERE mp.id = ?";

// Apply wado filter
if ($wado) {
    $sql_wado_seg .= " AND w.id = ?";
}

$sql_wado_seg .= " GROUP BY w.id ORDER BY total DESC";

$stmt = $conn->prepare($sql_wado_seg);

if ($wado) {
    $stmt->bind_param("ssii", $from_date, $to_date, $panchayat_id, $wado);
} else {
    $stmt->bind_param("ssi", $from_date, $to_date, $panchayat_id);
}

$stmt->execute();
$res = $stmt->get_result();

$wado_seg_labels = [];
$wado_seg_total = [];
$wado_seg_yes = [];
$wado_seg_no = [];

while ($r = $res->fetch_assoc()) {
    $wado_seg_labels[] = $r['name'];
    $wado_seg_total[] = $r['total'];
    $wado_seg_yes[] = $r['segregated'];
    $wado_seg_no[] = $r['not_segregated'];
}
$stmt->close();

// Preserve full data for debug
$wado_seg_labels_full = $wado_seg_labels;
$wado_seg_total_full = $wado_seg_total;
$wado_seg_yes_full = $wado_seg_yes;
$wado_seg_no_full = $wado_seg_no;

// Limit for readability
$limit = 100;
$wado_seg_labels = array_slice($wado_seg_labels, 0, $limit);
$wado_seg_total = array_slice($wado_seg_total, 0, $limit);
$wado_seg_yes = array_slice($wado_seg_yes, 0, $limit);
$wado_seg_no = array_slice($wado_seg_no, 0, $limit);

// WADO SEG FIX
if (empty($wado_seg_labels)) {
    $wado_seg_labels = ['No Data'];
    $wado_seg_total = [0];
    $wado_seg_yes = [0];
    $wado_seg_no = [0];
}

/* ================================
   DETAILED EXPORT (NEW FEATURE)
================================ */
if (isset($_GET['export']) && $_GET['export'] == 'detailed') {

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=collection_detailed_{$from_date}_to_{$to_date}.csv");

$output = fopen("php://output", "w");

// Header row
fputcsv($output, [
    'SR NO','Date','Time','User Name','Panchayat','Wado',
    'House No','Head of Family','QRCode',
    'Type','Subtype','Status',
    'Segregation Status',
    'Latitude','Longitude','New QR Code',
    'Action','Action By','Action Date',
    'Household Latitude','Household Longitude','Household Location'
]);

// DETAILED EXPORT: RAW COLLECTION DATA ONLY (NO household × date expansion)
$sql = "
SELECT 
DATE_FORMAT(msce.collection_date, '%d-%m-%Y') as date,
TIME(msce.collection_date) as time,
CONCAT(u.fname, ' ', u.lname) as user_name,
mp.name as panchayat,
w.name as wado,
mh.hno as house_no,
mh.name as head_of_family,
CASE 
    WHEN mh.action IN ('LINK_EXISTING_HOUSEHOLD','LINK_HOUSEHOLD') 
    THEN mh.old_qr_code 
    ELSE mh.qr_code 
END as qr_code,
COALESCE(t.name,'') as type,
COALESCE(st.name,'') as subtype,
CASE 
    WHEN msce.home_status_id = 1 THEN 'Open'
    WHEN msce.home_status_id = 2 THEN 'Closed'
    ELSE ''
END as status,
CASE
    WHEN ss.name IS NULL AND sss.name IS NULL THEN ''
    WHEN ss.name IS NOT NULL AND sss.name IS NOT NULL THEN CONCAT(ss.name,' / ',sss.name)
    WHEN ss.name IS NOT NULL THEN ss.name
    ELSE sss.name
END as segregation_status,
msce.latitude,
msce.longitude,
CASE 
    WHEN mh.action IN ('LINK_EXISTING_HOUSEHOLD','LINK_HOUSEHOLD') 
    THEN mh.qr_code 
    ELSE '' 
END as new_qr_code,
mh.action,
CONCAT(ua.fname, ' ', ua.lname) as action_by,
mh.action_ts as action_date,
mh.latitude as household_latitude,
mh.longitude as household_longitude,
mh.location as household_location

FROM mf_submit_collection_entry msce
JOIN mf_household mh ON mh.id = msce.household_id
JOIN mf_wado w ON w.id = mh.wado_id
JOIN mf_panchayat mp ON mp.id = w.panchayat_id
LEFT JOIN mf_household_subtype st ON st.id = mh.subtype_id
LEFT JOIN mf_household_type t ON t.id = st.type_id
LEFT JOIN mf_segregation_status ss ON ss.id = msce.segregation_status_id
LEFT JOIN mf_segregation_sub_status sss ON sss.id = msce.segregation_sub_status_id
LEFT JOIN mf_user u ON u.id = msce.user_id
LEFT JOIN mf_user ua ON ua.id = mh.action_by

WHERE DATE(msce.collection_date) BETWEEN ? AND ?
AND mp.id = ?
AND msce.segregation_status_id IS NOT NULL
";

if ($wado) {
    $sql .= " AND w.id = ?";
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL Prepare Failed: " . $conn->error);
}

if ($wado) {
    $stmt->bind_param("ssii", $from_date, $to_date, $panchayat_id, $wado);
} else {
    $stmt->bind_param("ssi", $from_date, $to_date, $panchayat_id);
}
if (!$stmt->execute()) {
    die("SQL Execute Failed: " . $stmt->error);
}
$res = $stmt->get_result();
if (!$res) {
    die("Get Result Failed: " . $stmt->error);
}

// CSV row mapping
$sr = 1;
while ($row = $res->fetch_assoc()) {
    fputcsv($output, [
        $sr++,
        safe_csv($row['date']),
        safe_csv($row['time']),
        safe_csv($row['user_name']),
        safe_csv($row['panchayat']),
        safe_csv($row['wado']),
        safe_csv($row['house_no']),
        safe_csv($row['head_of_family']),
        safe_csv($row['qr_code']),
        safe_csv($row['type']),
        safe_csv($row['subtype']),
        safe_csv($row['status']),
        safe_csv($row['segregation_status']),
        safe_csv($row['latitude']),
        safe_csv($row['longitude']),
        safe_csv($row['new_qr_code']),
        safe_csv($row['action']),
        safe_csv($row['action_by']),
        safe_csv($row['action_date']),
        safe_csv($row['household_latitude']),
        safe_csv($row['household_longitude']),
        safe_csv($row['household_location'])
    ]);
}

// -----------------------------
// UNSERVICED HOUSEHOLDS BLOCK
// -----------------------------

fputcsv($output, []);
fputcsv($output, ['UNSERVICED HOUSEHOLDS']);
fputcsv($output, []);

$sr = 1;

// Build UNSERVICED SQL to match admin logic exactly
$sql_unserviced = "
SELECT 
mp.name as panchayat,
w.name as wado,
mh.hno as house_no,
mh.name as head_of_family,
mh.qr_code,
COALESCE(t.name,'') as type,
COALESCE(st.name,'') as subtype,
mh.action,
CONCAT(ua.fname, ' ', ua.lname) as action_by,
mh.action_ts as action_date,
mh.latitude as household_latitude,
mh.longitude as household_longitude,
mh.location as household_location

FROM mf_household mh
JOIN mf_wado w ON w.id = mh.wado_id
JOIN mf_panchayat mp ON mp.id = w.panchayat_id
LEFT JOIN mf_household_subtype st ON st.id = mh.subtype_id
LEFT JOIN mf_household_type t ON t.id = st.type_id
LEFT JOIN mf_user ua ON ua.id = mh.action_by

LEFT JOIN (
    SELECT DISTINCT household_id
    FROM mf_submit_collection_entry
    WHERE DATE(collection_date) BETWEEN ? AND ?
    AND segregation_status_id IS NOT NULL
) serviced ON serviced.household_id = mh.id
WHERE mh.status = 1
AND mp.id = ?
";
// Param binding for UNSERVICED block
$un_params = [$from_date, $to_date];
$un_types = "ss";

if ($wado) {
    $sql_unserviced .= " AND w.id = ?";
    $un_types .= "i";
    $un_params[] = $wado;
}

// Panchayat filter (always present)
$sql_unserviced .= " AND mp.id = ?";
$un_types .= "i";
$un_params[] = $panchayat_id;

$stmt2 = $conn->prepare($sql_unserviced);
$stmt2->bind_param($un_types, ...$un_params);
$stmt2->execute();
$res2 = $stmt2->get_result();

while ($row = $res2->fetch_assoc()) {
    fputcsv($output, [
        $sr++,
        '',
        '',
        '',
        safe_csv($row['panchayat']),
        safe_csv($row['wado']),
        safe_csv($row['house_no']),
        safe_csv($row['head_of_family']),
        safe_csv($row['qr_code']),
        safe_csv($row['type']),
        safe_csv($row['subtype']),
        '',
        '',
        '',
        '',
        '',
        safe_csv($row['action']),
        safe_csv($row['action_by']),
        safe_csv($row['action_date']),
        safe_csv($row['household_latitude']),
        safe_csv($row['household_longitude']),
        safe_csv($row['household_location'])
    ]);
}

$stmt2->close();

fclose($output);
exit;
}

$conn->close();

/* DEBUG TOGGLE */
$debug_mode = isset($_GET['debug']) && $_GET['debug'] == 1;
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
<strong>Panchayat ID:</strong> <?= $panchayat_id ?><br>
<strong>From:</strong> <?= $from_date ?><br>
<strong>To:</strong> <?= $to_date ?><br>
<strong>Wado:</strong> <?= $wado ? $wado : 'All' ?><br>
</div>

<div class="col-md-3">
<strong>Total Households:</strong> <?= number_format($total_households) ?><br>
<strong>Total Collections:</strong> <?= number_format($kpi['total_collections']) ?><br>
<strong>Serviced Households:</strong> <?= number_format($kpi['serviced_households']) ?><br>
<strong>Last Collection:</strong> <?= $kpi['last_collection'] ?? 'N/A' ?>
</div>

<div class="col-md-3">
<strong>Collection Compliance %:</strong><br>
= Serviced Households / Total Households<br>
= <?= $kpi['serviced_households'] ?> / <?= $total_households ?><br>
= <?= $collection_compliance ?>%<br>
<strong>Seg Labels:</strong><br>
<?php foreach($seg_labels as $i=>$label){ echo htmlspecialchars($label)." (".$seg_data[$i].")<br>"; } ?>
<!-- RAW SEGREGATION TOTAL CHECK -->
<strong>Total Seg Records:</strong> <?= $total_seg_records ?><br>
<strong>Segregated Count:</strong> <?= $segregated_count ?><br>
<strong>Seg Compliance %:</strong><br>
= Segregated / Total Seg Records<br>
= <?= $segregated_count ?> / <?= $total_seg_records ?><br>
= <?= $total_seg_records>0 ? round(($segregated_count/$total_seg_records)*100,1) : 0 ?>%<br>
</div>

<div class="col-md-3">
<strong>Wado Count:</strong> <?= count($wado_labels) ?><br>
<strong>Wado Seg Count (Full):</strong> <?= count($wado_seg_total_full) ?><br>
<strong>Wado Seg Count (Displayed):</strong> <?= count($wado_seg_labels) ?><br>
<?php
$duplicate_names = count($wado_seg_labels_full) - count(array_unique($wado_seg_labels_full));
echo "<strong>Duplicate Wado Names:</strong> ".$duplicate_names."<br>";
$inactive_wados = count($wado_seg_total_full) - count($wado_labels);
echo "<strong>Inactive Wados (No Collection):</strong> ".$inactive_wados."<br>";
?>
</div>

<div class="col-md-3">
<strong>Wado Seg Data:</strong><br>
<?php foreach($wado_seg_labels as $i=>$label){ 
    $pct = $wado_seg_total[$i] > 0 ? round(($wado_seg_yes[$i] / $wado_seg_total[$i]) * 100,1) : 0;
    if($pct > 100 || $pct < 0){
        echo "<span style='color:red;'>⚠️ INVALID %</span> ";
    }
    echo htmlspecialchars($label)." (".$pct."%)<br>"; 
} ?>
<!-- WADO SEG CONSISTENCY CHECK -->
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
</div>

</div>

<!-- KPI VS SEG CROSS CHECK -->
<br><strong>Cross Checks:</strong><br>
<?php
// serviced households vs seg records
$diff = $kpi['serviced_households'] - $total_seg_records;
echo "Serviced vs Seg Records Diff: ".$diff."<br>";
// households vs wado totals
echo "Households vs Wado Total Diff: ".($total_households - $total_wado_households)."<br>";
?>
<br><strong>Zero Collection Households:</strong><br>
<?php
$unserviced = $total_households - $kpi['serviced_households'];
$unserviced_pct = $total_households > 0 ? round(($unserviced/$total_households)*100,1) : 0;

echo "Unserviced Households:<br>";
echo "= Total - Serviced<br>";
echo "= $total_households - {$kpi['serviced_households']}<br>";
echo "= $unserviced ($unserviced_pct%)<br>";
?>

<!-- EMPTY / NULL DATA CHECKS -->
<br><strong>Data Quality:</strong><br>
<?php
$null_seg = 0;
foreach($wado_seg_no_full as $v){ if($v===null) $null_seg++; }
echo "Null Seg Entries: ".$null_seg."<br>";
echo "Zero Household Wados: ".count(array_filter($wado_seg_total_full, fn($v)=>$v==0))."<br>";
?>

<?php
// TYPE / SUBTYPE DEBUG SAMPLE
$sql_debug_type = "
SELECT 
COALESCE(t.name,'Not Defined') as type,
COALESCE(st.name,'Not Defined') as subtype
FROM mf_household mh
LEFT JOIN mf_household_subtype st ON st.id = mh.subtype_id
LEFT JOIN mf_household_type t ON t.id = st.type_id
LIMIT 1
";
$res_debug = $conn->query($sql_debug_type);

if($res_debug && $row_debug = $res_debug->fetch_assoc()){
    echo "<br><strong>Type/Subtype Sample:</strong><br>";
    echo htmlspecialchars($row_debug['type'])." / ".htmlspecialchars($row_debug['subtype'])."<br>";
}
?>

<!-- DATE RANGE FILTER VALIDATION -->
<br><strong>Date Range Validation:</strong><br>
From: <?= $from_date ?><br>
To: <?= $to_date ?><br>

</div>
</div>
<?php endif; ?>

<div class="card p-3 mb-4 shadow-sm">
<form method="GET" class="row g-3 align-items-end">

<div class="col-md-3">
<label class="form-label">From Date</label>
<input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
</div>

<div class="col-md-3">
<label class="form-label">To Date</label>
<input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
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
<a href="index.php" class="btn btn-secondary w-100">
Clear Filters
</a>
</div>

<div class="col-md-2">
<a href="?export=detailed&from_date=<?= $from_date ?>&to_date=<?= $to_date ?><?= $wado ? '&wado='.$wado : '' ?>" class="btn btn-dark w-100 mt-1">
    ⬇ Detailed Report
</a>
</div>

</form>
</div>

<div class="d-flex justify-content-between mb-4">
<h3><?= htmlspecialchars(ucfirst($_SESSION['username'])) ?> Dashboard</h3>
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
<canvas id="wadoChart" style="min-width:<?= max(1200, count($wado_labels)*60) ?>px; height:<?= max(500, count($wado_labels)*20) ?>px"></canvas>
</div>
</div>
</div>

<div class="col-md-12">
<div class="row g-3">

<div class="col-md-6">
<div class="card p-3 shadow-sm h-100">
<h6 class="text-center">Monthly Collection Service Trend</h6>
<canvas id="monthlyChart" style="height:350px"></canvas>
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
<canvas id="wadoSegChart" style="min-width:<?= max(1200, count($wado_seg_labels)*60) ?>px; height:<?= max(500, count($wado_seg_labels)*20) ?>px"></canvas>
</div>
</div>
</div>
</div>

<script>

Chart.register({
    id: 'valueLabels',
    afterDatasetsDraw(chart) {
        if (chart.data.labels.length > 30) return;
        const {ctx} = chart;
        chart.data.datasets.forEach((dataset, i) => {
            const meta = chart.getDatasetMeta(i);
            meta.data.forEach((bar, index) => {
                const value = dataset.data[index];
                ctx.fillStyle = '#000';
                ctx.font = '10px Arial';
                ctx.textAlign = 'center';
                ctx.fillText(value, bar.x, bar.y - 5);
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
backgroundColor: '#1cc88a'
},
{
label: 'Total Households',
data: <?= json_encode($total_household_trend); ?>,
backgroundColor: '#4e73df'
}
]
},
options: {
    responsive: true,
    scales: {
        y: { beginAtZero: true }
    }
}
});

new Chart(document.getElementById('wadoChart'), {
type: 'bar',
data: {
labels: <?= json_encode($wado_labels); ?>,
datasets: [
{ label: 'Serviced', data: <?= json_encode($wado_serviced); ?>, backgroundColor: '#1cc88a' },
{ label: 'Total Households', data: <?= json_encode($wado_total); ?>, backgroundColor: '#4e73df' }
]
},
options: {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        tooltip: { mode: 'index', intersect: false },
        legend: {
            position: 'top'
        }
    },
    scales: {
        x: {
            ticks: {
                font: { size: 11 }
            }
        },
        y: {
            beginAtZero: true
        }
    }
}
});

new Chart(document.getElementById('segChart'), {
type: 'pie',
data: {
labels: <?= json_encode($seg_labels); ?>,
datasets: [{
data: <?= json_encode($seg_data); ?>,
backgroundColor: ['#1cc88a','#e74a3b','#f6c23e','#36b9cc']
}]
},
options: {
plugins: {
tooltip: {
callbacks: {
label: function(context) {
let total = context.dataset.data.reduce((a,b)=>a+b,0);
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
        tooltip: { mode: 'index', intersect: false },
        legend: {
            position: 'top'
        },
        valueLabels: true
    },
    scales: {
        x: {
            ticks: {
                font: { size: 11 }
            }
        },
        y: {
            beginAtZero: true
        }
    }
}
});

</script>

</body>
</html>