<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireAdmin();
$db = getDB();

$search = trim($_GET['q'] ?? '');
$mtype  = $_GET['meal'] ?? 'all';
$page   = max(1,(int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page-1)*$limit;

$where  = "WHERE 1=1";
$params = [];
if ($search) {
    $where .= " AND (u.name LIKE ? OR c.food_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($mtype !== 'all') {
    $where .= " AND c.meal_type=?";
    $params[] = $mtype;
}

$has_table   = (bool)$db->query("SHOW TABLES LIKE 'calorie_logs'")->fetch();
$total_count = 0;
$total_pages = 1;
$logs        = [];
$csum        = ['total'=>0,'calories'=>0];
$top_food    = null;
$top_meal    = null;

if ($has_table) {
    $t = $db->prepare("SELECT COUNT(*) FROM calorie_logs c JOIN users u ON u.id=c.user_id $where");
    $t->execute($params);
    $total_count = (int)$t->fetchColumn();
    $total_pages = max(1, (int)ceil($total_count / $limit));

    $s = $db->prepare("SELECT c.*, u.name, u.email FROM calorie_logs c JOIN users u ON u.id=c.user_id $where ORDER BY c.log_date DESC, c.id DESC LIMIT $limit OFFSET $offset");
    $s->execute($params);
    $logs = $s->fetchAll();

    $csum     = $db->query("SELECT COUNT(*) as total, COALESCE(SUM(calories),0) as calories FROM calorie_logs")->fetch();
    $top_food = $db->query("SELECT food_name, COUNT(*) as cnt FROM calorie_logs GROUP BY food_name ORDER BY cnt DESC LIMIT 1")->fetch();
    $top_meal = $db->query("SELECT meal_type, COUNT(*) as cnt FROM calorie_logs GROUP BY meal_type ORDER BY cnt DESC LIMIT 1")->fetch();
}

$meal_types = ['breakfast','lunch','dinner','snack'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Calories — MyFitCal Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--sb:#050505;--sb-w:248px;--blue:#1f4f7d;--blue-d:#174a7a;--slate:#111827;--muted:#52606d;--border:rgba(216,226,234,.9);--bg:#ebeff3;--card:#fff;--accent:#1f4f7d;}
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
.main{margin-left:var(--sb-w);min-height:100vh;display:flex;flex-direction:column;}
.topbar{background:#fff;border-bottom:1px solid rgba(216,226,234,.7);padding:1rem 2rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 10px 30px rgba(15,23,42,.04);}
.topbar h2{font-size:1.15rem;font-weight:900;color:var(--slate);letter-spacing:-.02em;}
.topbar p{font-size:.78rem;color:var(--muted);margin-top:.15rem;}
.content{padding:1.75rem 2rem;}
.stats-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;margin-bottom:1.5rem;}
.scard{background:#fff;border-radius:20px;border:1px solid rgba(216,226,234,.8);padding:1.25rem;box-shadow:0 16px 30px rgba(15,23,42,.05);}
.scard-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1rem;margin-bottom:.7rem;}
.scard-val{font-size:1.5rem;font-weight:800;color:var(--slate);line-height:1;margin-bottom:.2rem;}
.scard-label{font-size:.72rem;color:var(--muted);font-weight:600;}
.card{background:#fff;border-radius:22px;border:1px solid rgba(216,226,234,.8);padding:1.5rem;box-shadow:0 20px 45px rgba(15,23,42,.05);}
.filters{display:flex;align-items:center;gap:.6rem;margin-bottom:1.35rem;flex-wrap:wrap;}
.search-wrap{position:relative;flex:1;min-width:220px;max-width:340px;}
.search-wrap input{width:100%;padding:.6rem 1rem .6rem 2.3rem;border-radius:13px;border:1.5px solid rgba(216,226,234,.85);font-family:'Plus Jakarta Sans',sans-serif;font-size:.9rem;outline:none;background:#fff;color:var(--slate);}
.search-wrap input:focus{border-color:var(--accent);background:#fff;box-shadow:0 0 0 4px rgba(31,79,125,.08);}
.search-wrap i{position:absolute;left:.85rem;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.95rem;}
.filter-btn{padding:.55rem 1rem;border-radius:13px;border:1.5px solid rgba(216,226,234,.85);background:#fff;font-family:'Plus Jakarta Sans',sans-serif;font-size:.82rem;font-weight:700;color:var(--muted);cursor:pointer;text-decoration:none;transition:all .2s;}
.filter-btn:hover{background:rgba(31,79,125,.08);color:var(--slate);}
.filter-btn.active{background:var(--accent);color:#fff;border-color:var(--accent);}
table{width:100%;border-collapse:collapse;}
th{font-size:.68rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;padding:.6rem .85rem;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap;}
td{font-size:.8rem;color:var(--slate);padding:.65rem .85rem;border-bottom:1px solid #f8fafc;vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#fafbfc;}
.user-cell{display:flex;align-items:center;gap:.6rem;}
.mini-av{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#1e40af);display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;color:#fff;flex-shrink:0;}
.meal-badge{font-size:.68rem;font-weight:700;padding:.2rem .6rem;border-radius:6px;text-transform:capitalize;}
.meal-breakfast{background:#fef9c3;color:#854d0e;}
.meal-lunch{background:#dcfce7;color:#15803d;}
.meal-dinner{background:#eff6ff;color:#1d4ed8;}
.meal-snack{background:#fce7f3;color:#9d174d;}
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
    <a class="sb-item" href="calories.php"><div class="sb-link active"><i class="bi bi-fire"></i> Calories</div></a>
    <a class="sb-item" href="meal_compliance.php"><div class="sb-link"><i class="bi bi-egg-fried"></i> Meal Compliance</div></a>
  </nav>
  <div class="sb-footer">
    <div class="sb-user">
      <div class="sb-avatar"><?= strtoupper(substr($_SESSION['name'],0,1)) ?></div>
      <div>
        <div class="sb-uname"><?= htmlspecialchars($_SESSION['name']) ?></div>
        <div class="sb-urole">Administrator</div>
      </div>
      <a class="sb-logout" href="/myfitcal_system/logout.php"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div>
      <h2>Calorie Logs</h2>
      <p>All food entries logged by users</p>
    </div>
  </div>

  <div class="content">

    <!-- STATS -->
    <div class="stats-row">
      <div class="scard">
        <div class="scard-icon" style="background:#fff7ed;color:#f97316;"><i class="bi bi-journal-text"></i></div>
        <div class="scard-val"><?= number_format($csum['total']) ?></div>
        <div class="scard-label">Total Entries</div>
      </div>
      <div class="scard">
        <div class="scard-icon" style="background:#fef9c3;color:#ca8a04;"><i class="bi bi-fire"></i></div>
        <div class="scard-val"><?= number_format($csum['calories']) ?></div>
        <div class="scard-label">Total kcal Logged</div>
      </div>
      <div class="scard">
        <div class="scard-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-egg-fried"></i></div>
        <div class="scard-val"><?= $top_food ? htmlspecialchars($top_food['food_name']) : '—' ?></div>
        <div class="scard-label">Most Logged Food</div>
      </div>
      <div class="scard">
        <div class="scard-icon" style="background:#eff6ff;color:#2563eb;"><i class="bi bi-clock"></i></div>
        <div class="scard-val" style="text-transform:capitalize;"><?= $top_meal ? htmlspecialchars($top_meal['meal_type']) : '—' ?></div>
        <div class="scard-label">Top Meal Type</div>
      </div>
    </div>

    <!-- TABLE -->
    <div class="card">
      <form method="GET" class="filters">
        <div class="search-wrap">
          <i class="bi bi-search"></i>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search user or food...">
          <?php if ($mtype !== 'all'): ?>
          <input type="hidden" name="meal" value="<?= htmlspecialchars($mtype) ?>">
          <?php endif; ?>
        </div>
      </form>
      <div style="display:flex;gap:.5rem;margin-bottom:1.25rem;flex-wrap:wrap;">
        <?php
        $all_active = ($mtype === 'all') ? ' active' : '';
        $qs_base = $search ? '?q='.urlencode($search) : '?';
        ?>
        <a href="<?= $qs_base ?>&meal=all" class="filter-btn<?= $all_active ?>">All</a>
        <?php foreach ($meal_types as $mt): ?>
        <?php $mt_active = ($mtype === $mt) ? ' active' : ''; ?>
        <a href="<?= $qs_base ?>&meal=<?= $mt ?>" class="filter-btn<?= $mt_active ?>" style="text-transform:capitalize;"><?= $mt ?></a>
        <?php endforeach; ?>
      </div>

      <?php if (empty($logs)): ?>
      <div class="empty-state">
        <i class="bi bi-journal-x"></i>
        <?= $has_table ? 'No calorie logs found.' : 'No calorie data yet.' ?>
      </div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>User</th>
            <th>Food</th>
            <th>Meal</th>
            <th>Calories</th>
            <th>Protein</th>
            <th>Carbs</th>
            <th>Fat</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $r): ?>
          <tr>
            <td>
              <div class="user-cell">
                <div class="mini-av"><?= strtoupper(substr($r['name'],0,1)) ?></div>
                <div>
                  <div style="font-weight:700;"><?= htmlspecialchars($r['name']) ?></div>
                  <div style="font-size:.7rem;color:var(--muted);"><?= htmlspecialchars($r['email']) ?></div>
                </div>
              </div>
            </td>
            <td style="font-weight:600;"><?= htmlspecialchars($r['food_name'] ?? '—') ?></td>
            <td>
              <?php
              $mt = $r['meal_type'] ?? '';
              $mc = in_array($mt, $meal_types) ? 'meal-'.$mt : '';
              ?>
              <span class="meal-badge <?= $mc ?>"><?= ucfirst($mt ?: '—') ?></span>
            </td>
            <td><?= $r['calories'] ?? '—' ?> kcal</td>
            <td><?= $r['protein_g'] ?? '—' ?>g</td>
            <td><?= $r['carbs_g'] ?? '—' ?>g</td>
            <td><?= $r['fat_g'] ?? '—' ?>g</td>
            <td><?= $r['log_date'] ?? '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <?php
          $pg_qs = '?page='.$p;
          if ($search) $pg_qs .= '&q='.urlencode($search);
          if ($mtype !== 'all') $pg_qs .= '&meal='.urlencode($mtype);
          $pg_active = ($p === $page) ? ' active' : '';
        ?>
        <a href="<?= $pg_qs ?>" class="page-btn<?= $pg_active ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>

  </div>
</div>
</body>
</html>