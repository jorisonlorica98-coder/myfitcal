<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../config/exercises.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$db = getDB();
$day = (int)($_GET['day'] ?? 1);
if ($day < 1 || $day > 30) { header('Location: /myfitcal_system/user/dashboard_female.php'); exit; }

$genderq = $db->prepare("SELECT gender FROM users WHERE id=? LIMIT 1");
$genderq->execute([$user_id]);
$gender = $genderq->fetchColumn() ?: 'male';
$is_female = strtolower($gender) === 'female';
if (strtolower($gender) !== 'female') { header("Location: /myfitcal_system/user/workout.php?day=$day"); exit; }

$gq = $db->prepare("SELECT * FROM user_goals WHERE user_id=?");
$gq->execute([$user_id]);
$goals = $gq->fetch();

$fq = $db->prepare("SELECT * FROM user_fitness WHERE user_id=?");
$fq->execute([$user_id]);
$fitness = $fq->fetch();

$level    = $fitness['fitness_level'] ?? 'beginner';
$activity = $fitness['activity_level'] ?? 'sedentary';
$goal_type= $goals['goal_type'] ?? 'maintain';
$days_pw  = $fitness['days_per_week'] ?? 3;

$schedule = getExercisePlan($level, $activity, $goal_type, $days_pw, 'female');
$today    = $schedule[$day];

if ($today['is_rest']) { header('Location: /myfitcal_system/user/dashboard_female.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete') {
    $ins = $db->prepare("INSERT INTO user_workout_progress (user_id, day_number, exercise_id, completed, completed_at)
        VALUES (?, ?, 1, 1, NOW()) ON DUPLICATE KEY UPDATE completed=1, completed_at=NOW()");
    $ins->execute([$user_id, $day]);
    header('Location: /myfitcal_system/user/complete.php?day='.$day); exit;
}

$uq = $db->prepare("SELECT name FROM users WHERE id=?");
$uq->execute([$user_id]);
$user = $uq->fetch();
$first_name = htmlspecialchars(explode(' ', $user['name'])[0]);

$ex_images = [
    'Basic Glute Bridge'=>'/myfitcal_system/exercises/Basic Glute Bridge.png',
    'Donkey Kick'=>'/myfitcal_system/exercises/Donkey Kick.png',
    'Clamshell'=>'/myfitcal_system/exercises/Clamshell.png',
    'Wall Sit'=>'/myfitcal_system/exercises/Wall Sit.png',
    'Hip Circle'=>'/myfitcal_system/exercises/Hip Circle.png',
    'Bird Dog'=>'/myfitcal_system/exercises/Bird Dog.png',
    'Seated Hip Abduction'=>'/myfitcal_system/exercises/Seated Hip Abduction.png',
    'Lying Leg Raise'=>'/myfitcal_system/exercises/Lying Leg Raise.png',
    'Single Leg Glute Bridge'=>'/myfitcal_system/exercises/Single Leg Glute Bridge.png',
    'Sumo Squat'=>'/myfitcal_system/exercises/Sumo Squat.png',
    'Hip Thrust'=>'/myfitcal_system/exercises/Hip Thrust.png',
    'Fire Hydrant'=>'/myfitcal_system/exercises/Fire Hydrant.png',
    'Lateral Leg Raise'=>'/myfitcal_system/exercises/Lateral Leg Raise.png',
    'Plank Hip Dip'=>'/myfitcal_system/exercises/Plank Hip Dip.png',
    'Frog Pump'=>'/myfitcal_system/exercises/Frog Pump.png',
    'Curtsy Lunge'=>'/myfitcal_system/exercises/Curtsy Lunge.png',
    'Sumo Jump Squat'=>'/myfitcal_system/exercises/Sumo Jump Squat.png',
    'Hip Thrust Pulse'=>'/myfitcal_system/exercises/Hip Thrust Pulse.png',
    'Lateral Band Walk'=>'/myfitcal_system/exercises/Lateral Band Walk.png',
    'Reverse Hyper'=>'/myfitcal_system/exercises/Reverse Hyper.png',
    'Diagonal Lunge'=>'/myfitcal_system/exercises/Diagonal Lunge.png',
    'Side Lying Hip Raise'=>'/myfitcal_system/exercises/Side Lying Hip Raise.png',
    'Glute Kickback'=>'/myfitcal_system/exercises/Glute Kickback.png',
    'Side Lunge Touchdown'=>'/myfitcal_system/exercises/Side Lunge Touchdown.png',
    'Single Leg Squat'=>'/myfitcal_system/exercises/Single Leg Squat.png',
    'Nordic Hamstring Curl'=>'/myfitcal_system/exercises/Nordic Hamstring Curl.png',
    'Plyometric Curtsy Lunge'=>'/myfitcal_system/exercises/Plyometric Curtsy Lunge.png',
    'Single Leg Hip Thrust'=>'/myfitcal_system/exercises/Single Leg Hip Thrust.png',
    'Power Lateral Bound'=>'/myfitcal_system/exercises/Power Lateral Bound.png',
    'Ab Dragon Flag'=>'/myfitcal_system/exercises/Ab Dragon Flag.png',
    'Plyometric Hip Thrust'=>'/myfitcal_system/exercises/Plyometric Hip Thrust.png',
    'L-Sit Core Hold'=>'/myfitcal_system/exercises/L-Sit Core Hold.png',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Day <?= $day ?> Workout — MyFitCal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:#f5f5f4;color:#1c1917;min-height:100vh;}

/* ── SIDEBAR ── */
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

/* ── MAIN ── */
.main{margin-left:220px;min-height:100vh;display:flex;flex-direction:column;}
.topbar{background:#fff;border-bottom:1px solid #e7e5e4;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;flex-shrink:0;}
.topbar-l h2{font-size:14px;font-weight:600;color:#1c1917;}
.topbar-l p{font-size:12px;color:#78716c;margin-top:1px;}
.tb-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:6px;border:1px solid #e7e5e4;background:#fff;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:500;color:#78716c;text-decoration:none;transition:all .12s;}
.tb-btn:hover{border-color:#1c1917;color:#1c1917;}

.content{padding:24px;flex:1;}

/* ── WORKOUT HEADER ── */
.workout-header{background:#1c1917;border-radius:8px;padding:20px 22px;margin-bottom:16px;color:#fff;}
.wh-label{font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#78716c;margin-bottom:4px;}
.wh-title{font-size:20px;font-weight:700;margin-bottom:8px;color:#fafaf9;}
.wh-meta{display:flex;gap:14px;flex-wrap:wrap;}
.wh-tag{font-size:12px;font-weight:500;color:#a8a29e;display:flex;align-items:center;gap:5px;}
.wh-tag.female{color:#f9a8d4;}

/* ── PROGRESS ── */
.progress-bar-wrap{background:#fff;border-radius:8px;border:1px solid #e7e5e4;padding:14px 18px;margin-bottom:16px;}
.pb-label{display:flex;justify-content:space-between;font-size:12px;font-weight:500;color:#78716c;margin-bottom:8px;}
.pb-track{height:6px;background:#f5f5f4;border-radius:999px;overflow:hidden;}
.pb-fill{height:100%;background:#1c1917;border-radius:999px;transition:width .5s ease;}

/* ── EXERCISE CARDS ── */
.ex-card{background:#fff;border-radius:8px;border:1px solid #e7e5e4;margin-bottom:8px;overflow:hidden;transition:all .2s;}
.ex-card.active{border-color:#16a34a;box-shadow:0 2px 12px rgba(22,163,74,.1);}
.ex-card.done-card{opacity:.55;}
.ex-card-header{display:flex;align-items:center;gap:12px;padding:12px 16px;cursor:pointer;user-select:none;}
.ex-card-header:hover{background:#fafaf9;}
.ex-num-badge{width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;background:#f5f5f4;color:#78716c;flex-shrink:0;transition:all .2s;}
.ex-card.active .ex-num-badge{background:#1c1917;color:#fff;}
.ex-card.done-card .ex-num-badge{background:#f0fdf4;color:#16a34a;}
.ex-title-wrap{flex:1;}
.ex-card-name{font-size:13px;font-weight:600;color:#1c1917;}
.ex-card-meta{font-size:11px;color:#78716c;margin-top:2px;}
.ex-status{font-size:14px;}
.ex-body{display:none;border-top:1px solid #f5f5f4;}
.ex-card.active .ex-body{display:block;}

/* ── MEDIA ── */
.ex-media{position:relative;width:100%;height:260px;overflow:hidden;background:#f5f5f4;display:flex;align-items:center;justify-content:center;}
.ex-demo-img{width:100%;height:100%;object-fit:cover;display:block;}
.ex-placeholder{display:flex;align-items:center;justify-content:center;background:#1c1917;}
.ph-inner{display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:24px;}
.ph-icon{font-size:48px;color:rgba(255,255,255,.2);margin-bottom:10px;}
.ph-name{font-size:16px;font-weight:700;color:#fff;margin-bottom:4px;}
.ph-muscle{font-size:12px;color:rgba(255,255,255,.5);}
.muscle-badge{position:absolute;bottom:10px;left:10px;background:rgba(28,25,23,.7);color:#a8a29e;font-size:11px;font-weight:500;padding:4px 10px;border-radius:5px;backdrop-filter:blur(4px);z-index:3;}

/* ── DETAILS ── */
.ex-details{padding:14px 16px;}
.detail-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px;}
.dg-item{text-align:center;background:#f5f5f4;border-radius:6px;padding:10px 6px;}
.dg-val{font-size:14px;font-weight:700;color:#1c1917;}
.dg-label{font-size:10px;font-weight:600;color:#a8a29e;text-transform:uppercase;letter-spacing:.07em;margin-top:2px;}
.instructions-box{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:10px 12px;margin-bottom:12px;}
.ib-label{font-size:10px;font-weight:700;color:#15803d;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;}
.ib-text{font-size:12px;color:#166534;line-height:1.7;}
.btn-done{width:100%;background:#1c1917;color:#fafaf9;border:none;border-radius:6px;padding:11px;font-family:'DM Sans',sans-serif;font-weight:600;font-size:13px;cursor:pointer;transition:all .15s;display:flex;align-items:center;justify-content:center;gap:6px;}
.btn-done:hover{background:#292524;}
.btn-done.completed{background:#f0fdf4;color:#16a34a;cursor:default;}

/* ── FINISH ── */
.finish-section{background:#fff;border-radius:8px;border:1px solid #e7e5e4;padding:28px;text-align:center;margin-top:16px;display:none;}
.finish-section.show{display:block;}
.finish-title{font-size:16px;font-weight:700;color:#1c1917;margin-bottom:4px;}
.finish-sub{font-size:12px;color:#78716c;margin-bottom:20px;}
.btn-finish{display:block;background:#1c1917;color:#fafaf9;border:none;border-radius:6px;padding:12px;font-family:'DM Sans',sans-serif;font-weight:600;font-size:13px;cursor:pointer;width:100%;text-decoration:none;transition:background .15s;}
.btn-finish:hover{background:#292524;color:#fafaf9;}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sb-top">
    <div class="sb-brand">
      <div class="sb-logo"><img src="/myfitcal_system/assets/image/logo.png" alt="MyFitCal"></div>
      <div>
        <div class="sb-name">MyFitCal</div>
        <div class="sb-plan">Female Plan</div>
      </div>
    </div>
  </div>
  <nav class="sb-nav">
    <span class="sb-lbl">Main</span>
    <a href="/myfitcal_system/user/dashboard_female.php" class="sb-link"><i class="bi bi-grid-1x2"></i> Dashboard</a>
    <a href="/myfitcal_system/user/workout_female.php?day=1" class="sb-link active"><i class="bi bi-lightning-charge"></i> Workout</a>
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
      <h2>Workout</h2>
      <p>Day <?= $day ?> of 30 · Female Plan</p>
    </div>
    <a href="/myfitcal_system/user/dashboard_female.php" class="tb-btn"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
  </div>

  <div class="content">
    <div class="workout-header">
      <div class="wh-label">Day <?= $day ?> of 30</div>
      <div class="wh-title"><?= htmlspecialchars($today['focus']) ?></div>
      <div class="wh-meta">
        <span class="wh-tag"><i class="bi bi-list-check"></i> <?= count($today['exercises']) ?> exercises</span>
        <span class="wh-tag"><i class="bi bi-bar-chart"></i> <?= ucfirst($level) ?> level</span>
        <span class="wh-tag"><i class="bi bi-fire"></i> ~<?= array_sum(array_column($today['exercises'],'calories')) * 3 ?> kcal</span>
        <span class="wh-tag female"><i class="bi bi-gender-female"></i> Female Plan</span>
      </div>
    </div>

    <div class="progress-bar-wrap">
      <div class="pb-label"><span>Progress</span><span id="progressText">0 / <?= count($today['exercises']) ?> done</span></div>
      <div class="pb-track"><div class="pb-fill" id="progressFill" style="width:0%"></div></div>
    </div>

    <?php foreach ($today['exercises'] as $i => $ex):
      $has_img = isset($ex_images[$ex['name']]);
      $img_src = $ex_images[$ex['name']] ?? '';
    ?>
    <div class="ex-card <?= $i===0?'active':'' ?>" id="card<?= $i ?>">
      <div class="ex-card-header" onclick="toggleCard(<?= $i ?>)">
        <div class="ex-num-badge" id="badge<?= $i ?>"><?= $i+1 ?></div>
        <div class="ex-title-wrap">
          <div class="ex-card-name"><?= htmlspecialchars($ex['name']) ?></div>
          <div class="ex-card-meta"><?= $ex['sets'] ?> sets × <?= $ex['reps'] ?> · <?= $ex['muscle'] ?></div>
        </div>
        <div class="ex-status" id="status<?= $i ?>">⬜</div>
      </div>
      <div class="ex-body">
        <?php if ($has_img): ?>
        <div class="ex-media">
          <img src="<?= $img_src ?>" alt="<?= htmlspecialchars($ex['name']) ?>" class="ex-demo-img">
          <div class="muscle-badge"><i class="bi bi-bullseye"></i> <?= htmlspecialchars($ex['muscle']) ?></div>
        </div>
        <?php else: ?>
        <div class="ex-media ex-placeholder">
          <div class="ph-inner">
            <div class="ph-icon"><i class="bi bi-person-arms-up"></i></div>
            <div class="ph-name"><?= htmlspecialchars($ex['name']) ?></div>
            <div class="ph-muscle"><?= htmlspecialchars($ex['muscle']) ?></div>
          </div>
          <div class="muscle-badge"><i class="bi bi-bullseye"></i> <?= htmlspecialchars($ex['muscle']) ?></div>
        </div>
        <?php endif; ?>
        <div class="ex-details">
          <div class="detail-grid">
            <div class="dg-item"><div class="dg-val"><?= $ex['sets'] ?></div><div class="dg-label">Sets</div></div>
            <div class="dg-item"><div class="dg-val"><?= $ex['reps'] ?></div><div class="dg-label">Reps</div></div>
            <div class="dg-item"><div class="dg-val"><?= $ex['rest'] ?>s</div><div class="dg-label">Rest</div></div>
          </div>
          <div class="instructions-box">
            <div class="ib-label"><i class="bi bi-info-circle"></i> How to do it</div>
            <div class="ib-text"><?= htmlspecialchars($ex['instructions']) ?></div>
          </div>
          <button class="btn-done" id="btn<?= $i ?>" onclick="markDone(<?= $i ?>)">
            <i class="bi bi-check-lg"></i> Mark as Done
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <div class="finish-section" id="finishSection">
      <div style="font-size:2.5rem;margin-bottom:12px;">🏆</div>
      <div class="finish-title">All Exercises Done!</div>
      <div class="finish-sub">Amazing work on Day <?= $day ?>, <?= $first_name ?>! Tap below to save your progress.</div>
      <form method="POST">
        <input type="hidden" name="action" value="complete">
        <button type="submit" class="btn-finish"><i class="bi bi-check-circle-fill"></i> Complete Workout</button>
      </form>
    </div>
  </div>
</div>

<script>
var total = <?= count($today['exercises']) ?>;
var done  = new Array(total).fill(false);
function toggleCard(i) {
  var card = document.getElementById('card'+i);
  var wasActive = card.classList.contains('active');
  document.querySelectorAll('.ex-card').forEach(c => c.classList.remove('active'));
  if (!wasActive) card.classList.add('active');
}
function markDone(i) {
  if (done[i]) return;
  done[i] = true;
  var card = document.getElementById('card'+i);
  card.classList.remove('active'); card.classList.add('done-card');
  var badge = document.getElementById('badge'+i);
  badge.innerHTML = '<i class="bi bi-check-lg"></i>';
  badge.style.background = '#f0fdf4'; badge.style.color = '#16a34a';
  document.getElementById('status'+i).textContent = '✅';
  var btn = document.getElementById('btn'+i);
  btn.className = 'btn-done completed';
  btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Done!';
  var count = done.filter(Boolean).length;
  document.getElementById('progressText').textContent = count+' / '+total+' done';
  document.getElementById('progressFill').style.width = (count/total*100)+'%';
  if (i+1 < total && !done[i+1]) {
    var next = document.getElementById('card'+(i+1));
    next.classList.add('active');
    next.scrollIntoView({behavior:'smooth',block:'start'});
  }
  if (count === total) {
    var fin = document.getElementById('finishSection');
    fin.classList.add('show');
    fin.scrollIntoView({behavior:'smooth'});
  }
}
</script>
</body>
</html>