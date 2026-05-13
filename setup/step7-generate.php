<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();
if (empty($_SESSION['setup_days'])) { header('Location: step6-weekly.php'); exit; }

$goal     = $_SESSION['setup_goal']     ?? 'maintain';
$level    = $_SESSION['setup_level']    ?? 'beginner';
$days     = $_SESSION['setup_days']     ?? 3;
$activity = $_SESSION['setup_activity'] ?? 'sedentary';
$calories = $_SESSION['setup_calories'] ?? 2000;
$protein  = $_SESSION['setup_protein']  ?? 150;
$name     = $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'there';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Generating Your Plan — MyFitCal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:#1c1917;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;}

.wrap{text-align:center;max-width:420px;width:100%;margin:0 auto;}

/* SPINNER */
.spinner-wrap{position:relative;width:80px;height:80px;margin:0 auto 1.75rem;}
.spinner{width:80px;height:80px;border-radius:50%;border:2px solid rgba(255,255,255,.08);border-top-color:#16a34a;border-right-color:#16a34a;animation:spin 1s linear infinite;position:absolute;top:0;left:0;}
@keyframes spin{to{transform:rotate(360deg);}}
.spinner-icon{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:52px;height:52px;border-radius:50%;background:rgba(22,163,74,.12);display:flex;align-items:center;justify-content:center;font-size:20px;color:#16a34a;}

.gen-title{font-size:22px;font-weight:700;color:#fafaf9;margin-bottom:6px;}
.gen-sub{font-size:13px;color:#78716c;margin-bottom:1.75rem;line-height:1.6;}

/* STEPS LIST */
.steps-list{text-align:left;margin-bottom:1.75rem;}
.gen-step{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;margin-bottom:5px;background:rgba(255,255,255,.03);transition:all .3s;}
.gen-step.active{background:rgba(22,163,74,.1);}
.gen-step.done{background:rgba(22,163,74,.06);}
.gs-icon{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;background:rgba(255,255,255,.06);color:rgba(255,255,255,.25);transition:all .3s;}
.gen-step.active .gs-icon{background:#16a34a;color:#fff;}
.gen-step.done .gs-icon{background:#16a34a;color:#fff;}
.gs-label{font-size:12px;font-weight:500;color:rgba(255,255,255,.25);transition:color .3s;}
.gen-step.active .gs-label,.gen-step.done .gs-label{color:rgba(255,255,255,.75);}

/* SUMMARY */
.summary{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:16px;display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:1.5rem;opacity:0;transition:opacity .5s;}
.sum-item{text-align:center;}
.sum-val{font-size:18px;font-weight:700;color:#16a34a;line-height:1;}
.sum-label{font-size:10px;color:#78716c;text-transform:uppercase;letter-spacing:.5px;margin-top:3px;}

/* BTN */
.btn-start{display:none;width:100%;background:#16a34a;color:#fff;border:none;border-radius:8px;padding:12px;font-family:'DM Sans',sans-serif;font-weight:700;font-size:14px;cursor:pointer;transition:background .15s;display:none;align-items:center;justify-content:center;gap:6px;}
.btn-start:hover{background:#15803d;}
</style>
</head>
<body>
<div class="wrap">

  <div class="spinner-wrap">
    <div class="spinner" id="spinner"></div>
    <div class="spinner-icon" id="spinnerIcon"><i class="bi bi-gear-fill"></i></div>
  </div>

  <div class="gen-title" id="genTitle">Generating Your Plan</div>
  <div class="gen-sub">Building your personalized <?= ucfirst($level) ?> program, <?= htmlspecialchars($name) ?>...</div>

  <div class="steps-list">
    <div class="gen-step" id="gs1"><div class="gs-icon"><i class="bi bi-person-fill"></i></div><span class="gs-label">Analyzing your profile & goals</span></div>
    <div class="gen-step" id="gs2"><div class="gs-icon"><i class="bi bi-calculator"></i></div><span class="gs-label">Calculating nutrition targets</span></div>
    <div class="gen-step" id="gs3"><div class="gs-icon"><i class="bi bi-clipboard-pulse"></i></div><span class="gs-label">Selecting <?= ucfirst($level) ?> exercises</span></div>
    <div class="gen-step" id="gs4"><div class="gs-icon"><i class="bi bi-calendar-week"></i></div><span class="gs-label">Building your <?= $days ?>-day weekly schedule</span></div>
    <div class="gen-step" id="gs5"><div class="gs-icon"><i class="bi bi-check-circle-fill"></i></div><span class="gs-label">Finalizing your 30-day plan</span></div>
  </div>

  <div class="summary" id="summary">
    <div class="sum-item"><div class="sum-val"><?= number_format($calories) ?></div><div class="sum-label">kcal / day</div></div>
    <div class="sum-item"><div class="sum-val"><?= $protein ?>g</div><div class="sum-label">protein / day</div></div>
    <div class="sum-item"><div class="sum-val"><?= $days ?> days</div><div class="sum-label">per week</div></div>
    <div class="sum-item"><div class="sum-val">30 days</div><div class="sum-label">program</div></div>
  </div>

  <button class="btn-start" id="btnStart" onclick="window.location='/myfitcal_system/user/dashboard.php'">
    <i class="bi bi-lightning-charge-fill"></i> Start Your First Workout
  </button>
</div>

<script>
var steps = ['gs1','gs2','gs3','gs4','gs5'];
var delays = [300,1000,1800,2500,3200];
var doneAt = [900,1700,2400,3100,3800];

steps.forEach(function(id,i) {
  setTimeout(function(){ document.getElementById(id).className='gen-step active'; }, delays[i]);
  setTimeout(function(){
    document.getElementById(id).className='gen-step done';
    document.getElementById(id).querySelector('.gs-icon').innerHTML='<i class="bi bi-check-lg"></i>';
  }, doneAt[i]);
});

setTimeout(function() {
  document.getElementById('summary').style.opacity = '1';
  document.getElementById('genTitle').textContent = 'Your Plan is Ready!';
  document.getElementById('spinner').style.borderTopColor = '#16a34a';
  document.getElementById('spinner').style.borderRightColor = '#16a34a';
  document.getElementById('spinner').style.animation = 'none';
  document.getElementById('spinner').style.border = '2px solid #16a34a';
  document.getElementById('spinnerIcon').innerHTML = '<i class="bi bi-check-lg"></i>';
  var btn = document.getElementById('btnStart');
  btn.style.display = 'flex';
}, 4200);
</script>
</body>
</html>