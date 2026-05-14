cat > /mnt/user-data/outputs/workout_progress.php << 'PHPEOF'
<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../config/exercises.php';
requireAdmin();
$db = getDB();

$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15; $offset = ($page - 1) * $limit;

// Selected user for drill-down
$selected_uid = (int)($_GET['uid'] ?? 0);

// ── USER LIST with workout stats ──
$where  = "WHERE u.role='user'";
$params = [];
if ($search) {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$tc = $db->prepare("SELECT COUNT(*) FROM users u $where");
$tc->execute($params);
$total_count = (int)$tc->fetchColumn();
$total_pages = ceil($total_count / $limit);

$stmt = $db->prepare("
    SELECT u.id, u.name, u.email, u.gender,
           COUNT(DISTINCT wp.day_number) as days_done,
           MAX(wp.completed_at) as last_workout,
           uf.fitness_level, ug.goal_type
    FROM users u
    LEFT JOIN user_workout_progress wp ON wp.user_id = u.id AND wp.completed=1
    LEFT JOIN user_fitness uf ON uf.user_id = u.id
    LEFT JOIN user_goals ug ON ug.user_id = u.id
    $where
    GROUP BY u.id
    ORDER BY days_done DESC, u.name ASC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// ── SELECTED USER DETAIL ──
$sel_user    = null;
$sel_days    = [];
$sel_schedule = [];

if ($selected_uid) {
    $uq = $db->prepare("SELECT u.*, uf.fitness_level, uf.activity_level, uf.days_per_week, ug.goal_type FROM users u LEFT JOIN user_fitness uf ON uf.user_id=u.id LEFT JOIN user_goals ug ON ug.user_id=u.id WHERE u.id=?");
    $uq->execute([$selected_uid]);
    $sel_user = $uq->fetch();

    if ($sel_user) {
        // Get all completed days
        $dq = $db->prepare("SELECT DISTINCT day_number, completed_at FROM user_workout_progress WHERE user_id=? AND completed=1 ORDER BY day_number ASC");
        $dq->execute([$selected_uid]);
        $done_rows = $dq->fetchAll();
        foreach ($done_rows as $r) $sel_days[$r['day_number']] = $r['completed_at'];

        // Build schedule for this user
        $level    = $sel_user['fitness_level']  ?? 'beginner';
        $activity = $sel_user['activity_level'] ?? 'sedentary';
        $goal     = $sel_user['goal_type']      ?? 'maintain';
        $days_pw  = $sel_user['days_per_week']  ?? 3;
        $gender   = strtolower($sel_user['gender'] ?? 'male');
        $sel_schedule = getExercisePlan($level, $activity, $goal, $days_pw, $gender);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Workout Progress — MyFitCal Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--sb:#050505;--sb-w:248px;--blue:#1f4f7d;--blue-d:#174a7a;--slate:#111827;--muted:#52606d;--border:rgba(216,226,234,.9);--bg:#ebeff3;--accent:#1f4f7d;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--slate);}

.sidebar{position:fixed;left:0;top:0;bottom:0;width:var(--sb-w);background:var(--sb);display:flex;flex-direction:column;z-index:200;border-right:1px solid rgba(255,255,255,.08);}
.sb-logo{padding:1.5rem 1.25rem 1rem;display:flex;align-items:center;gap:.8rem;border-bottom:1px solid rgba(255,255,255,.06);}
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

.main{margin-left:var(--sb-w);min-height:100vh;}
.topbar{background:#fff;border-bottom:1px solid rgba(216,226,234,.7);padding:1rem 2rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 10px 30px rgba(15,23,42,.04);}
.topbar h2{font-size:1.15rem;font-weight:900;color:var(--slate);}
.topbar p{font-size:.78rem;color:var(--muted);margin-top:.15rem;}
.content{padding:1.75rem 2rem;}

/* ── LAYOUT ── */
.two-col{display:grid;grid-template-columns:420px 1fr;gap:1.25rem;align-items:start;}
@media(max-width:1100px){.two-col{grid-template-columns:1fr;}}

/* ── CARDS ── */
.card{background:#fff;border-radius:22px;border:1px solid rgba(216,226,234,.8);box-shadow:0 20px 45px rgba(15,23,42,.05);overflow:hidden;}
.card-head{padding:1.1rem 1.35rem;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;gap:.75rem;}
.card-head h3{font-size:.92rem;font-weight:800;color:var(--slate);display:flex;align-items:center;gap:.4rem;}
.card-head h3 i{color:var(--blue);}
.card-body{padding:1.1rem 1.35rem;}

/* ── SEARCH ── */
.search-wrap{position:relative;}
.search-wrap input{width:100%;padding:.6rem 1rem .6rem 2.3rem;border-radius:13px;border:1.5px solid rgba(216,226,234,.85);font-family:'Plus Jakarta Sans',sans-serif;font-size:.85rem;outline:none;background:#f8fafc;color:var(--slate);}
.search-wrap input:focus{border-color:var(--accent);background:#fff;box-shadow:0 0 0 4px rgba(31,79,125,.08);}
.search-wrap i{position:absolute;left:.85rem;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.95rem;}

/* ── USER ROWS ── */
.user-row{display:flex;align-items:center;gap:.85rem;padding:.85rem 1.35rem;border-bottom:1px solid #f8fafc;cursor:pointer;transition:background .12s;text-decoration:none;color:inherit;}
.user-row:last-child{border-bottom:none;}
.user-row:hover{background:#f8fafc;}
.user-row.selected{background:#eff6ff;border-left:3px solid var(--accent);}
.mini-av{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:800;color:#fff;flex-shrink:0;background:linear-gradient(135deg,var(--blue),#1e40af);}
.mini-av.female{background:linear-gradient(135deg,#be185d,#7c3aed);}
.user-info{flex:1;min-width:0;}
.user-name{font-size:.85rem;font-weight:700;color:var(--slate);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.user-email{font-size:.7rem;color:var(--muted);}
.user-prog{text-align:right;flex-shrink:0;}
.prog-days{font-size:1rem;font-weight:900;color:var(--slate);line-height:1;}
.prog-label{font-size:.62rem;color:var(--muted);}
.prog-bar-wrap{width:70px;margin-top:4px;}
.prog-track{height:4px;background:#e2e8f0;border-radius:999px;overflow:hidden;}
.prog-fill{height:100%;background:linear-gradient(90deg,#2563eb,#60a5fa);border-radius:999px;}

/* ── DETAIL PANEL ── */
.user-header{display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;}
.user-avatar-lg{width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.2rem;font-weight:800;color:#fff;background:linear-gradient(135deg,var(--blue),#1e40af);flex-shrink:0;}
.user-avatar-lg.female{background:linear-gradient(135deg,#be185d,#7c3aed);}
.user-hname{font-size:1rem;font-weight:800;color:var(--slate);}
.user-hmeta{font-size:.75rem;color:var(--muted);margin-top:2px;}

.stats-mini{display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem;margin-bottom:1.25rem;}
.sm-card{background:#f8fafc;border-radius:12px;padding:.75rem;text-align:center;}
.sm-val{font-size:1.3rem;font-weight:900;color:var(--slate);}
.sm-lbl{font-size:.65rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em;}

/* ── 30-DAY GRID ── */
.day-grid-title{font-size:.8rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.75rem;}
.day-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:.5rem;margin-bottom:1.25rem;}
.day-cell{border-radius:10px;padding:.5rem .25rem;text-align:center;cursor:pointer;transition:all .15s;border:1.5px solid transparent;}
.day-cell.done{background:#dcfce7;border-color:#86efac;}
.day-cell.done:hover{background:#bbf7d0;}
.day-cell.rest{background:#f1f5f9;cursor:default;}
.day-cell.upcoming{background:#f8fafc;border-color:#e2e8f0;}
.day-cell.upcoming:hover{border-color:#93c5fd;background:#eff6ff;}
.day-num{font-size:.72rem;font-weight:800;color:var(--slate);}
.day-cell.done .day-num{color:#16a34a;}
.day-cell.rest .day-num{color:#cbd5e1;}
.day-check{font-size:.65rem;margin-top:2px;}

/* ── DAY DETAIL POPUP ── */
.day-detail{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:14px;padding:1rem 1.1rem;margin-bottom:1rem;display:none;}
.day-detail.show{display:block;}
.day-detail-title{font-size:.88rem;font-weight:800;color:#166534;margin-bottom:.6rem;display:flex;align-items:center;gap:.4rem;}
.ex-list-item{display:flex;align-items:center;gap:.6rem;padding:.4rem 0;border-bottom:1px solid rgba(34,197,94,.1);font-size:.78rem;color:#166534;}
.ex-list-item:last-child{border-bottom:none;}
.ex-list-item i{color:#16a34a;font-size:.8rem;flex-shrink:0;}
.completed-time{font-size:.7rem;color:#16a34a;margin-top:.4rem;display:flex;align-items:center;gap:.3rem;}

/* ── EMPTY STATE ── */
.empty-panel{text-align:center;padding:3rem 2rem;color:var(--muted);}
.empty-panel i{font-size:2.5rem;opacity:.25;display:block;margin-bottom:.75rem;}
.empty-panel p{font-size:.88rem;}

/* ── LEGEND ── */
.legend{display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;}
.leg-item{display:flex;align-items:center;gap:.4rem;font-size:.72rem;color:var(--muted);}
.leg-dot{width:12px;height:12px;border-radius:4px;}

/* ── PAGINATION ── */
.pagination{display:flex;gap:.4rem;margin-top:.75rem;padding:0 1.35rem 1rem;justify-content:center;}
.page-btn{min-width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;text-decoration:none;border:1.5px solid rgba(216,226,234,.85);color:var(--muted);transition:all .2s;}
.page-btn:hover{background:rgba(31,79,125,.08);}
.page-btn.active{background:var(--accent);color:#fff;border-color:var(--accent);}

.badge{font-size:.65rem;font-weight:700;padding:.2rem .5rem;border-radius:5px;}
.badge-beginner{background:#dbeafe;color:#1e40af;}
.badge-normal  {background:#dcfce7;color:#16a34a;}
.badge-expert  {background:#fff7ed;color:#c2410c;}
.badge-advance {background:#fee2e2;color:#dc2626;}
.badge-lose    {background:#fee2e2;color:#dc2626;}
.badge-gain    {background:#dcfce7;color:#16a34a;}
.badge-maintain{background:#dbeafe;color:#2563eb;}
.badge-muscle  {background:#f3e8ff;color:#7c3aed;}
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
    <a class="sb-item" href="dashboard.php"><div class="sb-link"><i class="bi bi-speedometer2"></i> Dashboard</div></a>
    <a class="sb-item" href="users.php"><div class="sb-link"><i class="bi bi-people"></i> Users</div></a>
    <a class="sb-item" href="workout_progress.php"><div class="sb-link active"><i class="bi bi-activity"></i> Workout Progress</div></a>
    <span class="sb-section" style="margin-top:.5rem">Data</span>
    <a class="sb-item" href="calories.php"><div class="sb-link"><i class="bi bi-fire"></i> Calories</div></a>
    <a class="sb-item" href="meal_compliance.php"><div class="sb-link"><i class="bi bi-egg-fried"></i> Meal Compliance</div></a>
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
    <div>
      <h2>Workout Progress</h2>
      <p>Per-user 30-day workout tracking</p>
    </div>
  </div>

  <div class="content">
    <div class="two-col">

      <!-- LEFT: USER LIST -->
      <div class="card">
        <div class="card-head">
          <h3><i class="bi bi-people-fill"></i> Users</h3>
          <span style="font-size:.75rem;color:var(--muted);font-weight:600;"><?= $total_count ?> total</span>
        </div>
        <div class="card-body" style="padding-bottom:.5rem;">
          <form method="GET" style="margin-bottom:.75rem;">
            <div class="search-wrap">
              <i class="bi bi-search"></i>
              <input type="text" name="q" placeholder="Search user..." value="<?= htmlspecialchars($search) ?>"
                     onchange="this.form.submit()">
              <?php if ($selected_uid): ?>
              <input type="hidden" name="uid" value="<?= $selected_uid ?>">
              <?php endif; ?>
            </div>
          </form>
        </div>

        <?php foreach ($users as $u):
          $pct = min(100, round($u['days_done'] / 30 * 100));
          $is_sel = $selected_uid === (int)$u['id'];
          $qs = $search ? "&q=".urlencode($search) : '';
        ?>
        <a href="?uid=<?= $u['id'] ?><?= $qs ?>&page=<?= $page ?>"
           class="user-row <?= $is_sel ? 'selected' : '' ?>">
          <div class="mini-av <?= strtolower($u['gender'] ?? '') === 'female' ? 'female' : '' ?>">
            <?= strtoupper(substr($u['name'], 0, 1)) ?>
          </div>
          <div class="user-info">
            <div class="user-name"><?= htmlspecialchars($u['name']) ?></div>
            <div class="user-email"><?= htmlspecialchars($u['email']) ?></div>
            <div style="margin-top:4px;">
              <?php if ($u['fitness_level']): ?>
              <span class="badge badge-<?= $u['fitness_level'] ?>"><?= ucfirst($u['fitness_level']) ?></span>
              <?php endif; ?>
              <?php if ($u['goal_type']): ?>
              <span class="badge badge-<?= $u['goal_type'] ?>"><?= ucfirst($u['goal_type']) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="user-prog">
            <div class="prog-days"><?= $u['days_done'] ?><span style="font-size:.65rem;color:var(--muted);font-weight:500;">/30</span></div>
            <div class="prog-label">days done</div>
            <div class="prog-bar-wrap">
              <div class="prog-track"><div class="prog-fill" style="width:<?= $pct ?>%;"></div></div>
            </div>
          </div>
        </a>
        <?php endforeach; ?>

        <?php if (empty($users)): ?>
        <div class="empty-panel"><i class="bi bi-people"></i><p>No users found.</p></div>
        <?php endif; ?>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
          <?php for ($p = 1; $p <= $total_pages; $p++): ?>
          <a href="?page=<?= $p ?><?= $selected_uid ? "&uid=$selected_uid" : '' ?><?= $search ? "&q=".urlencode($search) : '' ?>"
             class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
          <?php endfor; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- RIGHT: USER DETAIL -->
      <div class="card">
        <?php if (!$sel_user): ?>
        <div class="empty-panel" style="padding:4rem 2rem;">
          <i class="bi bi-cursor-fill"></i>
          <p>Select a user to view their 30-day workout progress.</p>
        </div>

        <?php else: ?>
        <div class="card-head">
          <h3><i class="bi bi-activity"></i> <?= htmlspecialchars(explode(' ', $sel_user['name'])[0]) ?>'s Progress</h3>
          <a href="?" style="font-size:.75rem;color:var(--muted);text-decoration:none;">← Back</a>
        </div>
        <div class="card-body">

          <!-- User header -->
          <div class="user-header">
            <div class="user-avatar-lg <?= strtolower($sel_user['gender'] ?? '') === 'female' ? 'female' : '' ?>">
              <?= strtoupper(substr($sel_user['name'], 0, 1)) ?>
            </div>
            <div>
              <div class="user-hname"><?= htmlspecialchars($sel_user['name']) ?></div>
              <div class="user-hmeta"><?= htmlspecialchars($sel_user['email']) ?> &middot; <?= ucfirst($sel_user['gender'] ?? '—') ?></div>
              <div style="margin-top:5px;">
                <?php if ($sel_user['fitness_level']): ?>
                <span class="badge badge-<?= $sel_user['fitness_level'] ?>"><?= ucfirst($sel_user['fitness_level']) ?></span>
                <?php endif; ?>
                <?php if ($sel_user['goal_type']): ?>
                <span class="badge badge-<?= $sel_user['goal_type'] ?>"><?= ucfirst($sel_user['goal_type']) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Mini stats -->
          <?php
            $days_done  = count($sel_days);
            $days_left  = 30 - $days_done;
            $pct_done   = round($days_done / 30 * 100);
            $streak     = 0;
            $last_done  = max(array_keys($sel_days) ?: [0]);
            for ($d = $last_done; $d >= 1; $d--) {
                if (isset($sel_days[$d])) $streak++; else break;
            }
          ?>
          <div class="stats-mini">
            <div class="sm-card">
              <div class="sm-val" style="color:#16a34a;"><?= $days_done ?></div>
              <div class="sm-lbl">Days Done</div>
            </div>
            <div class="sm-card">
              <div class="sm-val" style="color:#f97316;"><?= $streak ?></div>
              <div class="sm-lbl">Streak</div>
            </div>
            <div class="sm-card">
              <div class="sm-val" style="color:#2563eb;"><?= $pct_done ?>%</div>
              <div class="sm-lbl">Complete</div>
            </div>
          </div>

          <!-- Legend -->
          <div class="legend">
            <div class="leg-item"><div class="leg-dot" style="background:#dcfce7;border:1.5px solid #86efac;"></div> Done</div>
            <div class="leg-item"><div class="leg-dot" style="background:#f1f5f9;"></div> Rest Day</div>
            <div class="leg-item"><div class="leg-dot" style="background:#f8fafc;border:1.5px solid #e2e8f0;"></div> Not yet done</div>
          </div>

          <!-- 30-day grid -->
          <div class="day-grid-title">30-Day Plan</div>
          <div class="day-grid">
            <?php for ($d = 1; $d <= 30; $d++):
              $is_done = isset($sel_days[$d]);
              $is_rest = $sel_schedule[$d]['is_rest'] ?? false;
              $cls = $is_done ? 'done' : ($is_rest ? 'rest' : 'upcoming');
            ?>
            <div class="day-cell <?= $cls ?>"
                 onclick="<?= (!$is_rest) ? "showDay($d)" : '' ?>"
                 title="Day <?= $d ?>: <?= htmlspecialchars($sel_schedule[$d]['focus'] ?? '') ?>">
              <div class="day-num"><?= $d ?></div>
              <div class="day-check">
                <?= $is_done ? '✅' : ($is_rest ? '😴' : '') ?>
              </div>
            </div>
            <?php endfor; ?>
          </div>

          <!-- Day detail popup -->
          <div class="day-detail" id="dayDetail">
            <div class="day-detail-title" id="dayDetailTitle"></div>
            <div id="dayDetailBody"></div>
          </div>

        </div><!-- /card-body -->
        <?php endif; ?>
      </div><!-- /right card -->

    </div>
  </div>
</div>

<script>
const schedule = <?= json_encode($sel_schedule) ?>;
const selDays  = <?= json_encode($sel_days) ?>;

function showDay(d) {
    const detail = document.getElementById('dayDetail');
    const title  = document.getElementById('dayDetailTitle');
    const body   = document.getElementById('dayDetailBody');
    const day    = schedule[d];
    if (!day) return;

    const isDone = selDays[d];
    title.innerHTML = `<i class="bi bi-${isDone ? 'check-circle-fill' : 'calendar3'}"></i> Day ${d} — ${day.focus}`;

    let html = '';
    if (isDone) {
        html += `<div class="completed-time"><i class="bi bi-clock-fill"></i> Completed: ${isDone}</div><br>`;
    }

    if (day.exercises && day.exercises.length) {
        day.exercises.forEach((ex, i) => {
            html += `<div class="ex-list-item">
                <i class="bi bi-${isDone ? 'check-circle-fill' : 'circle'}"></i>
                <span><strong>${ex.name}</strong> — ${ex.sets} sets × ${ex.reps} · ${ex.muscle}</span>
            </div>`;
        });
    } else {
        html += '<div class="ex-list-item"><i class="bi bi-moon-stars-fill"></i> Rest Day — No exercises</div>';
    }

    body.innerHTML = html;
    detail.classList.add('show');
    detail.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
</script>
</body>
</html>
PHPEOF
echo "Done"