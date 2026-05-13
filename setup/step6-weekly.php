<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();
if (empty($_SESSION['setup_activity'])) { header('Location: step5-activity.php'); exit; }

$user_id = $_SESSION['user_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $days = (int)($_POST['days'] ?? 0);
    if ($days < 2 || $days > 6) $error = 'Please select between 2 and 6 days per week.';
    else {
        $db = getDB();
        $s = $db->prepare("INSERT INTO user_fitness (user_id, fitness_level, activity_level, days_per_week) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE fitness_level=?, activity_level=?, days_per_week=?");
        $s->execute([$user_id,$_SESSION['setup_level'],$_SESSION['setup_activity'],$days,$_SESSION['setup_level'],$_SESSION['setup_activity'],$days]);
        $_SESSION['setup_days'] = $days;
        header('Location: step7-generate.php'); exit;
    }
}

$days_options = [
    2 => ['label'=>'2 Days','desc'=>'Great for beginners. Light commitment.','intensity'=>'Low','badge'=>'#dbeafe','bc'=>'#1d4ed8'],
    3 => ['label'=>'3 Days','desc'=>'Most popular. Balanced recovery time.','intensity'=>'Moderate','badge'=>'#dcfce7','bc'=>'#15803d'],
    4 => ['label'=>'4 Days','desc'=>'Good for faster progress.','intensity'=>'Moderate-High','badge'=>'#fef9c3','bc'=>'#854d0e'],
    5 => ['label'=>'5 Days','desc'=>'Serious training. Good for advanced users.','intensity'=>'High','badge'=>'#fed7aa','bc'=>'#c2410c'],
    6 => ['label'=>'6 Days','desc'=>'Intense. Only for advanced athletes.','intensity'=>'Very High','badge'=>'#fee2e2','bc'=>'#dc2626'],
];
$sel_days = (int)($_POST['days'] ?? 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Weekly Goal — MyFitCal Setup</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:#f5f5f4;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;}
.card{background:#fff;border-radius:12px;border:1px solid #e7e5e4;padding:2.5rem 2rem;width:100%;max-width:460px;box-shadow:0 1px 4px rgba(0,0,0,.06);}
.steps{display:flex;align-items:center;margin-bottom:.5rem;}
.step-dot{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;}
.step-dot.active{background:#1c1917;color:#fff;}
.step-dot.done{background:#16a34a;color:#fff;}
.step-dot.pending{background:#f5f5f4;color:#a8a29e;border:1px solid #e7e5e4;}
.step-line{flex:1;height:2px;background:#f5f5f4;}
.step-line.done{background:#16a34a;}
.step-label{font-size:11px;color:#78716c;font-weight:500;margin-bottom:1.75rem;margin-top:6px;}
.page-icon{width:40px;height:40px;border-radius:8px;background:#f5f5f4;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:1rem;}
h1{font-size:20px;font-weight:700;color:#1c1917;margin-bottom:6px;}
.sub{font-size:13px;color:#78716c;margin-bottom:1.25rem;line-height:1.6;}
.alert-err{background:#fef2f2;border:1px solid #fecaca;border-left:3px solid #dc2626;border-radius:8px;padding:10px 12px;font-size:13px;color:#dc2626;margin-bottom:1.25rem;display:flex;align-items:center;gap:6px;}
.week-visual{display:flex;gap:5px;justify-content:center;margin-bottom:1.25rem;}
.wv-day{width:34px;height:34px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;transition:all .2s;}
.wv-day.active{background:#1c1917;color:#fff;}
.wv-day.rest{background:#f5f5f4;color:#a8a29e;}
.day-list{display:flex;flex-direction:column;gap:6px;margin-bottom:1.25rem;}
.day-card{position:relative;cursor:pointer;}
.day-card input[type=radio]{position:absolute;opacity:0;width:0;height:0;}
.day-inner{display:flex;align-items:center;gap:12px;border:1.5px solid #e7e5e4;border-radius:8px;padding:10px 14px;background:#fafaf9;transition:all .15s;}
.day-card input:checked + .day-inner{border-color:#1c1917;background:#fff;border-width:2px;}
.day-num{font-size:22px;font-weight:700;color:#1c1917;width:28px;flex-shrink:0;line-height:1;}
.day-card input:checked + .day-inner .day-num{color:#16a34a;}
.day-lbl{font-size:13px;font-weight:600;color:#1c1917;}
.day-desc{font-size:11px;color:#78716c;margin-top:1px;}
.day-badge{font-size:10px;font-weight:600;padding:3px 8px;border-radius:4px;white-space:nowrap;}
.btn{width:100%;background:#1c1917;color:#fafaf9;border:none;border-radius:8px;padding:11px;font-family:'DM Sans',sans-serif;font-weight:600;font-size:14px;cursor:pointer;transition:background .15s;display:flex;align-items:center;justify-content:center;gap:6px;}
.btn:hover{background:#292524;}
.back-link{display:block;text-align:center;margin-top:10px;font-size:12px;color:#78716c;text-decoration:none;}
.back-link:hover{color:#1c1917;}
</style>
</head>
<body>
<div class="card">
  <div class="steps">
    <div class="step-dot done">1</div><div class="step-line done"></div>
    <div class="step-dot done">2</div><div class="step-line done"></div>
    <div class="step-dot done">3</div><div class="step-line done"></div>
    <div class="step-dot done">4</div><div class="step-line done"></div>
    <div class="step-dot done">5</div><div class="step-line done"></div>
    <div class="step-dot active">6</div>
  </div>
  <div class="step-label">Step 6 of 6 — Weekly Schedule</div>
  <div class="page-icon"><i class="bi bi-calendar-week"></i></div>
  <h1>Set Your Weekly Workout Days</h1>
  <p class="sub">How many days per week can you commit to training? Choose a realistic number.</p>

  <div class="week-visual" id="weekVisual">
    <?php foreach(['M','T','W','T','F','S','S'] as $i=>$d): ?>
    <div class="wv-day <?= $i < $sel_days ? 'active' : 'rest' ?>" id="wvd<?= $i ?>"><?= $d ?></div>
    <?php endforeach; ?>
  </div>

  <?php if ($error): ?>
  <div class="alert-err"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="day-list">
      <?php foreach ($days_options as $d => $opt): ?>
      <label class="day-card">
        <input type="radio" name="days" value="<?= $d ?>" <?= $sel_days===$d?'checked':'' ?> onchange="updateVisual(<?= $d ?>)">
        <div class="day-inner">
          <div class="day-num"><?= $d ?></div>
          <div style="flex:1;">
            <div class="day-lbl"><?= $opt['label'] ?> per week</div>
            <div class="day-desc"><?= $opt['desc'] ?></div>
          </div>
          <div class="day-badge" style="background:<?= $opt['badge'] ?>;color:<?= $opt['bc'] ?>;"><?= $opt['intensity'] ?></div>
        </div>
      </label>
      <?php endforeach; ?>
    </div>
    <button type="submit" class="btn">Generate My Plan <i class="bi bi-arrow-right"></i></button>
  </form>
  <a href="step5-activity.php" class="back-link"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<script>
function updateVisual(days) {
  document.querySelectorAll('.wv-day').forEach(function(el,i){
    el.className = 'wv-day ' + (i < days ? 'active' : 'rest');
  });
}
</script>
</body>
</html>