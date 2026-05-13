<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../config/exercises.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$db = getDB();
$day = (int)($_GET['day'] ?? 1);

$fq = $db->prepare("SELECT * FROM user_fitness WHERE user_id=?");
$fq->execute([$user_id]);
$fitness = $fq->fetch();

$gq = $db->prepare("SELECT * FROM user_goals WHERE user_id=?");
$gq->execute([$user_id]);
$goals = $gq->fetch();

$level    = $fitness['fitness_level'] ?? 'beginner';
$activity = $fitness['activity_level'] ?? 'sedentary';
$goal_type= $goals['goal_type'] ?? 'maintain';
$days_pw  = $fitness['days_per_week'] ?? 3;

$genderq = $db->prepare("SELECT gender FROM users WHERE id=? LIMIT 1");
$genderq->execute([$user_id]);
$gender = $genderq->fetchColumn() ?: 'male';
$schedule = getExercisePlan($level, $activity, $goal_type, $days_pw, $gender);
$today    = $schedule[$day];

$next_day = null;
for ($d = $day + 1; $d <= 30; $d++) {
    if (!$schedule[$d]['is_rest']) { $next_day = $d; break; }
}

$total_kcal = array_sum(array_column($today['exercises'], 'calories')) * 3;

$uq = $db->prepare("SELECT name FROM users WHERE id=?");
$uq->execute([$user_id]);
$user = $uq->fetch();

$cq = $db->prepare("SELECT COUNT(DISTINCT day_number) FROM user_workout_progress WHERE user_id=? AND completed=1");
$cq->execute([$user_id]);
$total_done = (int)$cq->fetchColumn();

$activity_level2 = $fitness['activity_level'] ?? 'sedentary';
$times_map = [
    'sedentary'         => ['6:00 AM','7:00 AM','12:00 PM','6:00 PM','7:00 PM'],
    'lightly_active'    => ['6:30 AM','7:30 AM','12:00 PM','5:30 PM','7:00 PM'],
    'moderately_active' => ['6:00 AM','12:00 PM','5:00 PM','6:00 PM'],
    'very_active'       => ['5:30 AM','6:00 AM','5:00 PM','6:00 PM'],
];
$sugg_times  = $times_map[$activity_level2] ?? $times_map['sedentary'];
$saved_time2 = '';
try {
    $db->exec("CREATE TABLE IF NOT EXISTS workout_reminders (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id INT UNSIGNED NOT NULL UNIQUE,
      reminder_time VARCHAR(10) NOT NULL,
      next_workout_day INT NOT NULL DEFAULT 1,
      is_active TINYINT(1) DEFAULT 1,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $rq = $db->prepare("SELECT reminder_time FROM workout_reminders WHERE user_id=? AND is_active=1");
    $rq->execute([$user_id]);
    $saved_time2 = $rq->fetchColumn() ?: ($_SESSION['preferred_workout_time'] ?? '');
} catch(Exception $e) { $saved_time2 = $_SESSION['preferred_workout_time'] ?? ''; }

if (!empty($_POST['save_workout_time'])) {
    $wt2 = $_POST['workout_time'] ?? '';
    $wd2 = $next_day ?? 1;
    $_SESSION['preferred_workout_time'] = $wt2;
    $saved_time2 = $wt2;
    try {
        $db->prepare("INSERT INTO workout_reminders (user_id,reminder_time,next_workout_day,is_active)
          VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE reminder_time=VALUES(reminder_time),
          next_workout_day=VALUES(next_workout_day),is_active=1,updated_at=NOW()")
          ->execute([$user_id, $wt2, $wd2]);
    } catch(Exception $e) {}
}

$first_name  = htmlspecialchars(explode(' ', $user['name'])[0]);
$progress_pct = round($total_done / 30 * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Workout Complete! — MyFitCal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}

body{
  font-family:'DM Sans',sans-serif;
  background:#f5f5f4;
  color:#1c1917;
  min-height:100vh;
  display:flex;
  align-items:flex-start;
  justify-content:center;
  padding:40px 16px 60px;
  position:relative;
  overflow-x:hidden;
}

/* ── CONFETTI ── */
.confetti-wrap{position:fixed;inset:0;pointer-events:none;z-index:0;overflow:hidden;}
.c-dot{position:absolute;border-radius:2px;animation:fall linear forwards;}
@keyframes fall{
  0%{transform:translateY(-20px) rotate(0deg);opacity:1;}
  100%{transform:translateY(110vh) rotate(720deg);opacity:0;}
}

.wrap{
  max-width:480px;width:100%;
  position:relative;z-index:1;
  display:flex;flex-direction:column;gap:10px;
}

/* ── SLIDE UP ANIMATION ── */
@keyframes slideUp{
  from{opacity:0;transform:translateY(20px);}
  to{opacity:1;transform:translateY(0);}
}

/* ── HERO CARD — matches app's dark sidebar ── */
.hero{
  background:#1c1917;
  border-radius:12px;
  padding:36px 24px 28px;
  text-align:center;
  position:relative;
  overflow:hidden;
  animation:slideUp .45s cubic-bezier(.16,1,.3,1) both;
}

/* Subtle green shimmer line on top */
.hero::before{
  content:'';
  position:absolute;top:0;left:0;right:0;
  height:2px;
  background:linear-gradient(90deg,transparent 0%,#16a34a 40%,#22c55e 60%,transparent 100%);
}

/* Faint radial glow in bg */
.hero::after{
  content:'';
  position:absolute;
  top:-60px;left:50%;transform:translateX(-50%);
  width:300px;height:200px;
  background:radial-gradient(ellipse,rgba(34,197,94,.08) 0%,transparent 70%);
  pointer-events:none;
}

/* ── TROPHY ── */
.trophy-wrap{
  position:relative;
  width:72px;height:72px;
  margin:0 auto 20px;
  animation:popIn .55s cubic-bezier(.175,.885,.32,1.275) .1s both;
}
@keyframes popIn{
  from{transform:scale(0) rotate(-15deg);opacity:0;}
  to{transform:scale(1) rotate(0);opacity:1;}
}
.trophy-ring{
  position:absolute;inset:0;
  border-radius:50%;
  border:1.5px dashed rgba(34,197,94,.25);
  animation:spin 8s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg);}}
.trophy-circle{
  width:72px;height:72px;
  border-radius:50%;
  background:#292524;
  border:1px solid rgba(34,197,94,.2);
  display:flex;align-items:center;justify-content:center;
  font-size:26px;
  color:#16a34a;
}

.day-tag{
  display:inline-flex;align-items:center;gap:5px;
  background:rgba(22,163,74,.15);
  border:1px solid rgba(22,163,74,.25);
  border-radius:999px;
  padding:4px 12px;
  font-size:10px;font-weight:700;
  color:#16a34a;
  letter-spacing:.07em;text-transform:uppercase;
  margin-bottom:12px;
  animation:slideUp .4s .15s both;
}

.hero-title{
  font-size:28px;font-weight:700;
  color:#fafaf9;
  line-height:1.2;
  margin-bottom:6px;
  animation:slideUp .4s .2s both;
}
.hero-title .name{color:#22c55e;}

.hero-sub{
  font-size:13px;
  color:#78716c;
  line-height:1.65;
  animation:slideUp .4s .25s both;
}

/* ── STATS ROW — same white card style as dashboard ── */
.stats{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:8px;
  animation:slideUp .4s .3s both;
}
.scard{
  background:#fff;
  border:1px solid #e7e5e4;
  border-radius:8px;
  padding:16px 10px;
  text-align:center;
}
.scard-val{
  font-size:22px;font-weight:700;
  color:#1c1917;
  line-height:1;margin-bottom:4px;
}
.scard-label{
  font-size:10px;font-weight:600;
  color:#78716c;
  text-transform:uppercase;letter-spacing:.07em;
}

/* ── PROGRESS — same as dashboard prog-card ── */
.prog-card{
  background:#fff;
  border:1px solid #e7e5e4;
  border-radius:8px;
  padding:16px 18px;
  animation:slideUp .4s .35s both;
}
.prog-top{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:8px;
}
.prog-top span{font-size:12px;font-weight:600;color:#78716c;}
.prog-top strong{font-size:12px;font-weight:700;color:#16a34a;}
.prog-track{
  height:6px;
  background:#f5f5f4;
  border-radius:999px;overflow:hidden;
}
.prog-fill{
  height:100%;
  background:#1c1917;
  border-radius:999px;
  width:0;
  transition:width 1s cubic-bezier(.16,1,.3,1);
}

/* ── NEXT WORKOUT CARD ── */
.next-card{
  background:#fff;
  border:1px solid #e7e5e4;
  border-radius:8px;
  overflow:hidden;
  animation:slideUp .4s .4s both;
}
.next-head{
  display:flex;align-items:center;gap:12px;
  padding:14px 18px;
  border-bottom:1px solid #f5f5f4;
}
.next-icon{
  width:40px;height:40px;
  border-radius:8px;
  background:#1c1917;
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:16px;
  flex-shrink:0;
}
.next-eyebrow{font-size:10px;font-weight:700;color:#78716c;text-transform:uppercase;letter-spacing:.07em;margin-bottom:2px;}
.next-title{font-size:13px;font-weight:700;color:#1c1917;}
.next-meta{font-size:11px;color:#78716c;margin-top:1px;}

.reminder-body{padding:14px 18px;}
.reminder-lbl{
  display:flex;align-items:center;gap:5px;
  font-size:11px;font-weight:700;
  color:#78716c;
  text-transform:uppercase;letter-spacing:.07em;
  margin-bottom:10px;
}
.reminder-lbl i{color:#16a34a;}

.time-pills{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;}
.time-pill{
  padding:5px 12px;
  border-radius:999px;
  border:1px solid #e7e5e4;
  background:#fff;
  font-family:'DM Sans',sans-serif;
  font-size:12px;font-weight:500;
  color:#78716c;
  cursor:pointer;
  transition:all .12s;
}
.time-pill:hover{border-color:#16a34a;color:#16a34a;}
.time-pill.selected{background:#1c1917;border-color:#1c1917;color:#fff;}

.custom-input{
  padding:6px 10px;
  border-radius:6px;
  border:1px solid #e7e5e4;
  font-family:'DM Sans',sans-serif;
  font-size:12px;color:#1c1917;
  outline:none;
  transition:border-color .2s;
}
.custom-input:focus{border-color:#16a34a;}

.time-confirmed{
  display:flex;align-items:center;gap:6px;
  background:#f0fdf4;
  border:1px solid #bbf7d0;
  border-radius:6px;
  padding:8px 12px;
  font-size:12px;font-weight:600;
  color:#15803d;
  margin-top:8px;
}

/* ── ACTIONS ── */
.actions{
  display:flex;flex-direction:column;gap:8px;
  animation:slideUp .4s .45s both;
}
.btn-primary{
  display:flex;align-items:center;justify-content:center;gap:7px;
  width:100%;padding:12px;
  border-radius:8px;border:none;
  background:#1c1917;
  color:#fafaf9;
  font-family:'DM Sans',sans-serif;
  font-size:13px;font-weight:700;
  text-decoration:none;cursor:pointer;
  transition:background .12s;
}
.btn-primary:hover{background:#292524;color:#fafaf9;}

.btn-row{display:flex;gap:8px;}
.btn-sec{
  flex:1;display:flex;align-items:center;justify-content:center;gap:5px;
  padding:10px;border-radius:8px;
  border:1px solid #e7e5e4;background:#fff;
  font-family:'DM Sans',sans-serif;
  font-size:12px;font-weight:600;
  color:#78716c;text-decoration:none;
  transition:all .12s;
}
.btn-sec:hover{border-color:#1c1917;color:#1c1917;}
.btn-bot{
  flex:1;display:flex;align-items:center;justify-content:center;gap:5px;
  padding:10px;border-radius:8px;
  border:1px solid #bbf7d0;
  background:#f0fdf4;
  font-family:'DM Sans',sans-serif;
  font-size:12px;font-weight:600;
  color:#15803d;text-decoration:none;
  transition:all .12s;
}
.btn-bot:hover{background:#dcfce7;color:#15803d;}

/* ── COMPLETE STATE ── */
.complete-card{
  background:#fff;
  border:1px solid #e7e5e4;
  border-radius:8px;
  padding:28px 20px;
  text-align:center;
  animation:slideUp .4s .4s both;
}
.complete-icon{font-size:28px;color:#f97316;margin-bottom:10px;}
.complete-title{font-size:16px;font-weight:700;color:#1c1917;margin-bottom:4px;}
.complete-sub{font-size:12px;color:#78716c;}
</style>
</head>
<body>

<div class="confetti-wrap" id="confetti"></div>

<div class="wrap">

  <!-- HERO -->
  <div class="hero">
    <div class="trophy-wrap">
      <div class="trophy-ring"></div>
      <div class="trophy-circle"><i class="bi bi-trophy-fill"></i></div>
    </div>
    <div class="day-tag"><i class="bi bi-check-circle-fill"></i> Day <?= $day ?> Complete</div>
    <div class="hero-title">Well Done, <span class="name"><?= $first_name ?>!</span></div>
    <div class="hero-sub">You crushed Day <?= $day ?>. Your body is getting stronger — keep the momentum going.</div>
  </div>

  <!-- STATS -->
  <div class="stats">
    <div class="scard">
      <div class="scard-val"><?= count($today['exercises']) ?></div>
      <div class="scard-label">Exercises</div>
    </div>
    <div class="scard">
      <div class="scard-val">~<?= $total_kcal ?></div>
      <div class="scard-label">kcal Burned</div>
    </div>
    <div class="scard">
      <div class="scard-val"><?= $total_done ?>/30</div>
      <div class="scard-label">Days Done</div>
    </div>
  </div>

  <!-- PROGRESS -->
  <div class="prog-card">
    <div class="prog-top">
      <span>30-Day Progress</span>
      <strong><?= $progress_pct ?>%</strong>
    </div>
    <div class="prog-track">
      <div class="prog-fill" id="progFill"></div>
    </div>
  </div>

  <!-- NEXT WORKOUT -->
  <?php if ($next_day): ?>
  <div class="next-card">
    <div class="next-head">
      <div class="next-icon"><i class="bi bi-lightning-charge-fill"></i></div>
      <div>
        <div class="next-eyebrow">Up Next</div>
        <div class="next-title">Day <?= $next_day ?> — <?= htmlspecialchars($schedule[$next_day]['focus']) ?></div>
        <div class="next-meta"><?= count($schedule[$next_day]['exercises']) ?> exercises &middot; <?= ucfirst($level) ?> level</div>
      </div>
    </div>
    <div class="reminder-body">
      <div class="reminder-lbl"><i class="bi bi-alarm-fill"></i> When will you work out next?</div>
      <div class="time-pills">
        <?php foreach($sugg_times as $t): ?>
        <button class="time-pill <?= $saved_time2===$t?'selected':'' ?>" onclick="selectTime('<?= $t ?>',this)"><?= $t ?></button>
        <?php endforeach; ?>
        <button class="time-pill" onclick="showCustom()">Custom</button>
      </div>
      <input type="time" id="customInput" class="custom-input" style="display:none;" onchange="selectCustom(this.value)">
      <div class="time-confirmed" id="timeConf" style="<?= $saved_time2 ? '' : 'display:none;' ?>">
        <i class="bi bi-alarm-fill"></i>
        <?php if($saved_time2): ?>Reminder set — <strong><?= htmlspecialchars($saved_time2) ?></strong> for Day <?= $next_day ?><?php endif; ?>
      </div>
    </div>
  </div>

  <?php else: ?>
  <div class="complete-card">
    <div class="complete-icon"><i class="bi bi-trophy-fill"></i></div>
    <div class="complete-title">30-Day Program Complete!</div>
    <div class="complete-sub">You finished all 30 days. An incredible achievement!</div>
  </div>
  <?php endif; ?>

  <!-- ACTIONS -->
  <div class="actions">
    <a href="/myfitcal_system/user/meals.php?day=<?= $day ?>" class="btn-primary">
      <i class="bi bi-egg-fried"></i> View Today's Meal Plan
    </a>
    <div class="btn-row">
      <a href="/myfitcal_system/user/dashboard.php" class="btn-sec">
        <i class="bi bi-house-fill"></i> Dashboard
      </a>
      <a href="/myfitcal_system/user/chatbot.php" class="btn-bot">
        <i class="bi bi-robot"></i> Ask FitBot
      </a>
    </div>
  </div>

</div>

<script>
/* ── CONFETTI — green tones matching app ── */
(function(){
  var colors=['#1c1917','#16a34a','#22c55e','#86efac','#d6d3d1','#fff'];
  var cf = document.getElementById('confetti');
  for(var i=0;i<60;i++){
    var d=document.createElement('div');
    d.className='c-dot';
    d.style.cssText=[
      'left:'+Math.random()*100+'%',
      'background:'+colors[Math.floor(Math.random()*colors.length)],
      'width:'+(4+Math.random()*6)+'px',
      'height:'+(4+Math.random()*6)+'px',
      'animation-duration:'+(2.5+Math.random()*3)+'s',
      'animation-delay:'+(Math.random()*2)+'s',
      'border-radius:'+(Math.random()>0.5?'50%':'2px'),
      'opacity:.8',
    ].join(';');
    cf.appendChild(d);
  }
})();

/* ── PROGRESS ANIMATE ── */
window.addEventListener('load', function(){
  setTimeout(function(){
    document.getElementById('progFill').style.width = '<?= $progress_pct ?>%';
  }, 300);
});

/* ── REMINDER ── */
const nextWorkoutDay = <?= $next_day ?? 1 ?>;

function selectTime(t, btn){
  document.querySelectorAll('.time-pill').forEach(p=>p.classList.remove('selected'));
  btn.classList.add('selected');
  document.getElementById('customInput').style.display='none';
  saveTime(t);
}
function showCustom(){
  document.getElementById('customInput').style.display='inline-block';
  document.getElementById('customInput').focus();
}
function selectCustom(val){
  if(!val)return;
  const [h,m]=val.split(':');
  const hr=parseInt(h);
  const label=(hr%12||12)+':'+m+' '+(hr>=12?'PM':'AM');
  document.querySelectorAll('.time-pill').forEach(p=>p.classList.remove('selected'));
  saveTime(label);
}
function saveTime(t){
  fetch('/myfitcal_system/user/set_reminder.php',{
    method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({time:t,day:nextWorkoutDay})
  }).then(r=>r.json()).then(()=>{
    const el=document.getElementById('timeConf');
    if(el){
      el.innerHTML='<i class="bi bi-alarm-fill"></i> Reminder set — <strong>'+t+'</strong> for Day '+nextWorkoutDay;
      el.style.display='flex';
    }
    if('Notification' in window&&Notification.permission==='default'){
      Notification.requestPermission().then(p=>{if(p==='granted')scheduleNotif(t,nextWorkoutDay);});
    }else if(Notification.permission==='granted'){scheduleNotif(t,nextWorkoutDay);}
  });
}
function scheduleNotif(timeStr,day){
  const parts=timeStr.match(/(\d+):(\d+)\s*(AM|PM)/i);
  if(!parts)return;
  let h=parseInt(parts[1]);const m=parseInt(parts[2]);const ampm=parts[3].toUpperCase();
  if(ampm==='PM'&&h!==12)h+=12;if(ampm==='AM'&&h===12)h=0;
  const target=new Date();target.setDate(target.getDate()+1);target.setHours(h,m,0,0);
  const delay=target-new Date();
  if(delay>0&&delay<172800000){
    setTimeout(()=>{
      new Notification('MyFitCal — Time to Workout!',{
        body:'Day '+day+' workout is ready. Let\'s go!',
        icon:'/myfitcal_system/assets/image/logo.png'
      });
    },delay);
    localStorage.setItem('mfc_reminder',JSON.stringify({time:timeStr,day:day,set:new Date().toISOString()}));
  }
}
</script>
</body>
</html>