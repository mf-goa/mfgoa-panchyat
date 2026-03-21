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

/* ================================
   HARDCODED USERS
================================ */
$users = [
    'bicholim' => ['password' => 'bicho123', 'panchayat_id' => 225],
    'sankhali' => ['password' => 'sank123', 'panchayat_id' => 227]
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
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$months_filter = isset($_GET['months']) ? $_GET['months'] : [];
$months_filter = array_filter($months_filter, function($m){
    return is_numeric($m) && $m >= 1 && $m <= 12;
});

if (empty($months_filter)) {
    $current_month = date('n');
    $months_filter = [
        $current_month,
        $current_month - 1,
        $current_month - 2
    ];
    $months_filter = array_map(function($m){
        return $m <= 0 ? $m + 12 : $m;
    }, $months_filter);
}

$wado = isset($_GET['wado']) ? intval($_GET['wado']) : null;

/* ================================
   BUILD WHERE
================================ */
$where = ["YEAR(msce.collection_date) = ?", "mp.id = ?"];
$params = [$year, $panchayat_id];
$types = "ii";

if (!empty($months_filter)) {
    $month_placeholders = implode(',', array_fill(0, count($months_filter), '?'));
    $where[] = "MONTH(msce.collection_date) IN ($month_placeholders)";
    foreach ($months_filter as $m) {
        $params[] = intval($m);
        $types .= "i";
    }
}

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
    return preg_replace('/^[-+=@]/', "'$0", $value);
}

/* ================================
   EXPORT TO EXCEL
================================ */
if (isset($_GET['export']) && $_GET['export'] == 'excel') {

    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=wado_report_$year.csv");

    $output = fopen("php://output", "w");

    // BOM for Excel
    fputs($output, "\xEF\xBB\xBF");

    // Header
    fputcsv($output, [
        'Wado',
        'Serviced Households',
        'Total Households'
    ]);

    $sql_export = "
    SELECT 
    w.name,
    COUNT(DISTINCT msce.household_id) AS serviced,
    (SELECT COUNT(*) FROM mf_household WHERE status=1 AND wado_id=w.id) AS total_households
    FROM mf_submit_collection_entry msce
    JOIN mf_household mh ON mh.id = msce.household_id
    JOIN mf_wado w ON w.id = mh.wado_id
    JOIN mf_panchayat mp ON mp.id = w.panchayat_id
    WHERE $where_sql
    GROUP BY w.id
    ";

    $stmt = $conn->prepare($sql_export);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        fputcsv($output, [
            $row['name'],
            $row['serviced'],
            $row['total_households']
        ]);
    }

    fclose($output);
    exit;
}

/* ================================
   KPI QUERY
================================ */
$sql_kpi = "
SELECT 
COUNT(*) AS total_collections,
COUNT(DISTINCT msce.household_id) AS serviced_households,
MAX(msce.collection_date) AS last_collection
FROM mf_submit_collection_entry msce
JOIN mf_household mh ON mh.id = msce.household_id
JOIN mf_wado w ON w.id = mh.wado_id
JOIN mf_panchayat mp ON mp.id = w.panchayat_id
WHERE $where_sql
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
SELECT MONTH(msce.collection_date) AS month,
COUNT(DISTINCT msce.household_id) AS serviced
FROM mf_submit_collection_entry msce
JOIN mf_household mh ON mh.id = msce.household_id
JOIN mf_wado w ON w.id = mh.wado_id
JOIN mf_panchayat mp ON mp.id = w.panchayat_id
WHERE $where_sql
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

while ($r = $res->fetch_assoc()) {
$months[] = $month_names[$r['month']];
$serviced_data[] = $r['serviced'];
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
$stmt->close();

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
$stmt->close();

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
$sql_wado_seg = "
SELECT 
    w.name,
    COUNT(DISTINCT mh.id) as total,
    COUNT(DISTINCT CASE WHEN ss.name = 'Segregate' THEN mh.id END) as segregated,
    COUNT(DISTINCT CASE WHEN ss.name != 'Segregate' OR ss.name IS NULL THEN mh.id END) as not_segregated
FROM mf_wado w
JOIN mf_panchayat mp ON mp.id = w.panchayat_id
LEFT JOIN mf_household mh ON mh.wado_id = w.id AND mh.status = 1
LEFT JOIN (
    SELECT msce1.*
    FROM mf_submit_collection_entry msce1
    INNER JOIN (
        SELECT household_id, MAX(collection_date) as max_date
        FROM mf_submit_collection_entry
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
    $stmt->bind_param("ii", $panchayat_id, $wado);
} else {
    $stmt->bind_param("i", $panchayat_id);
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

// Limit for readability
$limit = 100;
$wado_seg_labels = array_slice($wado_seg_labels, 0, $limit);
$wado_seg_total = array_slice($wado_seg_total, 0, $limit);
$wado_seg_yes = array_slice($wado_seg_yes, 0, $limit);
$wado_seg_no = array_slice($wado_seg_no, 0, $limit);

/* ================================
   DETAILED EXPORT (NEW FEATURE)
================================ */
if (isset($_GET['export']) && $_GET['export'] == 'detailed') {

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=collection_detailed_$year.csv");

$output = fopen("php://output", "w");

// Header row
fputcsv($output, [
    'Date',
    'Panchayat',
    'Wado',
    'Household',
    'Segregation Status',
    'User'
]);

$sql = "
SELECT 
msce.collection_date,
mp.name as panchayat,
w.name as wado,
msce.household_id,
ss.name as segregation_status,
CONCAT(u.fname, ' ', u.lname) as user_name
FROM mf_submit_collection_entry msce
JOIN mf_household mh ON mh.id = msce.household_id
JOIN mf_wado w ON w.id = mh.wado_id
JOIN mf_panchayat mp ON mp.id = w.panchayat_id
LEFT JOIN mf_segregation_status ss ON ss.id = msce.segregation_status_id
LEFT JOIN mf_user u ON u.id = msce.user_id
WHERE $where_sql
ORDER BY msce.collection_date DESC
";

// Prepare statement with SQL error debugging
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL Prepare Failed: " . $conn->error);
}
$stmt->bind_param($types, ...$params);
if (!$stmt->execute()) {
    die("SQL Execute Failed: " . $stmt->error);
}
$res = $stmt->get_result();
if (!$res) {
    die("Get Result Failed: " . $stmt->error);
}

while ($row = $res->fetch_assoc()) {
    fputcsv($output, [
        $row['collection_date'],
        $row['panchayat'],
        $row['wado'],
        $row['household_id'],
        $row['segregation_status'],
        $row['user_name']
    ]);
}

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
<strong>Year:</strong> <?= $year ?><br>
<strong>Wado:</strong> <?= $wado ? $wado : 'All' ?><br>
<strong>Months:</strong> <?= implode(',', $months_filter) ?>
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
</div>

<div class="col-md-3">
<strong>Wado Count:</strong> <?= count($wado_labels) ?><br>
</div>

<div class="col-md-3">
<strong>Wado Seg Data:</strong><br>
<?php foreach($wado_seg_labels as $i=>$label){ 
    $pct = $wado_seg_total[$i] > 0 ? round(($wado_seg_yes[$i] / $wado_seg_total[$i]) * 100,1) : 0;
    echo htmlspecialchars($label)." (".$pct."%)<br>"; 
} ?>
</div>

</div>
</div>
<?php endif; ?>

<div class="card p-3 mb-4 shadow-sm">
<form method="GET" class="row g-3 align-items-end">

<div class="col-md-2">
<label class="form-label">Year</label>
<select name="year" class="form-control">
<?php
$currentYear = date('Y');
for($y=$currentYear;$y>=2022;$y--){
    $selected = ($y==$year) ? "selected" : "";
    echo "<option value='$y' $selected>$y</option>";
}
?>
</select>
</div>

<div class="col-md-6">
<label class="form-label">Months</label>
<select name="months[]" class="form-control" multiple size="3">
<?php
$monthNames = [
1=>"Jan",2=>"Feb",3=>"Mar",4=>"Apr",
5=>"May",6=>"Jun",7=>"Jul",8=>"Aug",
9=>"Sep",10=>"Oct",11=>"Nov",12=>"Dec"
];

foreach($monthNames as $num=>$name){
    $selected = in_array($num,$months_filter) ? "selected" : "";
    echo "<option value='$num' $selected>$name</option>";
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
<a href="index.php" class="btn btn-secondary w-100">
Clear Filters
</a>
</div>

<div class="col-md-2">
<a href="?export=excel&year=<?= htmlspecialchars($year) ?><?php foreach($months_filter as $m){ echo '&months[]='.intval($m);} ?><?= $wado ? '&wado='.$wado : '' ?>" class="btn btn-success w-100">
Export Excel
</a>
<a href="?export=detailed&year=<?= htmlspecialchars($year) ?><?php foreach($months_filter as $m){ echo '&months[]='.intval($m);} ?><?= $wado ? '&wado='.$wado : '' ?>" class="btn btn-dark w-100 mt-1">
Detailed Report
</a>
</div>

</form>
</div>

<div class="d-flex justify-content-between mb-4">
<h3><?= htmlspecialchars(ucfirst($_SESSION['username'])) ?> Dashboard (<?= $year ?>)</h3>
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
<canvas id="wadoChart" style="height:<?= max(700, count($wado_labels)*25) ?>px"></canvas>
</div>
</div>

<div class="col-md-12">
<div class="row g-3">

<div class="col-md-6">
<div class="card p-3 shadow-sm h-100">
<h6 class="text-center">Monthly Collection Service Trend</h6>
<canvas id="monthlyChart" style="height:250px"></canvas>
</div>
</div>

<div class="col-md-6">
<div class="card p-3 shadow-sm h-100">
<h6 class="text-center">Segregation Percentage</h6>
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
<canvas id="wadoSegChart" style="height:<?= max(700, count($wado_seg_labels)*25) ?>px"></canvas>
</div>
</div>
</div>

<script>

Chart.register({
    id: 'valueLabels',
    afterDatasetsDraw(chart) {
        const {ctx} = chart;
        chart.data.datasets.forEach((dataset, i) => {
            const meta = chart.getDatasetMeta(i);
            meta.data.forEach((bar, index) => {
                const value = dataset.data[index];
                ctx.fillStyle = '#000';
                ctx.font = '10px Arial';
                ctx.textAlign = 'left';
                ctx.fillText(value, bar.x + 5, bar.y + 3);
            });
        });
    }
});

new Chart(document.getElementById('monthlyChart'), {
type: 'bar',
data: {
labels: <?= json_encode($months); ?>,
datasets: [{
label: 'Serviced Households',
data: <?= json_encode($serviced_data); ?>
}]
}
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
indexAxis: 'y',
responsive: true,
plugins: {
legend: {
position: 'top'
}
},
scales: {
    y: {
        ticks: {
            font: {
                size: 11
            },
            callback: function(value) {
                let label = this.getLabelForValue(value);
                return label;
            }
        }
    },
    x: {
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
let pct = ((value/total)*100).toFixed(1);
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
    indexAxis: 'y',
    responsive: true,
    plugins: {
        legend: {
            position: 'top'
        },
        valueLabels: true
    },
    scales: {
        y: {
            ticks: {
                font: {
                    size: 11
                }
            }
        },
        x: {
            beginAtZero: true
        }
    }
}
});

</script>

</body>
</html>