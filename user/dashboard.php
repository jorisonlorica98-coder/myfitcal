<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../config/exercises.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$db = getDB();

// Redirect if setup not done
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

$cq = $db->prepare("SELECT DISTINCT day_number FROM user_workout_progress WHERE user_id=? AND completed=1");
$cq->execute([$user_id]);
$completed_days = $cq->fetchAll(PDO::FETCH_COLUMN);

$level     = $fitness['fitness_level'] ?? 'beginner';
$activity  = $fitness['activity_level'] ?? 'sedentary';
$goal_type = $goals['goal_type'] ?? 'maintain';
$days_pw   = $fitness['days_per_week'] ?? 3;

$gender    = strtolower($user['gender'] ?? 'male');
$is_female = $gender === 'female';

if ($is_female) { header('Location: /myfitcal_system/user/dashboard_female.php'); exit; }

$schedule    = getExercisePlan($level, $activity, $goal_type, $days_pw, $gender);
$workout_url = '/myfitcal_system/user/workout.php';

$current_day = 1;
for ($d = 1; $d <= 30; $d++) {
    if (!in_array($d, $completed_days) && !$schedule[$d]['is_rest']) { $current_day = $d; break; }
}

$today_plan = $schedule[$current_day];
$total_done = count($completed_days);
$streak     = 0;
for ($d = $current_day - 1; $d >= 1; $d--) {
    if (in_array($d, $completed_days)) $streak++; else break;
}

$reminder_time = ''; $reminder_day = 0;
try {
    $rq = $db->prepare("SELECT reminder_time, next_workout_day FROM workout_reminders WHERE user_id=? AND is_active=1");
    $rq->execute([$user_id]);
    $rem = $rq->fetch();
    if ($rem) { $reminder_time = $rem['reminder_time']; $reminder_day = (int)$rem['next_workout_day']; }
} catch(Exception $e) {}

$goal_labels = ['lose'=>'Weight Loss','maintain'=>'Maintenance','gain'=>'Weight Gain','muscle'=>'Muscle Gain'];
$hour        = (int)date('H');
$greeting    = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$first_name  = htmlspecialchars(explode(' ', $user['name'])[0]);

$dash_imgs = [
    'Jumping Jacks'=>'jumpinjack.png','Wall Push-Up'=>'wallpushup.png',
    'Chair Squat'=>'chair squat.png','Standing March'=>'standingmarch.png',
    'Glute Bridge'=>'glute bridge.png','Seated Leg Raise'=>'seated leg.png',
    'Knee Push-Up'=>'knee.png','Standing Side Bend'=>'standing side bends.png',
    'Push-Up'=>'pushup.png','Bodyweight Squat'=>'bodyweight.png',
    'Plank'=>'planks.png','Reverse Lunge'=>'reverse lunge.png',
    'Tricep Dip'=>'tricep.png','Superman'=>'superman.png',
    'High Knees'=>'high knees.png','Mountain Climber'=>'mountain climbers.png',
    'Diamond Push-Up'=>'diamondpushup.png','Jump Squat'=>'jump squat.png',
    'Burpee'=>'burpee.png','Pike Push-Up'=>'pike pushup.png',
    'Bulgarian Split Squat'=>'Bulgarian.png','Plank to Push-Up'=>'planks(1).png',
    'Speed Skater'=>'speed skater.png','V-Up'=>'v up.png',
    'Clap Push-Up'=>'clap.png','Pistol Squat'=>'pistol squats.png',
    'Burpee Tuck Jump'=>'burpee tuck jump.png','Archer Push-Up'=>'archer pushup.png',
    'Single Leg Deadlift'=>'single leg.png','Dragon Flag'=>'dragon flag.png',
    'Lateral Bound'=>'lateral bound.png','L-Sit Hold'=>'L sit.png',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — MyFitCal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:#f5f5f4;color:#1c1917;min-height:100vh;}

/* SIDEBAR */
.sidebar{
  position:fixed;left:0;top:0;bottom:0;width:220px;
  background:#1c1917;display:flex;flex-direction:column;
}
.sb-top{padding:18px 14px 14px;border-bottom:1px solid rgba(255,255,255,.06);}
.sb-brand{display:flex;align-items:center;gap:9px;}
.sb-logo{width:30px;height:30px;border-radius:6px;overflow:hidden;flex-shrink:0;}
.sb-logo img{width:100%;height:100%;object-fit:contain;}
.sb-name{font-size:14px;font-weight:600;color:#fafaf9;}
.sb-plan{font-size:10px;color:#78716c;margin-top:1px;}

.sb-nav{flex:1;padding:10px 8px;overflow-y:auto;}
.sb-lbl{font-size:10px;font-weight:600;color:#57534e;text-transform:uppercase;letter-spacing:.6px;padding:10px 6px 4px;display:block;}
.sb-link{
  display:flex;align-items:center;gap:9px;
  padding:7px 8px;border-radius:6px;
  font-size:13px;font-weight:500;
  color:#a8a29e;text-decoration:none;
  margin-bottom:1px;transition:all .12s;
}
.sb-link:hover{background:rgba(255,255,255,.05);color:#e7e5e4;}
.sb-link.active{background:rgba(255,255,255,.08);color:#fafaf9;}
.sb-link i{font-size:14px;width:16px;text-align:center;}

.sb-foot{padding:10px 14px;border-top:1px solid rgba(255,255,255,.06);display:flex;align-items:center;gap:9px;}
.sb-av{width:28px;height:28px;border-radius:50%;background:#292524;color:#e7e5e4;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;flex-shrink:0;}
.sb-uname{font-size:12px;font-weight:500;color:#e7e5e4;}
.sb-role{font-size:10px;color:#78716c;}
.sb-out{margin-left:auto;color:#57534e;text-decoration:none;font-size:15px;transition:color .12s;}
.sb-out:hover{color:#f87171;}

/* MAIN */
.main{margin-left:220px;min-height:100vh;display:flex;flex-direction:column;}

/* TOPBAR */
.topbar{
  background:#fff;
  border-bottom:1px solid #e7e5e4;
  padding:12px 24px;
  display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:50;
}
.topbar-l h2{font-size:14px;font-weight:600;color:#1c1917;}
.topbar-l p{font-size:12px;color:#78716c;margin-top:1px;}
.topbar-r{display:flex;align-items:center;gap:8px;}
.tb-btn{
  display:inline-flex;align-items:center;gap:5px;
  padding:6px 12px;border-radius:6px;
  border:1px solid #e7e5e4;background:#fff;
  font-family:'DM Sans',sans-serif;
  font-size:12px;font-weight:500;color:#78716c;
  text-decoration:none;transition:all .12s;cursor:pointer;
}
.tb-btn:hover{border-color:#1c1917;color:#1c1917;}

/* CONTENT */
.content{padding:24px;flex:1;}

/* GREETING */
.greeting{margin-bottom:20px;}
.greeting h1{font-size:22px;font-weight:600;color:#1c1917;margin-bottom:4px;}
.greeting h1 span{color:#44403c;}
.greeting p{font-size:13px;color:#78716c;}

/* REMINDER */
.reminder-bar{
  display:flex;align-items:center;justify-content:space-between;gap:12px;
  background:#fff;border:1px solid #e7e5e4;
  border-left:3px solid #16a34a;
  border-radius:8px;padding:11px 14px;
  margin-bottom:20px;
}
.reminder-bar-l{display:flex;align-items:center;gap:10px;}
.reminder-bar-l i{font-size:15px;color:#16a34a;}
.reminder-bar-text{font-size:13px;font-weight:500;color:#1c1917;}
.reminder-bar-sub{font-size:11px;color:#78716c;margin-top:1px;}
.reminder-actions{display:flex;align-items:center;gap:6px;}
.reminder-go{
  font-size:12px;font-weight:500;color:#16a34a;
  text-decoration:none;padding:5px 12px;
  border:1px solid #bbf7d0;border-radius:6px;
  background:#f0fdf4;transition:all .12s;
}
.reminder-go:hover{background:#dcfce7;}
.reminder-dismiss{background:none;border:none;color:#a8a29e;font-size:15px;cursor:pointer;}

/* STATS */
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px;}
.scard{background:#fff;border:1px solid #e7e5e4;border-radius:8px;padding:16px;}
.scard-lbl{font-size:11px;font-weight:500;color:#78716c;margin-bottom:8px;}
.scard-val{font-size:24px;font-weight:700;color:#1c1917;line-height:1;}
.scard-sub{font-size:11px;color:#a8a29e;margin-top:3px;}

/* TODAY */
.today-card{background:#fff;border:1px solid #e7e5e4;border-radius:8px;overflow:hidden;margin-bottom:20px;}
.today-head{
  padding:14px 18px;border-bottom:1px solid #f5f5f4;
  display:flex;align-items:center;justify-content:space-between;
}
.today-head h3{font-size:14px;font-weight:600;color:#1c1917;}
.today-head p{font-size:12px;color:#78716c;margin-top:2px;}
.today-badge{font-size:11px;font-weight:500;color:#78716c;background:#f5f5f4;border:1px solid #e7e5e4;padding:4px 10px;border-radius:5px;}

/* EXERCISE ROW */
.ex-row{
  display:flex;align-items:center;gap:12px;
  padding:12px 18px;border-bottom:1px solid #f5f5f4;
  transition:background .1s;
}
.ex-row:last-child{border-bottom:none;}
.ex-row:hover{background:#fafaf9;}
.ex-n{width:22px;height:22px;border-radius:5px;background:#f5f5f4;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:600;color:#78716c;flex-shrink:0;}
.ex-img{width:72px;height:72px;border-radius:8px;object-fit:cover;background:#f5f5f4;border:1px solid #e7e5e4;flex-shrink:0;}
.ex-info{flex:1;min-width:0;}
.ex-name{font-size:13px;font-weight:600;color:#1c1917;margin-bottom:5px;}
.ex-tags{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:4px;}
.ex-tag{font-size:11px;font-weight:500;padding:2px 7px;border-radius:4px;background:#f5f5f4;color:#57534e;}
.ex-muscle{font-size:11px;color:#a8a29e;}

.today-foot{padding:12px 18px;border-top:1px solid #f5f5f4;}
.btn-start{
  display:flex;align-items:center;justify-content:center;gap:7px;
  width:100%;padding:11px;border-radius:7px;
  background:#1c1917;color:#fafaf9;
  font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;
  text-decoration:none;border:none;cursor:pointer;
  transition:background .12s;
}
.btn-start:hover{background:#292524;color:#fafaf9;}

/* REST DAY */
.rest-card{background:#fff;border:1px solid #e7e5e4;border-radius:8px;padding:40px 20px;text-align:center;margin-bottom:20px;}
.rest-icon{width:48px;height:48px;border-radius:10px;background:#f5f5f4;display:flex;align-items:center;justify-content:center;font-size:22px;color:#78716c;margin:0 auto 12px;}
.rest-card h3{font-size:16px;font-weight:600;color:#1c1917;margin-bottom:6px;}
.rest-card p{font-size:13px;color:#78716c;max-width:300px;margin:0 auto;line-height:1.6;}

/* PROGRESS */
.prog-card{background:#fff;border:1px solid #e7e5e4;border-radius:8px;padding:18px;margin-bottom:20px;}
.prog-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;}
.prog-head span{font-size:13px;font-weight:500;color:#1c1917;}
.prog-head strong{font-size:13px;font-weight:600;color:#16a34a;}
.prog-track{height:6px;background:#f5f5f4;border-radius:999px;overflow:hidden;margin-bottom:16px;}
.prog-fill{height:100%;background:#1c1917;border-radius:999px;transition:width .5s;}
.days-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:5px;}
.day-cell{
  aspect-ratio:1;border-radius:6px;
  display:flex;align-items:center;justify-content:center;
  font-size:10px;font-weight:600;
}
.day-cell.done{background:#1c1917;color:#fafaf9;}
.day-cell.today{background:#16a34a;color:#fff;}
.day-cell.rest{background:#f5f5f4;color:#d6d3d1;}
.day-cell.upcoming{background:#f5f5f4;color:#a8a29e;}
.prog-legend{display:flex;gap:14px;margin-top:12px;flex-wrap:wrap;}
.pl-item{display:flex;align-items:center;gap:5px;font-size:11px;color:#78716c;}
.pl-dot{width:9px;height:9px;border-radius:2px;}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sb-top">
    <div class="sb-brand">
      <div class="sb-logo"><img src="/myfitcal_system/assets/image/logo.png" alt=""></div>
      <div>
        <div class="sb-name">MyFitCal</div>
        <div class="sb-plan">Male Plan</div>
      </div>
    </div>
  </div>
  <nav class="sb-nav">
    <span class="sb-lbl">Main</span>
    <a href="/myfitcal_system/user/dashboard.php" class="sb-link active"><i class="bi bi-grid-1x2"></i> Dashboard</a>
    <a href="/myfitcal_system/user/workout.php?day=<?= $current_day ?>" class="sb-link"><i class="bi bi-lightning-charge"></i> Workout</a>
    <a href="/myfitcal_system/user/meals.php" class="sb-link"><i class="bi bi-egg-fried"></i> Meals</a>
    <span class="sb-lbl">Track</span>
    <a href="/myfitcal_system/user/calendar.php" class="sb-link"><i class="bi bi-calendar3"></i> Calendar</a>
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
      <h2>Dashboard</h2>
      <p><?= date('l, F j, Y') ?></p>
    </div>

  </div>

  <div class="content">

    <div class="greeting">
      <h1><?= $greeting ?>, <span><?= $first_name ?>.</span></h1>
      <p><?= $total_done ?> of 30 workouts done &middot; <?= ucfirst($level) ?> level &middot; <?= $goal_labels[$goal_type] ?? 'Fitness' ?></p>
    </div>

    <?php if ($reminder_time && $reminder_day > 0): ?>
    <div class="reminder-bar" id="rb">
      <div class="reminder-bar-l">
        <i class="bi bi-alarm-fill"></i>
        <div>
          <div class="reminder-bar-text">Workout reminder set for <?= htmlspecialchars($reminder_time) ?></div>
          <div class="reminder-bar-sub">Day <?= $reminder_day ?> — <?= htmlspecialchars($schedule[$reminder_day]['focus'] ?? '') ?></div>
        </div>
      </div>
      <div class="reminder-actions">
        <a href="<?= $workout_url ?>?day=<?= $current_day ?>" class="reminder-go">Start now</a>
        <button class="reminder-dismiss" onclick="document.getElementById('rb').style.display='none'"><i class="bi bi-x"></i></button>
      </div>
    </div>
    <?php endif; ?>

    <div class="stats">
      <div class="scard">
        <div class="scard-lbl">Workouts done</div>
        <div class="scard-val"><?= $total_done ?></div>
        <div class="scard-sub">out of 30 days</div>
      </div>
      <div class="scard">
        <div class="scard-lbl">Current streak</div>
        <div class="scard-val"><?= $streak ?></div>
        <div class="scard-sub">consecutive days</div>
      </div>
      <div class="scard">
        <div class="scard-lbl">Days remaining</div>
        <div class="scard-val"><?= 30 - $total_done ?></div>
        <div class="scard-sub">to finish program</div>
      </div>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
      <p style="font-size:13px;font-weight:600;color:#1c1917;">Today — Day <?= $current_day ?></p>
      <a href="<?= $workout_url ?>?day=<?= $current_day ?>" style="font-size:12px;color:#78716c;text-decoration:none;">View all →</a>
    </div>

    <?php if ($today_plan['is_rest']): ?>
    <div class="rest-card">
      <div class="rest-icon"><i class="bi bi-moon-stars-fill"></i></div>
      <h3>Rest Day</h3>
      <p>Your body grows during rest. Stretch, hydrate, and sleep well today.</p>
    </div>
    <?php else: ?>
    <div class="today-card">
      <div class="today-head">
        <div>
          <h3><?= htmlspecialchars($today_plan['focus']) ?></h3>
          <p><?= count($today_plan['exercises']) ?> exercises &middot; est. <?= array_sum(array_column($today_plan['exercises'],'calories')) * 3 ?> kcal</p>
        </div>
        <span class="today-badge"><?= ucfirst($level) ?></span>
      </div>
      <?php foreach ($today_plan['exercises'] as $i => $ex):
        $img = '/myfitcal_system/workout.png/' . ($dash_imgs[$ex['name']] ?? 'pushup.png');
      ?>
      <div class="ex-row">
        <div class="ex-n"><?= $i+1 ?></div>
        <img src="<?= $img ?>" alt="<?= htmlspecialchars($ex['name']) ?>" class="ex-img">
        <div class="ex-info">
          <div class="ex-name"><?= htmlspecialchars($ex['name']) ?></div>
          <div class="ex-tags">
            <span class="ex-tag"><?= $ex['sets'] ?> sets &times; <?= $ex['reps'] ?></span>
            <span class="ex-tag"><?= $ex['rest'] ?>s rest</span>
            <span class="ex-tag">~<?= $ex['calories'] ?> kcal</span>
          </div>
          <div class="ex-muscle"><?= htmlspecialchars($ex['muscle']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <div class="today-foot">
        <a href="<?= $workout_url ?>?day=<?= $current_day ?>" class="btn-start">
          <i class="bi bi-play-fill"></i> Start Day <?= $current_day ?> Workout
        </a>
      </div>
    </div>
    <?php endif; ?>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
      <p style="font-size:13px;font-weight:600;color:#1c1917;">30-Day Progress</p>
      <span style="font-size:12px;color:#16a34a;font-weight:600;"><?= $total_done ?> / 30</span>
    </div>

    <div class="prog-card">
      <div class="prog-head">
        <span>Overall</span>
        <strong><?= round($total_done/30*100) ?>%</strong>
      </div>
      <div class="prog-track">
        <div class="prog-fill" style="width:<?= round($total_done/30*100) ?>%"></div>
      </div>
      <div class="days-grid">
        <?php for ($d=1;$d<=30;$d++):
          $cls = in_array($d,$completed_days) ? 'done' : ($d===$current_day ? 'today' : ($schedule[$d]['is_rest'] ? 'rest' : 'upcoming'));
        ?>
        <div class="day-cell <?= $cls ?>" title="Day <?= $d ?>"><?= $cls==='done' ? '✓' : $d ?></div>
        <?php endfor; ?>
      </div>
      <div class="prog-legend">
        <div class="pl-item"><div class="pl-dot" style="background:#1c1917;"></div> Done</div>
        <div class="pl-item"><div class="pl-dot" style="background:#16a34a;"></div> Today</div>
        <div class="pl-item"><div class="pl-dot" style="background:#f5f5f4;border:1px solid #e7e5e4;"></div> Rest / Upcoming</div>
      </div>
    </div>

  </div>
</div>
</body>
</html>