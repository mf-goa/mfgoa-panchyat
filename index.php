<?php

session_start();
// ================================
// DEBUG MODE (TEMPORARY)
// ================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// ================================
// DB CONNECTION DEBUG TEST
// ================================
$debug_test = $conn->query("SELECT COUNT(*) as total FROM mf_submit_collection_entry");
if ($debug_test) {
    $debug_row = $debug_test->fetch_assoc();
    echo "<div style='background:#000;color:#0f0;padding:5px;font-size:12px;'>
    DEBUG: Total rows in mf_submit_collection_entry = {$debug_row['total']}
    </div>";
} else {
    echo "<div style='background:red;color:#fff;padding:5px;'>
    DEBUG QUERY FAILED: " . $conn->error . "
    </div>";
}

/* ================================
   HARDCODED USERS
================================ */
$users = [
    'bicholim' => ['password' => 'bicho123', 'panchayat_id' => 3],
    'sankhali' => ['password' => 'sank123', 'panchayat_id' => 5]
];

/* ================================
   LOGIN
================================ */
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (isset($users[$username]) && $users[$username]['password'] === $password) {
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
echo "<div style='background:#111;color:#fff;padding:5px;font-size:12px;'>
DEBUG: Panchayat ID = {$panchayat_id}
</div>";
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$wado = isset($_GET['wado']) ? intval($_GET['wado']) : null;

/* ================================
   BUILD WHERE
================================ */
$where = ["YEAR(msce.collection_date) = ?", "mp.id = ?"];
$params = [$year, $panchayat_id];
$types = "ii";

if ($wado) {
    $where[] = "w.id = ?";
    $params[] = $wado;
    $types .= "i";
}

$where_sql = implode(" AND ", $where);
echo "<div style='background:#222;color:#0ff;padding:5px;font-size:12px;'>
DEBUG WHERE CLAUSE: $where_sql
</div>";

/* ================================
   EXPORT TO EXCEL
================================ */
if (isset($_GET['export']) && $_GET['export'] == 'excel') {

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=wado_report_$year.csv");

    echo "Wado,Serviced Households,Total Households\n";

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
    if ($stmt->error) {
        echo "<div style='background:red;color:white;padding:5px;'>
        DEBUG SQL ERROR: " . $stmt->error . "
        </div>";
    }
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        echo "{$row['name']},{$row['serviced']},{$row['total_households']}\n";
    }

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
if ($stmt->error) {
    echo "<div style='background:red;color:white;padding:5px;'>
    DEBUG SQL ERROR: " . $stmt->error . "
    </div>";
}
$kpi = $stmt->get_result()->fetch_assoc();
$stmt->close();

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
if ($stmt->error) {
    echo "<div style='background:red;color:white;padding:5px;'>
    DEBUG SQL ERROR: " . $stmt->error . "
    </div>";
}
$res = $stmt->get_result();

$months = [];
$serviced_data = [];

while ($r = $res->fetch_assoc()) {
    $months[] = $r['month'];
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
if ($stmt->error) {
    echo "<div style='background:red;color:white;padding:5px;'>
    DEBUG SQL ERROR: " . $stmt->error . "
    </div>";
}
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
if ($stmt->error) {
    echo "<div style='background:red;color:white;padding:5px;'>
    DEBUG SQL ERROR: " . $stmt->error . "
    </div>";
}
$res = $stmt->get_result();

$seg_labels = [];
$seg_data = [];

while ($r = $res->fetch_assoc()) {
    $seg_labels[] = $r['name'];
    $seg_data[] = $r['total'];
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
<title>Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4 bg-light">

<div class="d-flex justify-content-between mb-4">
    <h3><?= ucfirst($_SESSION['username']); ?> Dashboard (<?= $year; ?>)</h3>
    <div>
        <a href="?logout=1" class="btn btn-danger">Logout</a>
    </div>
</div>

<a href="?year=<?= $year ?>&export=excel" class="btn btn-success mb-3">Export Wado Report</a>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card p-3 text-center shadow-sm">
            <h6>Total Collections</h6>
            <h3><?= number_format($kpi['total_collections']); ?></h3>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 text-center shadow-sm">
            <h6>Serviced Households</h6>
            <h3><?= number_format($kpi['serviced_households']); ?></h3>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 text-center shadow-sm">
            <h6>Last Collection</h6>
            <h3><?= $kpi['last_collection']; ?></h3>
        </div>
    </div>
</div>

<div class="card p-4 mb-4 shadow-sm">
    <h5>Monthly Trend</h5>
    <canvas id="monthlyChart"></canvas>
</div>

<div class="card p-4 mb-4 shadow-sm">
    <h5>Wado Breakdown</h5>
    <canvas id="wadoChart"></canvas>
</div>

<div class="card p-4 shadow-sm">
    <h5>Segregation Breakdown</h5>
    <canvas id="segChart"></canvas>
</div>

<script>
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
    }
});

new Chart(document.getElementById('segChart'), {
    type: 'pie',
    data: {
        labels: <?= json_encode($seg_labels); ?>,
        datasets: [{ data: <?= json_encode($seg_data); ?> }]
    }
});
</script>

</body>
</html>