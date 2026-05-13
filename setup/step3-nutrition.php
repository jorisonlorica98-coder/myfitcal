<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();
if (empty($_SESSION['setup_goal'])) { header('Location: step2-goal.php'); exit; }

$user_id  = $_SESSION['user_id'];
$goal     = $_SESSION['setup_goal'];
$calories = $_SESSION['setup_calories'];
$protein  = $_SESSION['setup_protein'];
$tdee     = $_SESSION['setup_tdee'];
$deficit  = abs($goal === 'lose' ? $tdee - $calories : $calories - $tdee);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $s  = $db->prepare("INSERT INTO user_goals (user_id, goal_type, daily_calories, daily_protein_g) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE goal_type=VALUES(goal_type), daily_calories=VALUES(daily_calories), daily_protein_g=VALUES(daily_protein_g)");
    $s->execute([$user_id, $goal, $calories, $protein]);
    header('Location: step4-fitness.php'); exit;
}

$goal_colors = ['lose'=>'#ef4444','maintain'=>'#2563eb','gain'=>'#16a34a','muscle'=>'#f97316'];
$goal_labels = ['lose'=>'Weight Loss','maintain'=>'Maintenance','gain'=>'Weight Gain','muscle'=>'Muscle Gain'];
$gc = $goal_colors[$goal] ?? '#16a34a';
$gl = $goal_labels[$goal] ?? 'Fitness';
$dir_label = $goal === 'lose' ? 'Calorie Deficit' : ($goal === 'maintain' ? 'At Maintenance' : 'Calorie Surplus');
$dir_sign  = $goal === 'lose' ? '-' : ($goal === 'maintain' ? '' : '+');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Nutrition Targets — MyFitCal Setup</title>
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
.goal-tag{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600;margin-bottom:1.25rem;border:1px solid;}
.nut-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:1.25rem;}
.nut-card{border-radius:8px;padding:16px;text-align:center;border:1px solid #e7e5e4;}
.nut-val{font-size:28px;font-weight:700;line-height:1;margin-bottom:3px;}
.nut-unit{font-size:10px;font-weight:600;color:#78716c;text-transform:uppercase;letter-spacing:.5px;}
.nut-label{font-size:12px;color:#78716c;margin-top:4px;}
.tdee-box{background:#fafaf9;border:1px solid #e7e5e4;border-radius:8px;padding:14px;margin-bottom:1.25rem;}
.tdee-row{display:flex;justify-content:space-between;align-items:center;font-size:13px;padding:5px 0;border-bottom:1px solid #f5f5f4;}
.tdee-row:last-child{border-bottom:none;font-weight:700;color:#1c1917;}
.tdee-row span:first-child{color:#78716c;}
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
    <div class="step-dot active">3</div><div class="step-line"></div>
    <div class="step-dot pending">4</div><div class="step-line"></div>
    <div class="step-dot pending">5</div><div class="step-line"></div>
    <div class="step-dot pending">6</div>
  </div>
  <div class="step-label">Step 3 of 6 — Nutrition Targets</div>
  <div class="page-icon"><i class="bi bi-fire"></i></div>
  <h1>Your Daily Nutrition Targets</h1>
  <p class="sub">Based on your body measurements and goal, here are your personalized daily targets.</p>
  <div class="goal-tag" style="background:<?= $gc ?>11;color:<?= $gc ?>;border-color:<?= $gc ?>33;"><?= $gl ?> Plan</div>
  <div class="nut-grid">
    <div class="nut-card" style="background:#fff7ed;">
      <div class="nut-val" style="color:#f97316;"><?= number_format($calories) ?></div>
      <div class="nut-unit">kcal / day</div>
      <div class="nut-label">Daily Calories</div>
    </div>
    <div class="nut-card" style="background:#f0fdf4;">
      <div class="nut-val" style="color:#16a34a;"><?= $protein ?>g</div>
      <div class="nut-unit">protein / day</div>
      <div class="nut-label">Daily Protein</div>
    </div>
  </div>
  <div class="tdee-box">
    <div class="tdee-row"><span>Maintenance Calories (TDEE)</span><span><?= number_format($tdee) ?> kcal</span></div>
    <div class="tdee-row"><span><?= $dir_label ?></span><span><?= $dir_sign ?><?= $goal === 'maintain' ? '0' : number_format($deficit) ?> kcal</span></div>
    <div class="tdee-row"><span>Your Daily Target</span><span><?= number_format($calories) ?> kcal</span></div>
  </div>
  <form method="POST">
    <button type="submit" class="btn">Got it — Continue <i class="bi bi-arrow-right"></i></button>
  </form>
  <a href="step2-goal.php" class="back-link"><i class="bi bi-arrow-left"></i> Back</a>
</div>
</body>
</html>