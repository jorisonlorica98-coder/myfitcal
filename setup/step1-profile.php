<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$db = getDB();

$check = $db->prepare("SELECT id FROM user_fitness WHERE user_id=?");
$check->execute([$user_id]);
if ($check->fetch()) { header('Location: /myfitcal_system/user/dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $height = (float)($_POST['height'] ?? 0);
    $weight = (float)($_POST['weight'] ?? 0);
    $age    = (int)($_POST['age'] ?? 0);

    if ($height < 50 || $height > 300)    $error = 'Please enter a valid height (50–300 cm).';
    elseif ($weight < 20 || $weight > 500) $error = 'Please enter a valid weight (20–500 kg).';
    elseif ($age < 10 || $age > 100)       $error = 'Please enter a valid age (10–100).';
    else {
        $s = $db->prepare("INSERT INTO user_profiles (user_id, height_cm, weight_kg, age) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE height_cm=?, weight_kg=?, age=?");
        $s->execute([$user_id, $height, $weight, $age, $height, $weight, $age]);
        $_SESSION['setup_height'] = $height;
        $_SESSION['setup_weight'] = $weight;
        $_SESSION['setup_age']    = $age;
        header('Location: step2-goal.php'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Your Profile — MyFitCal Setup</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:#f5f5f4;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;}

.card{background:#fff;border-radius:12px;border:1px solid #e7e5e4;padding:2.5rem 2rem;width:100%;max-width:460px;box-shadow:0 1px 4px rgba(0,0,0,.06);}

/* STEPS */
.steps{display:flex;align-items:center;margin-bottom:2rem;gap:0;}
.step-dot{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;}
.step-dot.active{background:#1c1917;color:#fff;}
.step-dot.done{background:#16a34a;color:#fff;}
.step-dot.pending{background:#f5f5f4;color:#a8a29e;border:1px solid #e7e5e4;}
.step-line{flex:1;height:2px;background:#f5f5f4;}
.step-line.done{background:#16a34a;}
.step-label{font-size:11px;color:#78716c;font-weight:500;margin-bottom:1.75rem;}

/* HEADER */
.page-icon{width:40px;height:40px;border-radius:8px;background:#f5f5f4;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:1rem;}
h1{font-size:20px;font-weight:700;color:#1c1917;margin-bottom:6px;}
.sub{font-size:13px;color:#78716c;margin-bottom:1.5rem;line-height:1.6;}

/* ALERT */
.alert-err{background:#fef2f2;border:1px solid #fecaca;border-left:3px solid #dc2626;border-radius:8px;padding:10px 12px;font-size:13px;color:#dc2626;margin-bottom:1.25rem;display:flex;align-items:center;gap:6px;}

/* BMI HINT */
.bmi-hint{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;margin-bottom:1.25rem;font-size:13px;color:#15803d;display:none;align-items:center;gap:8px;}
.bmi-hint strong{font-size:16px;font-weight:700;}

/* FIELDS */
.row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:1.25rem;}
.field label{display:block;font-size:11px;font-weight:600;color:#57534e;text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px;}
.f-wrap{position:relative;}
.f-icon{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#a8a29e;font-size:13px;pointer-events:none;}
.f-input{width:100%;border:1px solid #e7e5e4;border-radius:8px;padding:9px 36px 9px 32px;font-family:'DM Sans',sans-serif;font-size:13px;color:#1c1917;background:#fafaf9;outline:none;transition:border-color .15s;}
.f-input:focus{border-color:#1c1917;background:#fff;}
.f-input::placeholder{color:#a8a29e;}
.f-suffix{position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:11px;font-weight:600;color:#a8a29e;}

/* BUTTON */
.btn{width:100%;background:#1c1917;color:#fafaf9;border:none;border-radius:8px;padding:11px;font-family:'DM Sans',sans-serif;font-weight:600;font-size:14px;cursor:pointer;transition:background .15s;display:flex;align-items:center;justify-content:center;gap:6px;}
.btn:hover{background:#292524;}
</style>
</head>
<body>
<div class="card">

  <!-- STEPS -->
  <div class="steps">
    <div class="step-dot active">1</div>
    <div class="step-line"></div>
    <div class="step-dot pending">2</div>
    <div class="step-line"></div>
    <div class="step-dot pending">3</div>
    <div class="step-line"></div>
    <div class="step-dot pending">4</div>
    <div class="step-line"></div>
    <div class="step-dot pending">5</div>
    <div class="step-line"></div>
    <div class="step-dot pending">6</div>
  </div>
  <div class="step-label">Step 1 of 6 — Body Measurements</div>

  <div class="page-icon"><i class="bi bi-person-fill"></i></div>
  <h1>Your Body Measurements</h1>
  <p class="sub">We need your details to calculate your personalized calorie targets and workout intensity.</p>

  <?php if ($error): ?>
  <div class="alert-err"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="bmi-hint" id="bmiHint">
    <i class="bi bi-activity"></i>
    <div>BMI: <strong id="bmiVal">—</strong> &nbsp;<span id="bmiCat"></span></div>
  </div>

  <form method="POST">
    <div class="row-3">
      <div class="field">
        <label>Height</label>
        <div class="f-wrap">
          <i class="bi bi-rulers f-icon"></i>
          <input type="number" class="f-input" id="height" name="height" placeholder="170" min="50" max="300" step="0.1" value="<?= htmlspecialchars($_POST['height'] ?? '') ?>" required>
          <span class="f-suffix">cm</span>
        </div>
      </div>
      <div class="field">
        <label>Weight</label>
        <div class="f-wrap">
          <i class="bi bi-speedometer f-icon"></i>
          <input type="number" class="f-input" id="weight" name="weight" placeholder="65" min="20" max="500" step="0.1" value="<?= htmlspecialchars($_POST['weight'] ?? '') ?>" required>
          <span class="f-suffix">kg</span>
        </div>
      </div>
      <div class="field">
        <label>Age</label>
        <div class="f-wrap">
          <i class="bi bi-calendar-event f-icon"></i>
          <input type="number" class="f-input" id="age" name="age" placeholder="25" min="10" max="100" value="<?= htmlspecialchars($_POST['age'] ?? '') ?>" required>
          <span class="f-suffix">yrs</span>
        </div>
      </div>
    </div>
    <button type="submit" class="btn">Continue <i class="bi bi-arrow-right"></i></button>
  </form>
</div>

<script>
var h = document.getElementById('height');
var w = document.getElementById('weight');
var hint = document.getElementById('bmiHint');

function calcBMI() {
  var hv = parseFloat(h.value), wv = parseFloat(w.value);
  if (!hv || !wv || hv < 50 || wv < 20) { hint.style.display = 'none'; return; }
  var bmi = (wv / ((hv/100) * (hv/100))).toFixed(1);
  var cat = bmi < 18.5 ? 'Underweight' : bmi < 25 ? 'Normal' : bmi < 30 ? 'Overweight' : 'Obese';
  document.getElementById('bmiVal').textContent = bmi;
  document.getElementById('bmiCat').textContent = '(' + cat + ')';
  hint.style.display = 'flex';
}

h.addEventListener('input', calcBMI);
w.addEventListener('input', calcBMI);
</script>
</body>
</html>