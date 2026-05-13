<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$db = getDB();

$check = $db->prepare("SELECT height_cm, weight_kg FROM user_profiles WHERE user_id=?");
$check->execute([$user_id]);
$profile = $check->fetch();
if (!$profile) { header('Location: step1-profile.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $goal = $_POST['goal'] ?? '';
    if (!in_array($goal, ['lose','maintain','gain','muscle'])) $error = 'Please select a goal.';
    else {
        $h   = $profile['height_cm'];
        $w   = $profile['weight_kg'];
        $age = $_SESSION['setup_age'] ?? 25;

        $urow = $db->prepare("SELECT gender FROM users WHERE id=?");
        $urow->execute([$user_id]);
        $u = $urow->fetch();
        $gender = $u['gender'] ?? 'male';

        if ($gender === 'female')
            $bmr = (10 * $w) + (6.25 * $h) - (5 * $age) - 161;
        else
            $bmr = (10 * $w) + (6.25 * $h) - (5 * $age) + 5;

        $tdee = round($bmr * 1.55);

        switch ($goal) {
            case 'lose':    $calories = max(1200, $tdee - 500); $protein = round($w * 2.2); break;
            case 'maintain':$calories = $tdee;                  $protein = round($w * 1.8); break;
            case 'gain':    $calories = $tdee + 400;            $protein = round($w * 2.0); break;
            case 'muscle':  $calories = $tdee + 300;            $protein = round($w * 2.4); break;
        }

        $_SESSION['setup_goal']     = $goal;
        $_SESSION['setup_calories'] = $calories;
        $_SESSION['setup_protein']  = $protein;
        $_SESSION['setup_tdee']     = $tdee;

        header('Location: step3-nutrition.php'); exit;
    }
}

$goals = [
    'lose'     => ['title'=>'Weight Loss',  'desc'=>'Reduce body fat through a calorie deficit and cardio-focused training.','icon'=>'bi-arrow-down-circle-fill','color'=>'#ef4444','img'=>'/myfitcal_system/assets/image/Loseweight.png'],
    'maintain' => ['title'=>'Maintenance',  'desc'=>'Keep your current weight while improving fitness and overall health.',   'icon'=>'bi-arrows-collapse-vertical','color'=>'#2563eb','img'=>'/myfitcal_system/assets/image/maintenance.png'],
    'gain'     => ['title'=>'Weight Gain',  'desc'=>'Increase body weight through a calorie surplus and progressive training.','icon'=>'bi-arrow-up-circle-fill','color'=>'#16a34a','img'=>'/myfitcal_system/assets/image/Gainweight.png'],
    'muscle'   => ['title'=>'Muscle Gain',  'desc'=>'Build lean muscle through a small calorie surplus and strength training.','icon'=>'bi-lightning-charge-fill','color'=>'#f97316','img'=>'/myfitcal_system/assets/image/Musclegain.png'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Your Goal — MyFitCal Setup</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:#f5f5f4;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;}

.card{background:#fff;border-radius:12px;border:1px solid #e7e5e4;padding:2.5rem 2rem;width:100%;max-width:560px;box-shadow:0 1px 4px rgba(0,0,0,.06);}

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

.goal-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:1.25rem;}
.goal-card{position:relative;cursor:pointer;}
.goal-card input[type=radio]{position:absolute;opacity:0;width:0;height:0;}
.goal-inner{border:1.5px solid #e7e5e4;border-radius:10px;overflow:hidden;transition:all .2s;background:#fafaf9;}
.goal-card input:checked + .goal-inner{border-color:var(--gc);border-width:2px;background:#fff;}
.goal-img{width:100%;height:130px;object-fit:cover;object-position:center top;}
.goal-body{padding:10px 12px;}
.goal-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;}
.goal-title{font-size:13px;font-weight:700;color:#1c1917;}
.goal-check{width:18px;height:18px;border-radius:50%;border:1.5px solid #d6d3d1;display:flex;align-items:center;justify-content:center;transition:all .2s;flex-shrink:0;}
.goal-card input:checked + .goal-inner .goal-check{border-color:var(--gc);background:var(--gc);}
.goal-card input:checked + .goal-inner .goal-check::after{content:'';width:6px;height:6px;border-radius:50%;background:#fff;}
.goal-desc{font-size:11px;color:#78716c;line-height:1.5;}

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
    <div class="step-dot active">2</div><div class="step-line"></div>
    <div class="step-dot pending">3</div><div class="step-line"></div>
    <div class="step-dot pending">4</div><div class="step-line"></div>
    <div class="step-dot pending">5</div><div class="step-line"></div>
    <div class="step-dot pending">6</div>
  </div>
  <div class="step-label">Step 2 of 6 — Your Fitness Goal</div>

  <div class="page-icon"><i class="bi bi-bullseye"></i></div>
  <h1>What is Your Fitness Goal?</h1>
  <p class="sub">Choose the goal that best matches what you want to achieve. Each goal has a different calorie and workout plan.</p>

  <?php if ($error): ?>
  <div class="alert-err"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="goal-grid">
      <?php foreach ($goals as $key => $g): $sel = ($_POST['goal'] ?? '') === $key; ?>
      <label class="goal-card">
        <input type="radio" name="goal" value="<?= $key ?>" <?= $sel ? 'checked' : '' ?>>
        <div class="goal-inner" style="--gc:<?= $g['color'] ?>;">
          <img src="<?= $g['img'] ?>" alt="<?= $g['title'] ?>" class="goal-img">
          <div class="goal-body">
            <div class="goal-top">
              <div class="goal-title">
                <i class="bi <?= $g['icon'] ?>" style="color:<?= $g['color'] ?>;margin-right:3px;font-size:12px;"></i>
                <?= $g['title'] ?>
              </div>
              <div class="goal-check"></div>
            </div>
            <div class="goal-desc"><?= $g['desc'] ?></div>
          </div>
        </div>
      </label>
      <?php endforeach; ?>
    </div>
    <button type="submit" class="btn">Continue <i class="bi bi-arrow-right"></i></button>
  </form>
  <a href="step1-profile.php" class="back-link"><i class="bi bi-arrow-left"></i> Back</a>
</div>
</body>
</html>