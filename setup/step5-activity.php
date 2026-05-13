<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();
if (empty($_SESSION['setup_level'])) { header('Location: step4-fitness.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['activity'] ?? '';
    if (!in_array($act, ['sedentary','lightly_active','moderately_active','very_active'])) $error = 'Please select an activity level.';
    else { $_SESSION['setup_activity'] = $act; header('Location: step6-weekly.php'); exit; }
}

$activities = [
    'sedentary'          => ['label'=>'Sedentary',         'desc'=>'Little or no exercise. Mostly sitting.','example'=>'Office work, watching TV','multiplier'=>'× 1.2',  'icon'=>'bi-display',              'color'=>'#6b7280'],
    'lightly_active'     => ['label'=>'Lightly Active',    'desc'=>'Light exercise 1–3 days per week.',    'example'=>'Light walks, casual cycling',  'multiplier'=>'× 1.375','icon'=>'bi-person-walking',       'color'=>'#3b82f6'],
    'moderately_active'  => ['label'=>'Moderately Active', 'desc'=>'Moderate exercise 3–5 days per week.', 'example'=>'Regular gym, jogging',         'multiplier'=>'× 1.55', 'icon'=>'bi-bicycle',              'color'=>'#f97316'],
    'very_active'        => ['label'=>'Very Active',       'desc'=>'Hard exercise 6–7 days per week.',     'example'=>'Daily intense training, athletes','multiplier'=>'× 1.725','icon'=>'bi-lightning-charge-fill','color'=>'#dc2626'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Activity Level — MyFitCal Setup</title>
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
.act-list{display:flex;flex-direction:column;gap:8px;margin-bottom:1.25rem;}
.act-card{position:relative;cursor:pointer;}
.act-card input[type=radio]{position:absolute;opacity:0;width:0;height:0;}
.act-inner{display:flex;align-items:center;gap:12px;border:1.5px solid #e7e5e4;border-radius:8px;padding:12px 14px;background:#fafaf9;transition:all .15s;}
.act-card input:checked + .act-inner{border-color:var(--ac);background:#fff;border-width:2px;}
.act-icon{width:38px;height:38px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
.act-title{font-size:13px;font-weight:700;color:#1c1917;display:flex;align-items:center;gap:6px;margin-bottom:3px;}
.act-mult{font-size:10px;font-weight:700;padding:2px 6px;border-radius:4px;background:#f5f5f4;color:#57534e;}
.act-desc{font-size:11px;color:#78716c;}
.act-ex{font-size:11px;color:#a8a29e;font-style:italic;margin-top:2px;}
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
    <div class="step-dot active">5</div><div class="step-line"></div>
    <div class="step-dot pending">6</div>
  </div>
  <div class="step-label">Step 5 of 6 — Activity Level</div>
  <div class="page-icon"><i class="bi bi-activity"></i></div>
  <h1>What is Your Activity Level?</h1>
  <p class="sub">This helps us calculate your actual calorie burn and fine-tune your nutrition plan.</p>
  <?php if ($error): ?>
  <div class="alert-err"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="act-list">
      <?php foreach ($activities as $key => $act): ?>
      <label class="act-card">
        <input type="radio" name="activity" value="<?= $key ?>" <?= ($_POST['activity']??'')===$key?'checked':'' ?>>
        <div class="act-inner" style="--ac:<?= $act['color'] ?>;">
          <div class="act-icon" style="background:<?= $act['color'] ?>15;color:<?= $act['color'] ?>;"><i class="bi <?= $act['icon'] ?>"></i></div>
          <div style="flex:1;">
            <div class="act-title"><?= $act['label'] ?> <span class="act-mult"><?= $act['multiplier'] ?></span></div>
            <div class="act-desc"><?= $act['desc'] ?></div>
            <div class="act-ex"><?= $act['example'] ?></div>
          </div>
        </div>
      </label>
      <?php endforeach; ?>
    </div>
    <button type="submit" class="btn">Continue <i class="bi bi-arrow-right"></i></button>
  </form>
  <a href="step4-fitness.php" class="back-link"><i class="bi bi-arrow-left"></i> Back</a>
</div>
</body>
</html>