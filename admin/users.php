<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireAdmin();
$db = getDB();

$msg      = '';
$msg_type = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $uid    = (int)($_POST['uid'] ?? 0);

    if ($action === 'toggle' && $uid) {
        $cur = $db->prepare("SELECT is_active FROM users WHERE id=?");
        $cur->execute([$uid]);
        $row = $cur->fetch();
        if ($row) { $db->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([!$row['is_active'], $uid]); $msg = 'User status updated.'; }
    }
    if ($action === 'delete' && $uid) {
        $db->prepare("DELETE FROM users WHERE id=? AND role='user'")->execute([$uid]);
        $msg = 'User deleted.'; $msg_type = 'danger';
    }
    if ($action === 'unlock' && $uid) {
        $db->prepare("UPDATE users SET failed_attempts=0, locked_until=NULL WHERE id=?")->execute([$uid]);
        $msg = 'User account has been unlocked successfully.'; $msg_type = 'warn';
    }
    if ($action === 'approve' && $uid) {
        $db->prepare("UPDATE users SET status='active', is_active=1 WHERE id=?")->execute([$uid]);
        $u = $db->prepare("SELECT name, email FROM users WHERE id=?"); $u->execute([$uid]); $udata = $u->fetch();
        if ($udata) {
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f0f4f8;font-family:\'Segoe UI\',Arial,sans-serif;"><table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:32px 16px;"><tr><td align="center"><table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;"><tr><td style="background:linear-gradient(135deg,#0a1628,#0d2137,#0a2e1f);border-radius:20px 20px 0 0;padding:32px;text-align:center;"><h1 style="margin:0;font-size:26px;color:#fff;font-weight:900;">Account Approved! 🎉</h1><p style="margin:10px 0 0;font-size:14px;color:rgba(255,255,255,.5);">Welcome to MyFitCal</p></td></tr><tr><td style="background:#fff;padding:32px;"><p style="font-size:15px;color:#111827;">Hi <strong>'.htmlspecialchars($udata['name']).'</strong>,</p><p style="font-size:15px;color:#374151;line-height:1.7;">Your <strong>MyFitCal</strong> account has been <strong style="color:#16a34a;">approved</strong>!</p><div style="text-align:center;margin:28px 0;"><a href="http://localhost/myfitcal_system/login.php" style="display:inline-block;background:#16a34a;color:#fff;text-decoration:none;font-size:15px;font-weight:800;padding:14px 40px;border-radius:12px;">Log In to MyFitCal →</a></div></td></tr></table></td></tr></table></body></html>';
            sendMail($udata['email'], $udata['name'], '✅ Your MyFitCal Account is Approved!', $html);
        }
        $msg = 'Account approved. User has been notified via email.'; $msg_type = 'ok';
    }
    if ($action === 'reject' && $uid) {
        $db->prepare("UPDATE users SET status='rejected', is_active=0 WHERE id=?")->execute([$uid]);
        $u = $db->prepare("SELECT name, email FROM users WHERE id=?"); $u->execute([$uid]); $udata = $u->fetch();
        if ($udata) {
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f0f4f8;font-family:\'Segoe UI\',Arial,sans-serif;"><table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:32px 16px;"><tr><td align="center"><table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;"><tr><td style="background:linear-gradient(135deg,#450a0a,#7f1d1d,#450a0a);border-radius:20px 20px 0 0;padding:32px;text-align:center;"><h1 style="margin:0;font-size:26px;color:#fff;font-weight:900;">Account Registration Update</h1></td></tr><tr><td style="background:#fff;padding:32px;"><p style="font-size:15px;color:#111827;">Hi <strong>'.htmlspecialchars($udata['name']).'</strong>,</p><p style="font-size:15px;color:#374151;line-height:1.7;">Unfortunately your registration was <strong style="color:#dc2626;">not approved</strong>.</p></td></tr></table></td></tr></table></body></html>';
            sendMail($udata['email'], $udata['name'], 'MyFitCal Account Registration Update', $html);
        }
        $msg = 'Account rejected. User has been notified via email.'; $msg_type = 'danger';
    }
}

$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'pending';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15; $offset = ($page-1)*$limit;

$where = "WHERE u.role='user'"; $params = [];
if ($search) { $where .= " AND (u.name LIKE ? OR u.email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filter === 'active')   $where .= " AND u.status='active' AND u.is_active=1";
if ($filter === 'inactive') $where .= " AND u.status='active' AND u.is_active=0";
if ($filter === 'pending')  $where .= " AND u.status='pending'";
if ($filter === 'rejected') $where .= " AND u.status='rejected'";
if ($filter === 'locked')   $where .= " AND u.locked_until IS NOT NULL AND u.locked_until > NOW()";

$tc = $db->prepare("SELECT COUNT(*) FROM users u $where"); $tc->execute($params);
$total_count = (int)$tc->fetchColumn();
$total_pages = ceil($total_count / $limit);

$locked_count  = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='user' AND locked_until IS NOT NULL AND locked_until > NOW()")->fetchColumn();
$pending_count = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='user' AND status='pending'")->fetchColumn();

$stmt = $db->prepare("
    SELECT u.*, COUNT(DISTINCT f.id) as workouts, COUNT(DISTINCT c.id) as meal_logs, ug.goal_type,
           (SELECT logged_at FROM bmi_logs WHERE user_id=u.id ORDER BY logged_at DESC LIMIT 1) as last_bmi
    FROM users u
    LEFT JOIN fitness_activities f ON f.user_id=u.id
    LEFT JOIN calorie_logs c ON c.user_id=u.id
    LEFT JOIN user_goals ug ON ug.user_id=u.id
    $where GROUP BY u.id ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Users — MyFitCal Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --sb:#050505;--sb-w:248px;
  --blue:#1f4f7d;--blue-d:#174a7a;
  --slate:#111827;--muted:#52606d;--border:rgba(216,226,234,.9);--bg:#ebeff3;--accent:#1f4f7d;
}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--slate);}

/* ── SIDEBAR ── */
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

/* ── MAIN ── */
.main{margin-left:var(--sb-w);min-height:100vh;}
.topbar{background:#fff;border-bottom:1px solid rgba(216,226,234,.7);padding:1rem 2rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 10px 30px rgba(15,23,42,.04);}
.topbar h2{font-size:1.15rem;font-weight:900;color:var(--slate);letter-spacing:-.02em;}
.topbar p{font-size:.78rem;color:var(--muted);margin-top:.15rem;}
.content{padding:1.75rem 2rem;}

/* ── CARD ── */
.card{background:#fff;border-radius:22px;border:1px solid rgba(216,226,234,.8);padding:1.5rem;box-shadow:0 20px 45px rgba(15,23,42,.05);}

/* ── ALERT ── */
.alert{border-radius:12px;padding:.7rem 1rem;margin-bottom:1rem;font-size:.82rem;font-weight:600;display:flex;align-items:center;gap:.5rem;}
.alert-ok     {background:#dcfce7;border:1px solid #86efac;color:#166534;}
.alert-warn   {background:#fff7ed;border:1px solid #fed7aa;color:#c2410c;}
.alert-danger {background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;}
.alert-info   {background:#dbeafe;border:1px solid #93c5fd;color:#1e40af;}

/* ── FILTERS (from meal_compliance.php) ── */
.filters{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:1.35rem;}
.search-wrap{position:relative;flex:1;min-width:220px;max-width:340px;}
.search-wrap input{width:100%;padding:.6rem 1rem .6rem 2.3rem;border-radius:13px;border:1.5px solid rgba(216,226,234,.85);font-family:'Plus Jakarta Sans',sans-serif;font-size:.9rem;outline:none;background:#fff;color:var(--slate);}
.search-wrap input:focus{border-color:var(--accent);box-shadow:0 0 0 4px rgba(31,79,125,.08);}
.search-wrap i{position:absolute;left:.85rem;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.95rem;}
.filter-tabs{display:flex;gap:.5rem;flex-wrap:wrap;}
.ftab{padding:.55rem 1rem;border-radius:13px;border:1.5px solid rgba(216,226,234,.85);background:#fff;font-family:'Plus Jakarta Sans',sans-serif;font-size:.82rem;font-weight:700;color:var(--muted);cursor:pointer;text-decoration:none;transition:all .2s;display:flex;align-items:center;gap:.35rem;}
.ftab:hover{background:rgba(31,79,125,.08);color:var(--slate);}
.ftab.active{background:var(--accent);color:#fff;border-color:var(--accent);}
.ftab.pending-tab:not(.active){color:#92400e;border-color:#fcd34d;}
.ftab.pending-tab:not(.active):hover{background:#fef3c7;}
.ftab.pending-tab.active{background:#d97706;border-color:#d97706;}
.ftab.rejected-tab:not(.active){color:#991b1b;}
.ftab.rejected-tab:not(.active):hover{background:#fee2e2;border-color:#fca5a5;}
.ftab.rejected-tab.active{background:#dc2626;border-color:#dc2626;}
.ftab.locked-tab.active{background:#b91c1c;border-color:#b91c1c;}
.ftab.locked-tab:not(.active):hover{background:#fee2e2;color:#b91c1c;border-color:#fca5a5;}
.tab-badge{font-size:.65rem;padding:.1rem .45rem;border-radius:5px;font-weight:800;}
.tab-badge-pending{background:#fef3c7;color:#92400e;}
.tab-badge-lock   {background:#fee2e2;color:#b91c1c;}
.ftab.active .tab-badge-pending,.ftab.active .tab-badge-lock{background:rgba(255,255,255,.25);color:#fff;}

/* ── TABLE ── */
table{width:100%;border-collapse:collapse;}
th{font-size:.68rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;padding:.6rem .85rem;text-align:left;border-bottom:1px solid rgba(216,226,234,.9);white-space:nowrap;}
td{font-size:.8rem;color:var(--slate);padding:.65rem .85rem;border-bottom:1px solid #f8fafc;vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#fafbfc;}
.u-cell{display:flex;align-items:center;gap:.65rem;}
.mini-av{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--blue),#1e40af);display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:800;color:#fff;flex-shrink:0;}
.mini-av.female{background:linear-gradient(135deg,#be185d,#7c3aed);}
.u-name{font-weight:700;color:var(--slate);}
.u-email{font-size:.7rem;color:var(--muted);}

/* ── BADGES ── */
.badge{font-size:.65rem;font-weight:700;padding:.2rem .55rem;border-radius:5px;display:inline-flex;align-items:center;gap:.25rem;}
.badge-active  {background:#dcfce7;color:#16a34a;}
.badge-inactive{background:#fee2e2;color:#dc2626;}
.badge-locked  {background:#fff7ed;color:#c2410c;}
.badge-pending {background:#fef3c7;color:#92400e;}
.badge-rejected{background:#fee2e2;color:#991b1b;}
.badge-lose    {background:#fee2e2;color:#dc2626;}
.badge-gain    {background:#dcfce7;color:#16a34a;}
.badge-maintain{background:#dbeafe;color:#2563eb;}
.badge-none    {background:#f1f5f9;color:var(--muted);}
.lock-indicator{display:inline-flex;align-items:center;gap:.3rem;font-size:.65rem;font-weight:700;color:#c2410c;background:#fff7ed;border:1px solid #fed7aa;padding:.2rem .5rem;border-radius:5px;margin-top:3px;}

/* ── ACTION BUTTONS ── */
.actions-cell{display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;}
.btn-sm{font-size:.75rem;font-weight:700;padding:.4rem .75rem;border-radius:9px;border:none;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:all .2s;white-space:nowrap;display:inline-flex;align-items:center;gap:.3rem;}
.btn-toggle {background:#e8f2fb;color:#1f4f7d;}
.btn-toggle:hover{background:#d5e8fb;}
.btn-del    {background:#fde8e8;color:#b91c1c;}
.btn-del:hover{background:#b91c1c;color:#fff;}
.btn-unlock {background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;}
.btn-unlock:hover{background:#c2410c;color:#fff;border-color:#c2410c;}
.btn-approve{background:#dcfce7;color:#166534;}
.btn-approve:hover{background:#16a34a;color:#fff;}
.btn-reject {background:#fee2e2;color:#991b1b;}
.btn-reject:hover{background:#dc2626;color:#fff;}

/* ── PAGINATION (from meal_compliance.php) ── */
.pagination{display:flex;gap:.4rem;margin-top:1.5rem;justify-content:flex-end;}
.page-btn{min-width:36px;height:36px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:700;text-decoration:none;border:1.5px solid rgba(216,226,234,.85);color:var(--muted);transition:all .2s;}
.page-btn:hover{background:rgba(31,79,125,.08);color:var(--slate);}
.page-btn.active{background:var(--accent);color:#fff;border-color:var(--accent);}

/* ── EMPTY STATE ── */
.empty-state{text-align:center;padding:3rem;color:var(--muted);}
.empty-state i{font-size:2.5rem;margin-bottom:.75rem;display:block;opacity:.3;}
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
    <a class="sb-item" href="users.php"><div class="sb-link active"><i class="bi bi-people"></i> Users</div></a>
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
      <h2>User Management</h2>
      <p style="font-size:.75rem;color:var(--muted);margin-top:.1rem;">
        <?= number_format($total_count) ?> total users
        <?php if ($pending_count): ?>&nbsp;·&nbsp;<span style="color:#d97706;font-weight:700;"><i class="bi bi-clock-fill"></i> <?= $pending_count ?> pending approval</span><?php endif; ?>
        <?php if ($locked_count): ?>&nbsp;·&nbsp;<span style="color:#c2410c;font-weight:700;"><i class="bi bi-lock-fill"></i> <?= $locked_count ?> locked</span><?php endif; ?>
      </p>
    </div>
  </div>

  <div class="content">

    <?php if ($msg): ?>
    <?php $icon_map=['ok'=>'check-circle-fill','warn'=>'unlock-fill','danger'=>'exclamation-triangle-fill','info'=>'info-circle-fill']; ?>
    <div class="alert alert-<?= $msg_type ?>"><i class="bi bi-<?= $icon_map[$msg_type] ?? 'check-circle-fill' ?>"></i><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="card">
      <!-- FILTERS -->
      <div class="filters">
        <form method="GET" style="display:contents;">
          <div class="search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" name="q" placeholder="Search name or email..." value="<?= htmlspecialchars($search) ?>">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
          </div>
          <button type="submit" style="padding:.55rem 1.1rem;border-radius:9px;background:var(--accent);color:#fff;border:none;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:.82rem;cursor:pointer;">Search</button>
        </form>
        <div class="filter-tabs">
          <?php $qs = $search ? "&q=".urlencode($search) : ''; ?>
          <a href="?filter=all<?= $qs ?>" class="ftab <?= $filter==='all'?'active':'' ?>">All</a>
          <a href="?filter=pending<?= $qs ?>" class="ftab pending-tab <?= $filter==='pending'?'active':'' ?>">
            <i class="bi bi-clock-fill"></i> Pending
            <?php if ($pending_count): ?><span class="tab-badge tab-badge-pending"><?= $pending_count ?></span><?php endif; ?>
          </a>
          <a href="?filter=active<?= $qs ?>"   class="ftab <?= $filter==='active'?'active':'' ?>">Active</a>
          <a href="?filter=inactive<?= $qs ?>" class="ftab <?= $filter==='inactive'?'active':'' ?>">Inactive</a>
          <a href="?filter=rejected<?= $qs ?>" class="ftab rejected-tab <?= $filter==='rejected'?'active':'' ?>"><i class="bi bi-x-circle-fill"></i> Rejected</a>
          <a href="?filter=locked<?= $qs ?>"   class="ftab locked-tab <?= $filter==='locked'?'active':'' ?>">
            <i class="bi bi-lock-fill"></i> Locked
            <?php if ($locked_count): ?><span class="tab-badge tab-badge-lock"><?= $locked_count ?></span><?php endif; ?>
          </a>
        </div>
      </div>

      <!-- TABLE -->
      <table>
        <thead>
          <tr><th>User</th><th>Gender</th><th>Goal</th><th>Workouts</th><th>Meal Logs</th><th>Joined</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u):
          $is_locked = !empty($u['locked_until']) && new DateTime() < new DateTime($u['locked_until']);
          $secs_left = 0;
          if ($is_locked) {
            $now = new DateTime(); $unlock = new DateTime($u['locked_until']); $diff = $now->diff($unlock);
            $secs_left = $diff->s + ($diff->i * 60);
          }
          $status = $u['status'] ?? 'active';
        ?>
        <tr>
          <td>
            <div class="u-cell">
              <div class="mini-av <?= strtolower($u['gender'] ?? '')=='female'?'female':'' ?>"><?= strtoupper(substr($u['name'],0,1)) ?></div>
              <div>
                <div class="u-name"><?= htmlspecialchars($u['name']) ?></div>
                <div class="u-email"><?= htmlspecialchars($u['email']) ?></div>
                <?php if ($is_locked): ?><div class="lock-indicator"><i class="bi bi-lock-fill"></i> Locked · <?= $secs_left ?>s remaining</div><?php endif; ?>
              </div>
            </div>
          </td>
          <td><?= ucfirst($u['gender'] ?: '—') ?></td>
          <td><?php $g=$u['goal_type']??''; ?><span class="badge badge-<?= $g?:'none' ?>"><?= $g ? ucfirst($g) : 'Not set' ?></span></td>
          <td><?= number_format($u['workouts']) ?></td>
          <td><?= number_format($u['meal_logs']) ?></td>
          <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
          <td>
            <?php if ($status==='pending'): ?><span class="badge badge-pending"><i class="bi bi-clock-fill"></i> Pending</span>
            <?php elseif ($status==='rejected'): ?><span class="badge badge-rejected"><i class="bi bi-x-circle-fill"></i> Rejected</span>
            <?php elseif ($is_locked): ?><span class="badge badge-locked"><i class="bi bi-lock-fill"></i> Locked</span>
            <?php else: ?><span class="badge badge-<?= $u['is_active']?'active':'inactive' ?>"><?= $u['is_active']?'Active':'Inactive' ?></span>
            <?php endif; ?>
          </td>
          <td>
            <div class="actions-cell">
              <?php if ($status==='pending'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="approve"><input type="hidden" name="uid" value="<?= $u['id'] ?>"><input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                  <?php if($search) echo '<input type="hidden" name="q" value="'.htmlspecialchars($search).'">'; ?>
                  <button type="submit" class="btn-sm btn-approve"><i class="bi bi-check-lg"></i> Approve</button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Reject this account?')">
                  <input type="hidden" name="action" value="reject"><input type="hidden" name="uid" value="<?= $u['id'] ?>"><input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                  <?php if($search) echo '<input type="hidden" name="q" value="'.htmlspecialchars($search).'">'; ?>
                  <button type="submit" class="btn-sm btn-reject"><i class="bi bi-x-lg"></i> Reject</button>
                </form>
              <?php elseif ($status==='rejected'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="approve"><input type="hidden" name="uid" value="<?= $u['id'] ?>"><input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                  <button type="submit" class="btn-sm btn-approve"><i class="bi bi-arrow-counterclockwise"></i> Re-approve</button>
                </form>
              <?php else: ?>
                <?php if ($is_locked): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Unlock this account?')">
                  <input type="hidden" name="action" value="unlock"><input type="hidden" name="uid" value="<?= $u['id'] ?>"><input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                  <button type="submit" class="btn-sm btn-unlock"><i class="bi bi-unlock-fill"></i> Unlock</button>
                </form>
                <?php endif; ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="toggle"><input type="hidden" name="uid" value="<?= $u['id'] ?>"><input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                  <?php if($search) echo '<input type="hidden" name="q" value="'.htmlspecialchars($search).'">'; ?>
                  <button type="submit" class="btn-sm btn-toggle"><?= $u['is_active']?'Deactivate':'Activate' ?></button>
                </form>
              <?php endif; ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this user permanently?')">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="uid" value="<?= $u['id'] ?>">
                <button type="submit" class="btn-sm btn-del">Delete</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
        <tr><td colspan="8">
          <div class="empty-state">
            <i class="bi bi-<?= $filter==='pending'?'clock':'people' ?>"></i>
            <?php if($filter==='pending'): ?>No pending registrations. All clear!
            <?php elseif($filter==='rejected'): ?>No rejected accounts found.
            <?php else: ?>No users found.<?php endif; ?>
          </div>
        </td></tr>
        <?php endif; ?>
        </tbody>
      </table>

      <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php for($p=1;$p<=$total_pages;$p++): ?>
        <a href="?page=<?= $p ?>&filter=<?= $filter ?><?= $search?"&q=".urlencode($search):'' ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>
</body>
</html>