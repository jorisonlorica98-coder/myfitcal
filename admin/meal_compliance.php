<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireAdmin();
$db = getDB();

$search  = trim($_GET['q'] ?? '');
$filter  = $_GET['filter'] ?? 'all'; // all, compliant, partial, none
$range   = $_GET['range'] ?? '7';    // 7, 14, 30
$page    = max(1,(int)($_GET['page'] ?? 1));
$limit   = 20; $offset = ($page-1)*$limit;

// Create table if not exists
try {
    $db->exec("CREATE TABLE IF NOT EXISTS meal_compliance (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        log_date DATE NOT NULL,
        day_number INT NOT NULL DEFAULT 1,
        total_foods INT NOT NULL DEFAULT 0,
        checked_foods INT NOT NULL DEFAULT 0,
        is_complete TINYINT(1) DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_date (user_id, log_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Exception $e) {}

$has_table = (bool)$db->query("SHOW TABLES LIKE 'meal_compliance'")->fetch();
$has_goals = (bool)$db->query("SHOW TABLES LIKE 'user_goals'")->fetch();

// Summary stats
$total_users = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$days_range  = (int)$range;

$compliant_count = 0; $partial_count = 0; $none_count = 0; $total_logs = 0;
$compliance_rate = 0;

if ($has_table) {
    $total_logs      = (int)$db->query("SELECT COUNT(*) FROM meal_compliance WHERE log_date >= DATE_SUB(CURDATE(), INTERVAL $days_range DAY)")->fetchColumn();
    $compliant_count = (int)$db->query("SELECT COUNT(*) FROM meal_compliance WHERE is_complete=1 AND log_date >= DATE_SUB(CURDATE(), INTERVAL $days_range DAY)")->fetchColumn();
    $partial_count   = (int)$db->query("SELECT COUNT(*) FROM meal_compliance WHERE is_complete=0 AND checked_foods>0 AND log_date >= DATE_SUB(CURDATE(), INTERVAL $days_range DAY)")->fetchColumn();
    $none_count      = (int)$db->query("SELECT COUNT(*) FROM meal_compliance WHERE checked_foods=0 AND log_date >= DATE_SUB(CURDATE(), INTERVAL $days_range DAY)")->fetchColumn();
    $compliance_rate = $total_logs > 0 ? round($compliant_count / $total_logs * 100) : 0;
}

// Per-user compliance summary
$where = "WHERE u.role='user'"; $params = [];
if ($search) {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}

$sel_comp = $has_table
    ? "COUNT(DISTINCT CASE WHEN mc.is_complete=1 THEN mc.log_date END)"
    : "0";
$sel_days = $has_table
    ? "COUNT(DISTINCT mc.log_date)"
    : "0";
$sel_last = $has_table
    ? "MAX(mc.log_date)"
    : "NULL";
$sel_pct  = $has_table
    ? "ROUND(COUNT(DISTINCT CASE WHEN mc.is_complete=1 THEN mc.log_date END) / GREATEST(COUNT(DISTINCT mc.log_date),1) * 100)"
    : "0";
$join_mc  = $has_table
    ? "LEFT JOIN meal_compliance mc ON mc.user_id=u.id AND mc.log_date >= DATE_SUB(CURDATE(), INTERVAL $days_range DAY)"
    : "";
$join_goal= $has_goals
    ? "LEFT JOIN user_goals ug ON ug.user_id=u.id"
    : "";
$sel_goal = $has_goals ? "ug.goal_type" : "NULL";

$t = $db->prepare("SELECT COUNT(*) FROM users u $where");
$t->execute($params);
$total_count = (int)$t->fetchColumn();
$total_pages = max(1,(int)ceil($total_count/$limit));

$s = $db->prepare("
    SELECT u.id, u.name, u.email, u.gender, u.created_at,
           $sel_comp as complete_days,
           $sel_days as tracked_days,
           $sel_last as last_logged,
           $sel_pct  as compliance_pct,
           $sel_goal as goal_type
    FROM users u
    $join_mc $join_goal
    $where
    GROUP BY u.id
    ORDER BY compliance_pct DESC, complete_days DESC
    LIMIT $limit OFFSET $offset
");
$s->execute($params);
$rows = $s->fetchAll();

// Daily compliance trend (last 14 days)
$trend = [];
if ($has_table) {
    for ($i = ($days_range > 14 ? 13 : $days_range - 1); $i >= 0; $i--) {
        $date   = date('Y-m-d', strtotime("-$i days"));
        $comp   = (int)$db->query("SELECT COUNT(*) FROM meal_compliance WHERE log_date='$date' AND is_complete=1")->fetchColumn();
        $part   = (int)$db->query("SELECT COUNT(*) FROM meal_compliance WHERE log_date='$date' AND is_complete=0 AND checked_foods>0")->fetchColumn();
        $trend[] = ['date' => date('M d', strtotime($date)), 'complete' => $comp, 'partial' => $part];
    }
}

$goal_labels = ['lose'=>'Weight Loss','maintain'=>'Maintenance','gain'=>'Weight Gain','muscle'=>'Muscle Gain'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Meal Compliance — MyFitCal Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--sb:#050505;--sb-w:248px;--blue:#1f4f7d;--blue-d:#174a7a;--slate:#111827;--muted:#52606d;--border:rgba(216,226,234,.9);--bg:#ebeff3;--accent:#1f4f7d;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--slate);}
.sidebar{position:fixed;left:0;top:0;bottom:0;width:var(--sb-w);background:var(--sb);display:flex;flex-direction:column;z-index:200;border-right:1px solid rgba(255,255,255,.08);}
.sidebar::before{content:none;}
.sb-logo{padding:1.5rem 1.25rem 1rem;position:relative;z-index:1;display:flex;align-items:center;gap:.8rem;border-bottom:1px solid rgba(255,255,255,.06);}
.sb-icon{width:38px;height:38px;border-radius:10px;background:var(--blue);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem;flex-shrink:0;}
.sb-logo-text{font-size:.95rem;font-weight:800;color:#fff;letter-spacing:-.3px;}
.sb-badge{font-size:.58rem;font-weight:700;background:rgba(37,99,235,.3);border:1px solid rgba(37,99,235,.4);color:#93c5fd;padding:.1rem .45rem;border-radius:4px;margin-top:.1rem;letter-spacing:.5px;}
.sb-nav{flex:1;padding:.75rem 0;overflow-y:auto;position:relative;z-index:1;}
.sb-section{font-size:.58rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.22);padding:.6rem 1.25rem .2rem;display:block;}
.sb-item{display:block;padding:.2rem .75rem;text-decoration:none;}
.sb-link{display:flex;align-items:center;gap:.65rem;padding:.55rem .65rem;border-radius:9px;font-size:.82rem;font-weight:600;color:rgba(255,255,255,.42);transition:all .15s;}
.sb-link:hover{background:rgba(255,255,255,.07);color:rgba(255,255,255,.82);}
.sb-link.active{background:rgba(37,99,235,.22);color:#93c5fd;border:1px solid rgba(37,99,235,.2);}
.sb-link i{font-size:.95rem;width:18px;text-align:center;}
.sb-footer{padding:1rem 1.25rem;border-top:1px solid rgba(255,255,255,.06);position:relative;z-index:1;}
.sb-user{display:flex;align-items:center;gap:.7rem;}
.sb-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--blue),var(--blue-d));display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:800;color:#fff;flex-shrink:0;}
.sb-uname{font-size:.8rem;font-weight:700;color:#fff;line-height:1.2;}
.sb-urole{font-size:.62rem;color:rgba(255,255,255,.3);}
.sb-logout{color:rgba(255,255,255,.25);text-decoration:none;font-size:.9rem;margin-left:auto;transition:color .15s;}
.sb-logout:hover{color:#f87171;}
.main{margin-left:var(--sb-w);min-height:100vh;}
.topbar{background:#fff;border-bottom:1px solid rgba(216,226,234,.7);padding:1rem 2rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 10px 30px rgba(15,23,42,.04);}
.topbar h2{font-size:1.15rem;font-weight:900;color:var(--slate);letter-spacing:-.02em;}
.topbar p{font-size:.78rem;color:var(--muted);margin-top:.15rem;}
.content{padding:1.75rem 2rem;}
.stats-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;margin-bottom:1.5rem;}
.scard{background:#fff;border-radius:20px;border:1px solid rgba(216,226,234,.8);padding:1.25rem;box-shadow:0 16px 30px rgba(15,23,42,.05);}
.scard-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1rem;margin-bottom:.7rem;}
.scard-val{font-size:1.5rem;font-weight:800;color:var(--slate);line-height:1;margin-bottom:.2rem;}
.scard-label{font-size:.72rem;color:var(--muted);font-weight:600;}
.chart-card{background:#fff;border-radius:22px;border:1px solid rgba(216,226,234,.8);padding:1.35rem;margin-bottom:1.5rem;box-shadow:0 20px 45px rgba(15,23,42,.05);}
.chart-title{font-size:.92rem;font-weight:800;color:var(--slate);margin-bottom:1rem;display:flex;align-items:center;gap:.6rem;}
.chart-title i{color:var(--accent);}
.card{background:#fff;border-radius:22px;border:1px solid rgba(216,226,234,.8);padding:1.5rem;box-shadow:0 20px 45px rgba(15,23,42,.05);}
.toolbar{display:flex;align-items:center;gap:.75rem;margin-bottom:1.35rem;flex-wrap:wrap;}
.search-wrap{position:relative;flex:1;min-width:220px;max-width:340px;}
.search-wrap input{width:100%;padding:.6rem 1rem .6rem 2.3rem;border-radius:13px;border:1.5px solid rgba(216,226,234,.85);font-family:'Plus Jakarta Sans',sans-serif;font-size:.9rem;outline:none;background:#fff;color:var(--slate);}
.search-wrap input:focus{border-color:var(--accent);background:#fff;box-shadow:0 0 0 4px rgba(31,79,125,.08);}
.search-wrap i{position:absolute;left:.85rem;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.95rem;}
.range-pills{display:flex;gap:.5rem;}
.rpill{padding:.55rem 1rem;border-radius:13px;border:1.5px solid rgba(216,226,234,.85);background:#fff;font-family:'Plus Jakarta Sans',sans-serif;font-size:.82rem;font-weight:700;color:var(--muted);text-decoration:none;transition:all .2s;}
.rpill:hover{background:rgba(31,79,125,.08);color:var(--slate);}
.rpill.active{background:var(--accent);color:#fff;border-color:var(--accent);}
table{width:100%;border-collapse:collapse;}
th{font-size:.68rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;padding:.6rem .85rem;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap;}
td{font-size:.8rem;color:var(--slate);padding:.65rem .85rem;border-bottom:1px solid #f8fafc;vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#fafbfc;}
.user-cell{display:flex;align-items:center;gap:.6rem;}
.mini-av{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#1e40af);display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;color:#fff;flex-shrink:0;}
.mini-av.female{background:linear-gradient(135deg,#be185d,#7c3aed);}
.comp-bar{height:6px;background:#f1f5f9;border-radius:999px;overflow:hidden;width:70px;display:inline-block;vertical-align:middle;margin-right:.4rem;}
.comp-fill{height:100%;border-radius:999px;}
.comp-badge{font-size:.68rem;font-weight:700;padding:.2rem .6rem;border-radius:6px;}
.comp-full{background:#dcfce7;color:#15803d;}
.comp-partial{background:#fef9c3;color:#854d0e;}
.comp-none{background:#fee2e2;color:#dc2626;}
.goal-badge{font-size:.65rem;font-weight:700;padding:.15rem .55rem;border-radius:5px;background:#eff6ff;color:#1d4ed8;}
.empty-state{text-align:center;padding:3rem;color:var(--muted);}
.empty-state i{font-size:2.5rem;margin-bottom:.75rem;display:block;opacity:.3;}
.pagination{display:flex;gap:.4rem;margin-top:1.5rem;justify-content:flex-end;}
.page-btn{min-width:36px;height:36px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:700;text-decoration:none;border:1.5px solid rgba(216,226,234,.85);color:var(--muted);transition:all .2s;}
.page-btn:hover{background:rgba(31,79,125,.08);color:var(--slate);}
.page-btn.active{background:var(--accent);color:#fff;border-color:var(--accent);}
</style>
</head>
<body>
<aside class="sidebar">
  <div class="sb-logo">
    <img src="/myfitcal_system/assets/image/logo.png" alt="MyFitCal" style="height:54px;width:auto;object-fit:contain;filter:drop-shadow(0 2px 10px rgba(0,0,0,0.4));flex-shrink:0;">
        <div>
            <div style="font-size:.95rem;font-weight:800;color:#fff;letter-spacing:-.3px;">MyFitCal</div>
            <span style="font-size:.6rem;font-weight:700;background:rgba(255,255,255,.15);color:rgba(255,255,255,.7);padding:.15rem .5rem;border-radius:4px;">ADMIN</span>
        </div>
  </div>
  <nav class="sb-nav">
    <span class="sb-section">Monitor</span>
    <a class="sb-item" href="dashboard.php"><div class="sb-link"><i class="bi bi-speedometer2"></i> Dashboard</div></a>
    <a class="sb-item" href="users.php"><div class="sb-link"><i class="bi bi-people"></i> Users</div></a>
    <span class="sb-section" style="margin-top:.5rem">Data</span>
    <a class="sb-item" href="calories.php"><div class="sb-link"><i class="bi bi-fire"></i> Calories</div></a>
    <a class="sb-item" href="meal_compliance.php"><div class="sb-link active"><i class="bi bi-egg-fried"></i> Meal Compliance</div></a>
  </nav>
  <div class="sb-footer">
    <div class="sb-user">
      <div class="sb-avatar"><?= strtoupper(substr($_SESSION['name'],0,1)) ?></div>
      <div><div class="sb-uname"><?= htmlspecialchars($_SESSION['name']) ?></div><div class="sb-urole">Administrator</div></div>
      <a class="sb-logout" href="/myfitcal_system/logout.php"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div><h2>Meal Plan Compliance</h2><p>Track which users are following their daily meal plan</p></div>
  </div>
  <div class="content">

    <!-- STATS -->
    <div class="stats-row">
      <div class="scard">
        <div class="scard-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-check-circle-fill"></i></div>
        <div class="scard-val"><?= $compliance_rate ?>%</div>
        <div class="scard-label">Compliance Rate</div>
      </div>
      <div class="scard">
        <div class="scard-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-egg-fried"></i></div>
        <div class="scard-val"><?= $compliant_count ?></div>
        <div class="scard-label">Fully Completed</div>
      </div>
      <div class="scard">
        <div class="scard-icon" style="background:#fef9c3;color:#ca8a04;"><i class="bi bi-dash-circle"></i></div>
        <div class="scard-val"><?= $partial_count ?></div>
        <div class="scard-label">Partial Compliance</div>
      </div>
      <div class="scard">
        <div class="scard-icon" style="background:#fee2e2;color:#dc2626;"><i class="bi bi-x-circle"></i></div>
        <div class="scard-val"><?= $none_count ?></div>
        <div class="scard-label">No Compliance</div>
      </div>
    </div>

    <!-- TREND CHART -->
    <?php if (!empty($trend)): ?>
    <div class="chart-card">
      <div class="chart-title"><i class="bi bi-graph-up"></i> Daily Meal Compliance Trend</div>
      <canvas id="trendChart" height="80"></canvas>
    </div>
    <?php endif; ?>

    <!-- USER TABLE -->
    <div class="card">
      <form method="GET">
        <div class="toolbar">
          <div class="search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search user...">
            <input type="hidden" name="range" value="<?= $range ?>">
          </div>
          <div class="range-pills">
            <span style="font-size:.75rem;font-weight:700;color:var(--muted);align-self:center;">Range:</span>
            <?php foreach (['7'=>'7 Days','14'=>'14 Days','30'=>'30 Days'] as $val => $label): ?>
            <?php $ra = ($range === $val) ? ' active' : ''; ?>
            <a href="?range=<?= $val ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="rpill<?= $ra ?>"><?= $label ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      </form>

      <?php if (empty($rows)): ?>
      <div class="empty-state">
        <i class="bi bi-egg-fried"></i>
        No meal compliance data yet. Data is recorded when users check off food items.
      </div>
      <?php else: ?>
      <table>
        <thead>
          <tr><th>User</th><th>Goal</th><th>Compliance (<?= $range ?>d)</th><th>Complete Days</th><th>Tracked Days</th><th>Last Logged</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $pct      = (int)($r['compliance_pct'] ?? 0);
            $comp_d   = (int)($r['complete_days'] ?? 0);
            $track_d  = (int)($r['tracked_days'] ?? 0);
            $is_female = strtolower($r['gender'] ?? '') === 'female';

            if ($pct >= 80)       { $badge_class = 'comp-full';    $badge_label = 'Compliant'; }
            elseif ($pct >= 40)   { $badge_class = 'comp-partial'; $badge_label = 'Partial'; }
            else                  { $badge_class = 'comp-none';    $badge_label = $track_d > 0 ? 'Low' : 'No Data'; }

            $bar_color = $pct >= 80 ? '#16a34a' : ($pct >= 40 ? '#ca8a04' : '#dc2626');
          ?>
          <tr>
            <td>
              <div class="user-cell">
                <div class="mini-av <?= $is_female ? 'female' : '' ?>"><?= strtoupper(substr($r['name'],0,1)) ?></div>
                <div>
                  <div style="font-weight:700;"><?= htmlspecialchars($r['name']) ?></div>
                  <div style="font-size:.7rem;color:var(--muted);"><?= htmlspecialchars($r['email']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <?php if ($r['goal_type']): ?>
              <span class="goal-badge"><?= htmlspecialchars($goal_labels[$r['goal_type']] ?? $r['goal_type']) ?></span>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td>
              <div class="comp-bar"><div class="comp-fill" style="width:<?= $pct ?>%;background:<?= $bar_color ?>;"></div></div>
              <span style="font-size:.75rem;font-weight:800;color:<?= $bar_color ?>;"><?= $pct ?>%</span>
              <span class="comp-badge <?= $badge_class ?>" style="margin-left:.4rem;"><?= $badge_label ?></span>
            </td>
            <td style="font-weight:800;color:#16a34a;"><?= $comp_d ?></td>
            <td style="color:var(--muted);"><?= $track_d ?></td>
            <td style="color:var(--muted);"><?= $r['last_logged'] ? date('M d, Y', strtotime($r['last_logged'])) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php for ($p=1; $p<=$total_pages; $p++):
          $qs = '?page='.$p.'&range='.$range.($search ? '&q='.urlencode($search) : '');
          $active = ($p===$page) ? ' active' : '';
        ?>
        <a href="<?= $qs ?>" class="page-btn<?= $active ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (!empty($trend)): ?>
<script>
new Chart(document.getElementById('trendChart'), {
  type: 'bar',
  data: {
    labels: [<?= implode(',', array_map(fn($r) => '"'.$r['date'].'"', $trend)) ?>],
    datasets: [
      {
        label: 'Fully Complete',
        data: [<?= implode(',', array_column($trend, 'complete')) ?>],
        backgroundColor: 'rgba(22,163,74,.7)',
        borderRadius: 5,
        stack: 'a'
      },
      {
        label: 'Partial',
        data: [<?= implode(',', array_column($trend, 'partial')) ?>],
        backgroundColor: 'rgba(234,179,8,.6)',
        borderRadius: 5,
        stack: 'a'
      }
    ]
  },
  options: {
    plugins:{legend:{position:'top',labels:{font:{size:11},padding:12}}},
    scales:{
      y:{beginAtZero:true,ticks:{stepSize:1,font:{size:11}},grid:{color:'#f1f5f9'},stacked:true},
      x:{ticks:{font:{size:11}},grid:{display:false},stacked:true}
    }
  }
});
</script>
<?php endif; ?>
</body>
</html>