<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();
if (empty($_SESSION['setup_goal'])) { header('Location: step2-goal.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $level = $_POST['level'] ?? '';
    if (!in_array($level, ['beginner','normal','expert','advance'])) $error = 'Please select a fitness level.';
    else { $_SESSION['setup_level'] = $level; header('Location: step5-activity.php'); exit; }
}

$levels = [
    'beginner' => ['label'=>'Beginner','desc'=>'Little to no exercise experience. Starting from scratch.','icon'=>'bi-person','color'=>'#3b82f6','tags'=>['0–6 months','Low intensity','Basic movements']],
    'normal'   => ['label'=>'Normal',  'desc'=>'Some experience with regular workouts. Comfortable with basics.','icon'=>'bi-person-walking','color'=>'#16a34a','tags'=>['6–18 months','Moderate intensity','Good form']],
    'expert'   => ['label'=>'Expert',  'desc'=>'Consistent training for over a year. Strong foundation.','icon'=>'bi-person-arms-up','color'=>'#f97316','tags'=>['1–3 years','High intensity','Complex movements']],
    'advance'  => ['label'=>'Advanced','desc'=>'Elite level. Trains hard regularly. Needs challenging programs.','icon'=>'bi-trophy','color'=>'#dc2626','tags'=>['3+ years','Very high intensity','Full performance']],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fitness Level — MyFitCal Setup</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:#f5f5f4;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;}
.card{background:#fff;border-radius:12px;border:1px solid #e7e5e4;padding:2.5rem 2rem;width:100%;max-width:480px;box-shadow:0 1px 4px rgba(0,0,0,.06);}
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
.sub{font-size:13px;color:#78716c;margin-bottom:1.5rem;line-height:1.6;}
.alert-err{background:#fef2f2;border:1px solid #fecaca;border-left:3px solid #dc2626;border-radius:8px;padding:10px 12px;font-size:13px;color:#dc2626;margin-bottom:1.25rem;display:flex;align-items:center;gap:6px;}
.level-list{display:flex;flex-direction:column;gap:8px;margin-bottom:1.25rem;}
.level-card{position:relative;cursor:pointer;}
.level-card input[type=radio]{position:absolute;opacity:0;width:0;height:0;}
.level-inner{display:flex;align-items:center;gap:12px;border:1.5px solid #e7e5e4;border-radius:8px;padding:12px 14px;background:#fafaf9;transition:all .15s;}
.level-card input:checked + .level-inner{border-color:var(--lc);background:#fff;border-width:2px;}
.level-icon{width:38px;height:38px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
.level-title{font-size:13px;font-weight:700;color:#1c1917;margin-bottom:3px;}
.level-desc{font-size:11px;color:#78716c;margin-bottom:5px;}
.level-tags{display:flex;flex-wrap:wrap;gap:4px;}
.ltag{font-size:10px;font-weight:600;padding:2px 6px;border-radius:4px;background:#f5f5f4;color:#57534e;}
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
    <div class="step-dot active">4</div><div class="step-line"></div>
    <div class="step-dot pending">5</div><div class="step-line"></div>
    <div class="step-dot pending">6</div>
  </div>
  <div class="step-label">Step 4 of 6 — Fitness Level</div>
  <div class="page-icon"><i class="bi bi-bar-chart-fill"></i></div>
  <h1>What is Your Fitness Level?</h1>
  <p class="sub">Be honest — choosing the right level ensures your workout plan matches your current fitness.</p>
  <?php if ($error): ?>
  <div class="alert-err"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="level-list">
      <?php foreach ($levels as $key => $lv): ?>
      <label class="level-card">
        <input type="radio" name="level" value="<?= $key ?>" <?= ($_POST['level']??'')===$key?'checked':'' ?>>
        <div class="level-inner" style="--lc:<?= $lv['color'] ?>;">
          <div class="level-icon" style="background:<?= $lv['color'] ?>15;color:<?= $lv['color'] ?>;"><i class="bi <?= $lv['icon'] ?>"></i></div>
          <div style="flex:1;">
            <div class="level-title"><?= $lv['label'] ?></div>
            <div class="level-desc"><?= $lv['desc'] ?></div>
            <div class="level-tags"><?php foreach($lv['tags'] as $t): ?><span class="ltag"><?= $t ?></span><?php endforeach; ?></div>
          </div>
        </div>
      </label>
      <?php endforeach; ?>
    </div>
    <button type="submit" class="btn">Continue <i class="bi bi-arrow-right"></i></button>
  </form>
  <a href="step3-nutrition.php" class="back-link"><i class="bi bi-arrow-left"></i> Back</a>
</div>
</body>
</html>