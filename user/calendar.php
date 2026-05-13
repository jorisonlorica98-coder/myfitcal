<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../config/exercises.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$db = getDB();

$chk = $db->prepare("SELECT id FROM user_fitness WHERE user_id=?");
$chk->execute([$user_id]);
if (!$chk->fetch()) { header('Location: /myfitcal_system/setup/step1-profile.php'); exit; }

$uq = $db->prepare("SELECT * FROM users WHERE id=?");
$uq->execute([$user_id]);
$user = $uq->fetch();

$gq = $db->prepare("SELECT * FROM user_goals WHERE user_id=?");
$gq->execute([$user_id]);
$goals = $gq->fetch();

$fq = $db->prepare("SELECT * FROM user_fitness WHERE user_id=?");
$fq->execute([$user_id]);
$fitness = $fq->fetch();

$cq = $db->prepare("SELECT DISTINCT day_number FROM user_workout_progress WHERE user_id=? AND completed=1 ORDER BY day_number");
$cq->execute([$user_id]);
$completed_days = $cq->fetchAll(PDO::FETCH_COLUMN);

$level     = $fitness['fitness_level'] ?? 'beginner';
$activity  = $fitness['activity_level'] ?? 'sedentary';
$goal_type = $goals['goal_type'] ?? 'maintain';
$days_pw   = $fitness['days_per_week'] ?? 3;

$genderq = $db->prepare("SELECT gender FROM users WHERE id=? LIMIT 1");
$genderq->execute([$user_id]);
$gender = $genderq->fetchColumn() ?: 'male';
$is_female = strtolower($gender) === 'female';
$schedule = getExercisePlan($level, $activity, $goal_type, $days_pw, $gender);

$current_day = 1;
for ($d = 1; $d <= 30; $d++) {
    if (!in_array($d, $completed_days) && !$schedule[$d]['is_rest']) {
        $current_day = $d; break;
    }
}

$total_done = count($completed_days);
$total_rest = 0;
$total_work = 0;
for ($d = 1; $d <= 30; $d++) {
    if ($schedule[$d]['is_rest']) $total_rest++;
    else $total_work++;
}
$streak = 0;
for ($d = $current_day - 1; $d >= 1; $d--) {
    if (in_array($d, $completed_days)) $streak++;
    else break;
}
$pct = round($total_done / max(1, $total_work) * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Calendar — MyFitCal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:#f5f5f4;color:#1c1917;min-height:100vh;}

.sidebar{position:fixed;left:0;top:0;bottom:0;width:220px;background:#1c1917;display:flex;flex-direction:column;z-index:200;overflow:hidden;}
.sb-top{padding:18px 14px 14px;border-bottom:1px solid rgba(255,255,255,.06);flex-shrink:0;}
.sb-brand{display:flex;align-items:center;gap:9px;}
.sb-logo{width:30px;height:30px;border-radius:6px;overflow:hidden;flex-shrink:0;}
.sb-logo img{width:100%;height:100%;object-fit:contain;}
.sb-name{font-size:14px;font-weight:600;color:#fafaf9;}
.sb-plan{font-size:10px;color:#78716c;margin-top:1px;}
.sb-nav{flex:1;padding:10px 8px;overflow-y:auto;min-height:0;}
.sb-lbl{font-size:10px;font-weight:600;color:#57534e;text-transform:uppercase;letter-spacing:.6px;padding:10px 6px 4px;display:block;}
.sb-link{display:flex;align-items:center;gap:9px;padding:7px 8px;border-radius:6px;font-size:13px;font-weight:500;color:#a8a29e;text-decoration:none;margin-bottom:1px;transition:all .12s;}
.sb-link:hover{background:rgba(255,255,255,.05);color:#e7e5e4;}
.sb-link.active{background:rgba(255,255,255,.08);color:#fafaf9;}
.sb-link i{font-size:14px;width:16px;text-align:center;}
.sb-foot{padding:10px 14px;border-top:1px solid rgba(255,255,255,.06);display:flex;align-items:center;gap:9px;flex-shrink:0;}
.sb-av{width:28px;height:28px;border-radius:50%;background:#292524;color:#e7e5e4;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;flex-shrink:0;}
.sb-uname{font-size:12px;font-weight:500;color:#e7e5e4;}
.sb-role{font-size:10px;color:#78716c;}
.sb-out{margin-left:auto;color:#57534e;text-decoration:none;font-size:15px;transition:color .12s;}
.sb-out:hover{color:#f87171;}

.main{margin-left:220px;min-height:100vh;display:flex;flex-direction:column;}
.topbar{background:#fff;border-bottom:1px solid #e7e5e4;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;}
.topbar-l h2{font-size:14px;font-weight:600;color:#1c1917;}
.topbar-l p{font-size:12px;color:#78716c;margin-top:1px;}
.tb-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:6px;border:1px solid #e7e5e4;background:#fff;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:500;color:#78716c;text-decoration:none;transition:all .12s;}
.tb-btn:hover{border-color:#1c1917;color:#1c1917;}
.content{padding:24px;flex:1;}

.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px;}
.scard{background:#fff;border:1px solid #e7e5e4;border-radius:8px;padding:16px;}
.scard-lbl{font-size:11px;font-weight:500;color:#78716c;margin-bottom:8px;}
.scard-val{font-size:24px;font-weight:700;color:#1c1917;line-height:1;}
.scard-sub{font-size:11px;color:#a8a29e;margin-top:3px;}

.prog-card{background:#fff;border:1px solid #e7e5e4;border-radius:8px;padding:18px;margin-bottom:16px;}
.prog-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;font-size:13px;font-weight:500;color:#1c1917;}
.prog-track{height:6px;background:#f5f5f4;border-radius:999px;overflow:hidden;}
.prog-fill{height:100%;background:#1c1917;border-radius:999px;}

.cal-card{background:#fff;border:1px solid #e7e5e4;border-radius:8px;padding:18px;margin-bottom:16px;}
.cal-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
.cal-title{font-size:13px;font-weight:600;color:#1c1917;}
.cal-legend{display:flex;gap:12px;}
.pl-item{display:flex;align-items:center;gap:5px;font-size:11px;color:#78716c;}
.pl-dot{width:8px;height:8px;border-radius:50%;}

/* ── 5-column day grid ── */
.days-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:8px;}

/* ── DAY CARD ── */
.day-card{
  border-radius:8px;
  border:1px solid #e7e5e4;
  background:#fff;
  overflow:hidden;
  text-decoration:none;
  color:inherit;
  transition:box-shadow .15s, transform .15s;
  display:flex;
  flex-direction:column;
}
.day-card:hover{ transform:translateY(-2px); box-shadow:0 4px 14px rgba(0,0,0,.08); }

/* done state — dark */
.day-card.is-done{ background:#1c1917; border-color:#1c1917; }
/* today state — green border */
.day-card.is-today{ border-color:#16a34a; box-shadow:0 0 0 2px rgba(22,163,74,.15); }
/* rest state */
.day-card.is-rest{ background:#f5f5f4; border-color:#e7e5e4; opacity:.6; pointer-events:none; }
/* locked/upcoming */
.day-card.is-locked{ background:#fafaf9; }

/* ── CARD HEADER ── */
.dc-head{
  padding:8px 10px;
  display:flex;align-items:center;justify-content:space-between;
  border-bottom:1px solid #f0f0f0;
}
.day-card.is-done .dc-head{ border-bottom-color:rgba(255,255,255,.07); }
.day-card.is-today .dc-head{ border-bottom-color:#dcfce7; }
.day-card.is-rest .dc-head{ border-bottom:none; }

.dc-day-num{
  font-size:10px; font-weight:700; color:#78716c;
}
.day-card.is-done .dc-day-num{ color:#57534e; }
.day-card.is-today .dc-day-num{ color:#16a34a; }

.dc-badge{
  font-size:8px; font-weight:700;
  padding:2px 6px; border-radius:3px;
}
.badge-done{ background:rgba(34,197,94,.15); color:#16a34a; }
.day-card.is-done .badge-done{ background:rgba(255,255,255,.1); color:#a8a29e; }
.badge-today{ background:#16a34a; color:#fff; }
.badge-rest{ background:#e7e5e4; color:#a8a29e; }

/* ── FOCUS LABEL ── */
.dc-focus{
  padding:4px 10px 2px;
  font-size:9px; font-weight:600;
  color:#a8a29e; letter-spacing:.03em; text-transform:uppercase;
}
.day-card.is-done .dc-focus{ color:#57534e; }
.day-card.is-today .dc-focus{ color:#16a34a; }

/* ── EXERCISE LIST ── */
.dc-exlist{
  padding:4px 10px 10px;
  display:flex; flex-direction:column; gap:5px;
  flex:1;
}

.dc-ex{
  display:flex; align-items:center; gap:6px;
}

/* checkbox dot */
.dc-cb{
  width:13px; height:13px;
  border-radius:3px;
  border:1.5px solid #d6d3d1;
  background:#fff;
  display:flex; align-items:center; justify-content:center;
  flex-shrink:0;
  font-size:8px; color:#fff;
}
/* completed checkbox */
.dc-cb.checked{
  background:#22c55e;
  border-color:#22c55e;
}
.day-card.is-done .dc-cb{
  background:#22c55e;
  border-color:#22c55e;
}
/* pending on dark bg */
.day-card.is-done .dc-cb.unchecked{
  background:rgba(255,255,255,.08);
  border-color:rgba(255,255,255,.15);
}

.dc-ex-name{
  font-size:9px; font-weight:500;
  color:#78716c;
  overflow:hidden; white-space:nowrap; text-overflow:ellipsis;
  flex:1;
}
.day-card.is-done .dc-ex-name{
  color:#a8a29e;
  text-decoration:line-through;
  text-decoration-color:rgba(255,255,255,.2);
}
.day-card.is-today .dc-ex-name{ color:#57534e; }
.day-card.is-locked .dc-ex-name{ color:#c4c2c0; }

/* rest body */
.dc-rest-body{
  padding:12px 10px;
  text-align:center;
  color:#c4c2c0;
  font-size:10px;
  font-weight:500;
}
.dc-rest-body i{ font-size:16px; display:block; margin-bottom:4px; }

/* ── UPCOMING LIST ── */
.section-title{font-size:13px;font-weight:600;color:#1c1917;margin-bottom:10px;}
.upcoming-card{background:#fff;border:1px solid #e7e5e4;border-radius:8px;overflow:hidden;margin-bottom:16px;}
.upc-item{display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid #f5f5f4;text-decoration:none;color:inherit;transition:background .1s;}
.upc-item:last-child{border-bottom:none;}
.upc-item:hover{background:#fafaf9;}
.upc-item.is-today{background:#f0fdf4;border-left:3px solid #16a34a;}
.upc-num{width:28px;height:28px;border-radius:6px;background:#f5f5f4;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;color:#78716c;flex-shrink:0;}
.upc-num.today-num{background:#16a34a;color:#fff;}
.upc-info{flex:1;}
.upc-name{font-size:13px;font-weight:600;color:#1c1917;}
.upc-sub{font-size:11px;color:#78716c;margin-top:1px;}
.upc-tag{font-size:10px;font-weight:600;padding:2px 8px;border-radius:4px;margin-left:auto;flex-shrink:0;}
.upc-tag.today{background:#dcfce7;color:#16a34a;}
.upc-tag.rest{background:#f5f5f4;color:#78716c;}

@media(max-width:1100px){.days-grid{grid-template-columns:repeat(4,1fr);}}
@media(max-width:900px){.days-grid{grid-template-columns:repeat(3,1fr);}}
@media(max-width:768px){
  .sidebar{display:none;} .main{margin-left:0;}
  .stats{grid-template-columns:repeat(2,1fr);}
  .days-grid{grid-template-columns:repeat(2,1fr);}
}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sb-top">
    <div class="sb-brand">
      <div class="sb-logo"><img src="/myfitcal_system/assets/image/logo.png" alt="MyFitCal"></div>
      <div>
        <div class="sb-name">MyFitCal</div>
        <div class="sb-plan"><?= $is_female ? 'Female Plan' : 'Male Plan' ?></div>
      </div>
    </div>
  </div>
  <nav class="sb-nav">
    <span class="sb-lbl">Main</span>
    <a href="/myfitcal_system/user/<?= $is_female ? 'dashboard_female' : 'dashboard' ?>.php" class="sb-link"><i class="bi bi-grid-1x2"></i> Dashboard</a>
    <a href="/myfitcal_system/user/<?= $is_female ? 'workout_female' : 'workout' ?>.php?day=1" class="sb-link"><i class="bi bi-lightning-charge"></i> Workout</a>
    <a href="/myfitcal_system/user/meals.php" class="sb-link"><i class="bi bi-egg-fried"></i> Meals</a>
    <span class="sb-lbl">Track</span>
    <a href="/myfitcal_system/user/calendar.php" class="sb-link active"><i class="bi bi-calendar3"></i> Calendar</a>
    <a href="/myfitcal_system/user/chatbot.php" class="sb-link"><i class="bi bi-robot"></i> FitBot</a>
    <span class="sb-lbl">Account</span>
    <a href="/myfitcal_system/user/profile.php" class="sb-link"><i class="bi bi-person-circle"></i> My Profile</a>
  </nav>
  <div class="sb-foot">
    <div class="sb-av"><?= strtoupper(substr($user['name'],0,1)) ?></div>
    <div>
      <div class="sb-uname"><?= htmlspecialchars(explode(' ',$user['name'])[0]) ?></div>
      <div class="sb-role">Member</div>
    </div>
    <a href="/myfitcal_system/logout.php" class="sb-out"><i class="bi bi-box-arrow-right"></i></a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div class="topbar-l">
      <h2>30 Day Calendar</h2>
      <p><?= date('l, F j, Y') ?></p>
    </div>
    <a href="/myfitcal_system/user/<?= $is_female ? 'dashboard_female' : 'dashboard' ?>.php" class="tb-btn">
    </a>
  </div>

  <div class="content">

    <div class="stats">
      <div class="scard">
        <div class="scard-lbl">Workouts Done</div>
        <div class="scard-val"><?= $total_done ?></div>
        <div class="scard-sub">Completed</div>
      </div>
      <div class="scard">
        <div class="scard-lbl">Current Streak</div>
        <div class="scard-val"><?= $streak ?></div>
        <div class="scard-sub">Consecutive days</div>
      </div>
      <div class="scard">
        <div class="scard-lbl">Remaining</div>
        <div class="scard-val"><?= $total_work - $total_done ?></div>
        <div class="scard-sub">Left to finish</div>
      </div>
      <div class="scard">
        <div class="scard-lbl">Rest Days</div>
        <div class="scard-val"><?= $total_rest ?></div>
        <div class="scard-sub">Total in program</div>
      </div>
    </div>

    <div class="prog-card">
      <div class="prog-head">
        <span>Overall Progress</span>
        <strong style="color:#16a34a;"><?= $total_done ?> / <?= $total_work ?> — <?= $pct ?>%</strong>
      </div>
      <div class="prog-track">
        <div class="prog-fill" style="width:<?= $pct ?>%"></div>
      </div>
    </div>

    <!-- CALENDAR GRID -->
    <div class="cal-card">
      <div class="cal-top">
        <div class="cal-title">30-Day Overview</div>
        <div class="cal-legend">
          <div class="pl-item"><div class="pl-dot" style="background:#22c55e;"></div> Done</div>
          <div class="pl-item"><div class="pl-dot" style="background:#e7e5e4;border:1px solid #d6d3d1;"></div> Pending</div>
        </div>
      </div>

      <div class="days-grid">
        <?php for ($d = 1; $d <= 30; $d++):
          $is_done    = in_array($d, $completed_days);
          $is_today   = ($d === $current_day);
          $is_rest    = $schedule[$d]['is_rest'];
          $focus      = $schedule[$d]['focus'] ?? '';
          $exercises  = $schedule[$d]['exercises'] ?? [];
          $href       = "/myfitcal_system/user/" . ($is_female ? 'workout_female' : 'workout') . ".php?day=$d";

          if ($is_rest)       $cls = 'is-rest';
          elseif ($is_done)   $cls = 'is-done';
          elseif ($is_today)  $cls = 'is-today';
          else                $cls = 'is-locked';
        ?>
        <a href="<?= $is_rest ? '#' : $href ?>" class="day-card <?= $cls ?>">

          <!-- header -->
          <div class="dc-head">
            <div class="dc-day-num">Day <?= $d ?></div>
            <?php if ($is_done): ?>
              <span class="dc-badge badge-done">✓ Done</span>
            <?php elseif ($is_today): ?>
              <span class="dc-badge badge-today">Today</span>
            <?php elseif ($is_rest): ?>
              <span class="dc-badge badge-rest">Rest</span>
            <?php endif; ?>
          </div>

          <?php if ($is_rest): ?>
          <!-- rest body -->
          <div class="dc-rest-body">
            <i class="bi bi-moon-fill"></i>
            Rest Day
          </div>

          <?php else: ?>
          <!-- focus label -->
          <div class="dc-focus"><?= htmlspecialchars($focus) ?></div>

          <!-- exercise checklist -->
          <div class="dc-exlist">
            <?php foreach ($exercises as $ex): ?>
            <div class="dc-ex">
              <div class="dc-cb <?= $is_done ? 'checked' : 'unchecked' ?>">
                <?php if ($is_done): ?><i class="bi bi-check"></i><?php endif; ?>
              </div>
              <div class="dc-ex-name"><?= htmlspecialchars($ex['name']) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

        </a>
        <?php endfor; ?>
      </div>
    </div>

    <!-- UPCOMING WORKOUTS -->
    <div class="section-title">Upcoming Workouts</div>
    <div class="upcoming-card">
      <?php
      $shown = 0;
      for ($d = $current_day; $d <= 30 && $shown < 7; $d++):
        $is_rest  = $schedule[$d]['is_rest'];
        $is_today = ($d === $current_day);
        $focus    = $schedule[$d]['focus'];
        $ex_count = count($schedule[$d]['exercises']);
        $shown++;
      ?>
      <?php if ($is_rest): ?>
      <div class="upc-item" style="opacity:.6;">
        <div class="upc-num"><i class="bi bi-moon-fill"></i></div>
        <div class="upc-info">
          <div class="upc-name">Rest Day</div>
          <div class="upc-sub">Day <?= $d ?> — Recovery</div>
        </div>
        <span class="upc-tag rest">Rest</span>
      </div>
      <?php else: ?>
      <a href="/myfitcal_system/user/<?= $is_female ? 'workout_female' : 'workout' ?>.php?day=<?= $d ?>" class="upc-item <?= $is_today ? 'is-today' : '' ?>">
        <div class="upc-num <?= $is_today ? 'today-num' : '' ?>"><?= $d ?></div>
        <div class="upc-info">
          <div class="upc-name"><?= htmlspecialchars($focus) ?></div>
          <div class="upc-sub">Day <?= $d ?> — <?= $ex_count ?> exercises</div>
        </div>
        <?php if ($is_today): ?><span class="upc-tag today">Today</span><?php endif; ?>
      </a>
      <?php endif; ?>
      <?php endfor; ?>
    </div>

  </div>
</div>
</body>
</html>