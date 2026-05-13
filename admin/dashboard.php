<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireAdmin();

$db = getDB();

function tableExists($db, $table) {
    try { $db->query("SELECT 1 FROM `$table` LIMIT 1"); return true; }
    catch (Exception $e) { return false; }
}

$has_calorie_logs       = tableExists($db, 'calorie_logs');
$has_fitness_activities = tableExists($db, 'fitness_activities');
$has_user_goals         = tableExists($db, 'user_goals');
$has_workout_progress   = tableExists($db, 'user_workout_progress');
$has_user_profiles      = tableExists($db, 'user_profiles');
$has_user_fitness       = tableExists($db, 'user_fitness');

$total_users   = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$total_admins  = $db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$new_today     = $db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE() AND role='user'")->fetchColumn();
$new_week      = $db->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY) AND role='user'")->fetchColumn();
$inactive_users= $db->query("SELECT COUNT(*) FROM users WHERE is_active=0 AND role='user'")->fetchColumn();

$setup_done = $has_user_fitness ? $db->query("SELECT COUNT(DISTINCT user_id) FROM user_fitness")->fetchColumn() : 0;
$setup_pct  = $total_users > 0 ? round($setup_done / $total_users * 100) : 0;

$total_completions = $has_workout_progress ? $db->query("SELECT COUNT(*) FROM user_workout_progress WHERE completed=1")->fetchColumn() : 0;
$users_started     = $has_workout_progress ? $db->query("SELECT COUNT(DISTINCT user_id) FROM user_workout_progress")->fetchColumn() : 0;
$users_finished_30 = $has_workout_progress ? $db->query("SELECT COUNT(*) FROM (SELECT user_id, COUNT(DISTINCT day_number) as days FROM user_workout_progress WHERE completed=1 GROUP BY user_id HAVING days>=30) x")->fetchColumn() : 0;

$gd = ['lose'=>0,'gain'=>0,'maintain'=>0,'muscle'=>0];
if ($has_user_goals) {
    foreach ($db->query("SELECT goal_type, COUNT(*) as cnt FROM user_goals GROUP BY goal_type")->fetchAll() as $g)
        if (isset($gd[$g['goal_type']])) $gd[$g['goal_type']] = (int)$g['cnt'];
}

$genders = ['male'=>0,'female'=>0,'other'=>0];
foreach ($db->query("SELECT COALESCE(gender,'other') as g, COUNT(*) as cnt FROM users WHERE role='user' GROUP BY g")->fetchAll() as $r)
    $genders[$r['g'] === 'male' ? 'male' : ($r['g'] === 'female' ? 'female' : 'other')] += (int)$r['cnt'];

$levels = ['beginner'=>0,'normal'=>0,'expert'=>0,'advance'=>0];
if ($has_user_fitness) {
    foreach ($db->query("SELECT fitness_level, COUNT(*) as cnt FROM user_fitness GROUP BY fitness_level")->fetchAll() as $r)
        if (isset($levels[$r['fitness_level']])) $levels[$r['fitness_level']] = (int)$r['cnt'];
}

$reg_labels = []; $reg_data = [];
$reg_rows = $db->query("SELECT DATE(created_at) as d, COUNT(*) as cnt FROM users WHERE created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY) AND role='user' GROUP BY DATE(created_at) ORDER BY d ASC")->fetchAll();
for ($i=6;$i>=0;$i--) {
    $reg_labels[] = date('D M j', strtotime("-$i days"));
    $dt = date('Y-m-d', strtotime("-$i days"));
    $found = array_filter($reg_rows, fn($r) => $r['d'] === $dt);
    $reg_data[] = $found ? array_values($found)[0]['cnt'] : 0;
}

$wk_labels = []; $wk_data = [];
if ($has_workout_progress) {
    $wk_rows = $db->query("SELECT DATE(completed_at) as d, COUNT(*) as cnt FROM user_workout_progress WHERE completed=1 AND completed_at >= DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY DATE(completed_at) ORDER BY d ASC")->fetchAll();
    for ($i=29;$i>=0;$i--) {
        $dt = date('Y-m-d', strtotime("-$i days"));
        $found = array_filter($wk_rows, fn($r) => $r['d'] === $dt);
        $wk_data[] = $found ? array_values($found)[0]['cnt'] : 0;
        if ($i % 5 === 0) $wk_labels[] = date('M j', strtotime("-$i days"));
        else $wk_labels[] = '';
    }
} else {
    $wk_labels = array_fill(0, 30, ''); $wk_data = array_fill(0, 30, 0);
}

$recent_users = $db->query("SELECT id, name, email, gender, created_at, is_active FROM users WHERE role='user' ORDER BY created_at DESC LIMIT 10")->fetchAll();

$top_users = $db->query("
    SELECT u.name, u.email, u.gender, u.is_active,
           COUNT(DISTINCT wp.day_number) as days_done
    FROM users u
    LEFT JOIN user_workout_progress wp ON wp.user_id=u.id AND wp.completed=1
    WHERE u.role='user'
    GROUP BY u.id ORDER BY days_done DESC LIMIT 8
")->fetchAll();

$sys_checks = [
    ['label'=>'Users Table',           'ok'=>true,                     'detail'=>"$total_users users registered"],
    ['label'=>'User Goals Table',      'ok'=>$has_user_goals,          'detail'=>$has_user_goals ? array_sum($gd).' goals set' : 'Table not found'],
    ['label'=>'User Fitness Table',    'ok'=>$has_user_fitness,        'detail'=>$has_user_fitness ? "$setup_done users set up" : 'Table not found'],
    ['label'=>'User Profiles Table',   'ok'=>$has_user_profiles,       'detail'=>$has_user_profiles ? 'Table OK' : 'Table not found'],
    ['label'=>'Workout Progress Table','ok'=>$has_workout_progress,    'detail'=>$has_workout_progress ? "$total_completions completions logged" : 'Table not found'],
    ['label'=>'Calorie Logs Table',    'ok'=>$has_calorie_logs,        'detail'=>$has_calorie_logs ? 'Table OK' : 'Table not found — calorie logging disabled'],
    ['label'=>'Fitness Activities',    'ok'=>$has_fitness_activities,  'detail'=>$has_fitness_activities ? 'Table OK' : 'Table not found'],
    ['label'=>'Inactive Users',        'ok'=>$inactive_users == 0,     'detail'=>$inactive_users > 0 ? "$inactive_users accounts deactivated" : 'All accounts active'],
];
$sys_ok = count(array_filter($sys_checks, fn($c) => $c['ok']));
$sys_total = count($sys_checks);
$sys_health = round($sys_ok / $sys_total * 100);

if ($sys_health >= 87) {
    $hb_bg='#0f2517'; $hb_border='rgba(34,197,94,.22)'; $hb_icon_bg='rgba(34,197,94,.22)'; $hb_icon_color='#86efac'; $hb_bar='#86efac';
} elseif ($sys_health >= 60) {
    $hb_bg='#2d2518'; $hb_border='rgba(245,158,11,.22)'; $hb_icon_bg='rgba(245,158,11,.18)'; $hb_icon_color='#fbbf24'; $hb_bar='#fbbf24';
} else {
    $hb_bg='#191f1a'; $hb_border='rgba(255,255,255,.08)'; $hb_icon_bg='rgba(34,197,94,.16)'; $hb_icon_color='#a3e635'; $hb_bar='#4ade80';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — MyFitCal Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --sb:#050505;--sb-w:248px;
  --blue:#1f4f7d;--blue-d:#174a7a;
  --slate:#111827;--muted:#52606d;--border:rgba(216,226,234,.9);--bg:#ebeff3;--accent:#1f4f7d;
}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--slate);}

/* ── SIDEBAR (from meal_compliance.php) ── */
.sidebar{position:fixed;left:0;top:0;bottom:0;width:var(--sb-w);background:var(--sb);display:flex;flex-direction:column;z-index:200;border-right:1px solid rgba(255,255,255,.08);}
.sb-logo{padding:1.5rem 1.25rem 1rem;display:flex;align-items:center;gap:.8rem;border-bottom:1px solid rgba(255,255,255,.06);}
.sb-icon{width:38px;height:38px;border-radius:10px;background:var(--blue);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem;flex-shrink:0;}
.sb-logo-text{font-size:.95rem;font-weight:800;color:#fff;letter-spacing:-.3px;}
.sb-badge{font-size:.58rem;font-weight:700;background:rgba(255,255,255,.15);color:rgba(255,255,255,.7);padding:.15rem .5rem;border-radius:4px;margin-top:.1rem;display:inline-block;letter-spacing:.5px;}
.sb-nav{flex:1;padding:.75rem 0;overflow-y:auto;}
.sb-section{font-size:.58rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.22);padding:.6rem 1.25rem .2rem;display:block;}
.sb-item{display:block;padding:.2rem .75rem;text-decoration:none;}
.sb-link{display:flex;align-items:center;gap:.65rem;padding:.55rem .65rem;border-radius:9px;font-size:.82rem;font-weight:600;color:rgba(255,255,255,.42);transition:all .15s;}
.sb-link:hover{background:rgba(255,255,255,.07);color:rgba(255,255,255,.82);}
.sb-link.active{background:rgba(37,99,235,.22);color:#93c5fd;border:1px solid rgba(37,99,235,.2);}
.sb-link i{font-size:.95rem;width:18px;text-align:center;}
.sb-footer{padding:1rem 1.25rem;border-top:1px solid rgba(255,255,255,.06);}
.sb-user{display:flex;align-items:center;gap:.7rem;}
.sb-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--blue),var(--blue-d));display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:800;color:#fff;flex-shrink:0;}
.sb-uname{font-size:.8rem;font-weight:700;color:#fff;line-height:1.2;}
.sb-urole{font-size:.62rem;color:rgba(255,255,255,.3);}
.sb-logout{color:rgba(255,255,255,.25);text-decoration:none;font-size:.9rem;margin-left:auto;transition:color .15s;}
.sb-logout:hover{color:#f87171;}

/* ── MAIN ── */
.main{margin-left:var(--sb-w);min-height:100vh;}
.topbar{background:#fff;border-bottom:1px solid rgba(216,226,234,.7);padding:1rem 2rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 10px 30px rgba(15,23,42,.04);}
.topbar h2{font-size:1.15rem;font-weight:900;color:var(--slate);letter-spacing:-.02em;}
.topbar p{font-size:.78rem;color:var(--muted);margin-top:.15rem;}
.date-pill{display:inline-flex;align-items:center;gap:.45rem;padding:.7rem .95rem;background:#fff;border:1px solid rgba(148,163,184,.22);border-radius:999px;color:#52606d;font-size:.8rem;}
.date-pill i{font-size:1rem;color:var(--blue);}
.content{padding:1.75rem 2rem;}

/* ── HEALTH BANNER ── */
.health-banner{border-radius:20px;padding:1.75rem;margin-bottom:1.75rem;display:flex;align-items:center;gap:1.5rem;background:<?= $hb_bg ?>;border:1px solid <?= $hb_border ?>;position:relative;overflow:hidden;box-shadow:0 18px 48px rgba(15,23,42,.08);}
.hb-icon{width:64px;height:64px;border-radius:18px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1.75rem;background:<?= $hb_icon_bg ?>;color:<?= $hb_icon_color ?>;}
.hb-info{flex:1;}
.hb-title{font-size:1.2rem;font-weight:900;color:#fff;margin-bottom:.4rem;}
.hb-sub{font-size:.88rem;color:rgba(255,255,255,.72);margin-bottom:1rem;line-height:1.5;}
.hb-bar-track{height:10px;background:rgba(255,255,255,.16);border-radius:999px;overflow:hidden;width:100%;max-width:420px;}
.hb-bar-fill{height:100%;border-radius:999px;background:<?= $hb_bar ?>;}
.hb-pct{font-size:2.05rem;font-weight:900;color:#fff;flex-shrink:0;}

/* ── STAT CARDS (from meal_compliance.php) ── */
.stats-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;margin-bottom:1.5rem;}
.scard{background:#fff;border-radius:20px;border:1px solid rgba(216,226,234,.8);padding:1.25rem;box-shadow:0 16px 30px rgba(15,23,42,.05);}
.scard-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1rem;margin-bottom:.7rem;}
.scard-val{font-size:1.5rem;font-weight:800;color:var(--slate);line-height:1;margin-bottom:.2rem;}
.scard-label{font-size:.72rem;color:var(--muted);font-weight:600;}
.scard-sub{font-size:.75rem;margin-top:.5rem;display:flex;align-items:center;gap:.3rem;}
.scard-sub.ok{color:#16a34a;font-weight:700;}
.scard-sub.warn{color:#d97706;font-weight:700;}
.scard-sub.err{color:#dc2626;font-weight:700;}

/* ── CHARTS ROW ── */
.charts-row{display:grid;grid-template-columns:1.25fr 1fr;gap:1rem;margin-bottom:1.5rem;}
.chart-card{background:#fff;border-radius:22px;border:1px solid rgba(216,226,234,.8);padding:1.35rem;box-shadow:0 20px 45px rgba(15,23,42,.05);}
.chart-title{font-size:.92rem;font-weight:800;color:var(--slate);margin-bottom:.35rem;display:flex;align-items:center;justify-content:space-between;gap:.8rem;}
.chart-title i{color:var(--accent);}
.chart-sub{font-size:.75rem;color:var(--muted);margin-bottom:.9rem;}
.chart-wrap{position:relative;height:230px;}

/* ── DISTRIBUTIONS ── */
.dist-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1.5rem;}
.dist-card{background:#fff;border-radius:20px;border:1px solid rgba(216,226,234,.8);padding:1.25rem;box-shadow:0 16px 30px rgba(15,23,42,.05);}
.dist-title{font-size:.82rem;font-weight:800;color:var(--slate);margin-bottom:1rem;display:flex;align-items:center;gap:.4rem;}
.dist-title i{color:var(--blue);}
.dist-item{display:flex;align-items:center;gap:.6rem;margin-bottom:.6rem;}
.dist-item:last-child{margin-bottom:0;}
.dist-label{font-size:.72rem;font-weight:600;color:var(--muted);width:72px;white-space:nowrap;}
.dist-bar-wrap{flex:1;}
.dist-bar-track{height:6px;background:#f1f5f9;border-radius:999px;overflow:hidden;}
.dist-bar-fill{height:100%;border-radius:999px;}
.dist-val{font-size:.78rem;font-weight:800;color:var(--slate);width:24px;text-align:right;}

/* ── SYSTEM HEALTH ── */
.sec-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;}
.sec-hd h3{font-size:.9rem;font-weight:800;color:var(--slate);display:flex;align-items:center;gap:.5rem;}
.sec-hd h3 i{color:var(--blue);}
.sys-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:.6rem;margin-bottom:1.5rem;}
.sys-item{background:#fff;border-radius:12px;border:1px solid rgba(216,226,234,.8);padding:.85rem 1rem;display:flex;align-items:center;gap:.75rem;box-shadow:0 8px 20px rgba(15,23,42,.03);}
.sys-status{width:32px;height:32px;border-radius:9px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:.9rem;}
.sys-ok{background:#f0fdf4;color:#16a34a;}
.sys-err{background:#fef2f2;color:#ef4444;}
.sys-item-label{font-size:.8rem;font-weight:700;color:var(--slate);}
.sys-item-detail{font-size:.7rem;color:var(--muted);margin-top:.1rem;}

/* ── TABLES (from meal_compliance.php) ── */
.tables-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
.card{background:#fff;border-radius:22px;border:1px solid rgba(216,226,234,.8);padding:1.5rem;box-shadow:0 20px 45px rgba(15,23,42,.05);overflow:hidden;}
.card-title{font-size:.92rem;font-weight:800;color:var(--slate);margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;}
.card-title a{font-size:.75rem;color:var(--blue);font-weight:700;text-decoration:none;}
.card-title a:hover{text-decoration:underline;}
table{width:100%;border-collapse:collapse;}
th{font-size:.68rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;padding:.6rem .85rem;text-align:left;border-bottom:1px solid rgba(216,226,234,.9);white-space:nowrap;}
td{font-size:.8rem;color:var(--slate);padding:.65rem .85rem;border-bottom:1px solid #f8fafc;vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#fafbfc;}
.u-cell{display:flex;align-items:center;gap:.65rem;}
.mini-av{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--blue),#1e40af);display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;color:#fff;flex-shrink:0;}
.mini-av.female{background:linear-gradient(135deg,#be185d,#7c3aed);}
.u-name{font-weight:700;font-size:.82rem;}
.u-email{font-size:.7rem;color:var(--muted);}
.badge-on{background:#e0f2fe;color:#0c4a6e;font-size:.68rem;font-weight:700;padding:.22rem .55rem;border-radius:999px;}
.badge-off{background:#fee2e2;color:#991b1b;font-size:.68rem;font-weight:700;padding:.22rem .55rem;border-radius:999px;}
.prog-mini{display:flex;align-items:center;gap:.45rem;}
.prog-mini-track{flex:1;height:5px;background:#e2e8f0;border-radius:999px;overflow:hidden;}
.prog-mini-fill{height:100%;background:linear-gradient(90deg,#2563eb,#60a5fa);border-radius:999px;}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sb-logo">
    <img src="/myfitcal_system/assets/image/logo.png" alt="MyFitCal" style="height:54px;width:auto;object-fit:contain;filter:drop-shadow(0 2px 10px rgba(0,0,0,.4));flex-shrink:0;">
    <div>
      <div class="sb-logo-text">MyFitCal</div>
      <span class="sb-badge">ADMIN</span>
    </div>
  </div>
  <nav class="sb-nav">
    <span class="sb-section">Monitor</span>
    <a class="sb-item" href="dashboard.php"><div class="sb-link active"><i class="bi bi-speedometer2"></i> Dashboard</div></a>
    <a class="sb-item" href="users.php"><div class="sb-link"><i class="bi bi-people"></i> Users</div></a>
    <span class="sb-section" style="margin-top:.5rem">Data</span>
    <a class="sb-item" href="calories.php"><div class="sb-link"><i class="bi bi-fire"></i> Calories</div></a>
    <a class="sb-item" href="meal_compliance.php"><div class="sb-link"><i class="bi bi-egg-fried"></i> Meal Compliance</div></a>
  </nav>
  <div class="sb-footer">
    <div class="sb-user">
      <div class="sb-avatar"><?= strtoupper(substr($_SESSION['name'],0,1)) ?></div>
      <div><div class="sb-uname"><?= htmlspecialchars($_SESSION['name']) ?></div><div class="sb-urole">Administrator</div></div>
      <a class="sb-logout" href="/myfitcal_system/logout.php" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div>
      <h2>Dashboard</h2>
      <p>Overview of MyFitCal administration metrics</p>
    </div>
    <div class="date-pill"><i class="bi bi-calendar-event"></i> <?= date('l, F j, Y') ?></div>
  </div>

  <div class="content">

    <!-- HEALTH BANNER -->
    <div class="health-banner">
      <div class="hb-icon">
        <?php if($sys_health>=87): ?><i class="bi bi-shield-fill-check"></i>
        <?php elseif($sys_health>=60): ?><i class="bi bi-shield-fill-exclamation"></i>
        <?php else: ?><i class="bi bi-shield-fill-x"></i><?php endif; ?>
      </div>
      <div class="hb-info">
        <div class="hb-title">
          <?php if($sys_health>=87): ?>System Running Normally
          <?php elseif($sys_health>=60): ?>System Has Warnings
          <?php else: ?>System Needs Attention<?php endif; ?>
        </div>
        <div class="hb-sub"><?= $sys_ok ?> / <?= $sys_total ?> checks passed &mdash;
          <?php if($sys_health>=87): ?>All critical tables and data are healthy.
          <?php elseif($sys_health>=60): ?>Some tables or data may be missing.
          <?php else: ?>Multiple issues detected.<?php endif; ?>
        </div>
        <div class="hb-bar-track"><div class="hb-bar-fill" style="width:<?= $sys_health ?>%;"></div></div>
      </div>
      <div class="hb-pct"><?= $sys_health ?>%</div>
    </div>

    <!-- STAT CARDS -->
    <div class="stats-row">
      <div class="scard">
        <div class="scard-icon" style="background:rgba(37,99,235,.08);color:#2563eb;"><i class="bi bi-people-fill"></i></div>
        <div class="scard-val"><?= number_format($total_users) ?></div>
        <div class="scard-label">Total Users</div>
        <div class="scard-sub ok"><i class="bi bi-arrow-up-short"></i><?= $new_week ?> this week</div>
      </div>
      <div class="scard">
        <div class="scard-icon" style="background:rgba(22,163,74,.08);color:#16a34a;"><i class="bi bi-person-check-fill"></i></div>
        <div class="scard-val"><?= number_format($users_started) ?></div>
        <div class="scard-label">Started Workout</div>
        <div class="scard-sub ok"><?= $setup_pct ?>% setup complete</div>
      </div>
      <div class="scard">
        <div class="scard-icon" style="background:rgba(249,115,22,.08);color:#f97316;"><i class="bi bi-trophy-fill"></i></div>
        <div class="scard-val"><?= number_format($total_completions) ?></div>
        <div class="scard-label">Workout Days Done</div>
        <div class="scard-sub <?= $users_finished_30>0?'ok':'warn' ?>"><?= $users_finished_30 ?> finished 30 days</div>
      </div>
      <div class="scard">
        <div class="scard-icon" style="background:rgba(139,92,246,.08);color:#8b5cf6;"><i class="bi bi-person-plus-fill"></i></div>
        <div class="scard-val"><?= number_format($new_today) ?></div>
        <div class="scard-label">New Today</div>
        <div class="scard-sub <?= $inactive_users>0?'err':'ok' ?>">
          <?php if($inactive_users>0): ?><i class="bi bi-exclamation-triangle"></i> <?= $inactive_users ?> inactive<?php else: ?>All accounts active<?php endif; ?>
        </div>
      </div>
    </div>

    <!-- CHARTS -->
    <div class="charts-row">
      <div class="chart-card">
        <div class="chart-title"><span><i class="bi bi-bar-chart-fill"></i> User Registrations</span><span style="font-size:.7rem;color:var(--muted);font-weight:600;">Last 7 days</span></div>
        <div class="chart-sub">Daily new user sign-ups</div>
        <div class="chart-wrap"><canvas id="regChart"></canvas></div>
      </div>
      <div class="chart-card">
        <div class="chart-title"><span><i class="bi bi-graph-up"></i> Workout Completions</span><span style="font-size:.7rem;color:var(--muted);font-weight:600;">Last 30 days</span></div>
        <div class="chart-sub">Daily completed workout days across all users</div>
        <div class="chart-wrap"><canvas id="wkChart"></canvas></div>
      </div>
    </div>

    <!-- DISTRIBUTIONS -->
    <div class="dist-row">
      <div class="dist-card">
        <div class="dist-title"><i class="bi bi-bullseye"></i> Goal Distribution</div>
        <?php
        $goal_colors = ['lose'=>'#ef4444','gain'=>'#16a34a','maintain'=>'#2563eb','muscle'=>'#8b5cf6'];
        $goal_names  = ['lose'=>'Weight Loss','gain'=>'Weight Gain','maintain'=>'Maintain','muscle'=>'Muscle Gain'];
        $gd_total    = max(1, array_sum($gd));
        foreach($gd as $key => $val): ?>
        <div class="dist-item">
          <div class="dist-label"><?= $goal_names[$key] ?></div>
          <div class="dist-bar-wrap"><div class="dist-bar-track"><div class="dist-bar-fill" style="width:<?= round($val/$gd_total*100) ?>%;background:<?= $goal_colors[$key] ?>;"></div></div></div>
          <div class="dist-val"><?= $val ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="dist-card">
        <div class="dist-title"><i class="bi bi-gender-ambiguous"></i> Gender Distribution</div>
        <?php
        $gen_colors = ['male'=>'#2563eb','female'=>'#ec4899','other'=>'#94a3b8'];
        $gen_total  = max(1, array_sum($genders));
        foreach($genders as $key => $val): ?>
        <div class="dist-item">
          <div class="dist-label"><?= ucfirst($key) ?></div>
          <div class="dist-bar-wrap"><div class="dist-bar-track"><div class="dist-bar-fill" style="width:<?= round($val/$gen_total*100) ?>%;background:<?= $gen_colors[$key] ?>;"></div></div></div>
          <div class="dist-val"><?= $val ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="dist-card">
        <div class="dist-title"><i class="bi bi-bar-chart-fill"></i> Fitness Levels</div>
        <?php
        $lvl_colors = ['beginner'=>'#3b82f6','normal'=>'#16a34a','expert'=>'#f97316','advance'=>'#dc2626'];
        $lvl_total  = max(1, array_sum($levels));
        foreach($levels as $key => $val): ?>
        <div class="dist-item">
          <div class="dist-label"><?= ucfirst($key) ?></div>
          <div class="dist-bar-wrap"><div class="dist-bar-track"><div class="dist-bar-fill" style="width:<?= round($val/$lvl_total*100) ?>%;background:<?= $lvl_colors[$key] ?>;"></div></div></div>
          <div class="dist-val"><?= $val ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- SYSTEM HEALTH -->
    <div class="sec-hd">
      <h3><i class="bi bi-heart-pulse-fill"></i> System Health Checks</h3>
      <span style="font-size:.75rem;font-weight:700;color:<?= $sys_health>=87?'#16a34a':($sys_health>=60?'#f97316':'#ef4444') ?>;"><?= $sys_ok ?>/<?= $sys_total ?> OK</span>
    </div>
    <div class="sys-grid">
      <?php foreach($sys_checks as $check): ?>
      <div class="sys-item">
        <div class="sys-status <?= $check['ok']?'sys-ok':'sys-err' ?>"><i class="bi bi-<?= $check['ok']?'check-circle-fill':'exclamation-triangle-fill' ?>"></i></div>
        <div><div class="sys-item-label"><?= $check['label'] ?></div><div class="sys-item-detail"><?= $check['detail'] ?></div></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- TABLES -->
    <div class="tables-row">
      <div class="card">
        <div class="card-title">Recent Registrations <a href="users.php">View all &rarr;</a></div>
        <table>
          <thead><tr><th>User</th><th>Gender</th><th>Joined</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach($recent_users as $u): ?>
          <tr>
            <td>
              <div class="u-cell">
                <div class="mini-av <?= $u['gender']==='female'?'female':'' ?>"><?= strtoupper(substr($u['name'],0,1)) ?></div>
                <div><div class="u-name"><?= htmlspecialchars($u['name']) ?></div><div class="u-email"><?= htmlspecialchars($u['email']) ?></div></div>
              </div>
            </td>
            <td><?= $u['gender'] ? ucfirst($u['gender']) : '—' ?></td>
            <td style="font-size:.73rem;"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
            <td><span class="<?= $u['is_active']?'badge-on':'badge-off' ?>"><?= $u['is_active']?'Active':'Inactive' ?></span></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card">
        <div class="card-title">Top Users by Progress <a href="users.php">View all &rarr;</a></div>
        <table>
          <thead><tr><th>User</th><th>Progress</th><th>Days</th></tr></thead>
          <tbody>
          <?php foreach($top_users as $u):
            $pct = min(100, round($u['days_done']/30*100));
          ?>
          <tr>
            <td>
              <div class="u-cell">
                <div class="mini-av <?= $u['gender']==='female'?'female':'' ?>"><?= strtoupper(substr($u['name'],0,1)) ?></div>
                <div><div class="u-name"><?= htmlspecialchars($u['name']) ?></div><div class="u-email"><?= htmlspecialchars($u['email']) ?></div></div>
              </div>
            </td>
            <td>
              <div class="prog-mini">
                <div class="prog-mini-track"><div class="prog-mini-fill" style="width:<?= $pct ?>%;"></div></div>
                <span style="font-size:.68rem;font-weight:700;color:var(--muted);"><?= $pct ?>%</span>
              </div>
            </td>
            <td style="font-weight:800;font-size:.82rem;"><?= $u['days_done'] ?><span style="color:var(--muted);font-weight:500;">/30</span></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script>
const font = "'Plus Jakarta Sans', sans-serif";
const gridColor = '#f1f5f9';
new Chart(document.getElementById('regChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($reg_labels) ?>,
    datasets: [{label:'New Users',data:<?= json_encode($reg_data) ?>,backgroundColor:'rgba(37,99,235,.8)',borderRadius:8,borderSkipped:false,hoverBackgroundColor:'#2563eb'}]
  },
  options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false},ticks:{font:{family:font,size:10},maxRotation:45}},y:{grid:{color:gridColor},ticks:{font:{family:font,size:10},stepSize:1},beginAtZero:true}}}
});
new Chart(document.getElementById('wkChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($wk_labels) ?>,
    datasets: [{label:'Completions',data:<?= json_encode($wk_data) ?>,borderColor:'#16a34a',backgroundColor:'rgba(22,163,74,.08)',tension:0.4,fill:true,pointRadius:0,pointHoverRadius:5,pointBackgroundColor:'#16a34a',borderWidth:2.5}]
  },
  options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false},ticks:{font:{family:font,size:10}}},y:{grid:{color:gridColor},ticks:{font:{family:font,size:10},stepSize:1},beginAtZero:true}}}
});
</script>
</body>
</html>