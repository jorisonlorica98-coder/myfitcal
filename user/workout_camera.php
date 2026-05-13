<?php
session_start();
// Kunin ang exercise name mula sa query string o POST
// Gamitin: workout-camera.php?exercise=Push-Up&sets=4&reps=15
$exercise    = htmlspecialchars($_GET['exercise'] ?? 'Push-Up');
$target_sets = (int)($_GET['sets'] ?? 3);
$target_reps = (int)($_GET['reps'] ?? 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Camera Tracker — <?= $exercise ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --primary: #142c5b;
  --primary-alt: #0a1f44;
  --accent: #3b82f6;
  --green: #22c55e;
  --red: #ef4444;
  --orange: #f97316;
  --bg: #0f172a;
  --surface: #1e293b;
  --surface2: #334155;
  --text: #f1f5f9;
  --muted: #94a3b8;
}

body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
}

/* ── TOP BAR ── */
.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  background: var(--surface);
  border-bottom: 1px solid var(--surface2);
  position: sticky; top: 0; z-index: 100;
}
.topbar-left { display: flex; align-items: center; gap: .75rem; }
.back-btn {
  width: 36px; height: 36px;
  background: var(--surface2); border: none; border-radius: 10px;
  color: var(--text); font-size: 1rem; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: background .2s;
}
.back-btn:hover { background: #475569; }
.exercise-name { font-size: 1.1rem; font-weight: 800; }
.exercise-meta { font-size: .8rem; color: var(--muted); margin-top: 2px; }

.status-pill {
  padding: 6px 14px; border-radius: 999px;
  font-size: .8rem; font-weight: 700;
  display: flex; align-items: center; gap: 6px;
}
.status-pill.ready    { background: rgba(59,130,246,.15); color: #60a5fa; border: 1px solid rgba(59,130,246,.3); }
.status-pill.active   { background: rgba(34,197,94,.15);  color: #4ade80; border: 1px solid rgba(34,197,94,.3); }
.status-pill.rest     { background: rgba(249,115,22,.15); color: #fb923c; border: 1px solid rgba(249,115,22,.3); }
.status-pill.done     { background: rgba(34,197,94,.2);   color: #4ade80; border: 1px solid rgba(34,197,94,.4); }
.status-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: currentColor;
  animation: pulse 1.5s ease-in-out infinite;
}
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

/* ── MAIN LAYOUT ── */
.main-grid {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 1.25rem;
  padding: 1.25rem 1.5rem;
  max-width: 1400px;
  margin: 0 auto;
}
@media (max-width: 900px) {
  .main-grid { grid-template-columns: 1fr; }
}

/* ── CAMERA PANEL ── */
.camera-panel {
  position: relative;
  background: #000;
  border-radius: 20px;
  overflow: hidden;
  aspect-ratio: 4/3;
  border: 2px solid var(--surface2);
}
#videoEl {
  width: 100%; height: 100%;
  object-fit: cover;
  transform: scaleX(-1); /* mirror */
  display: block;
}
#canvasEl {
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  transform: scaleX(-1);
  pointer-events: none;
}

.camera-overlay-msg {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: rgba(15,23,42,.85);
  backdrop-filter: blur(4px);
  gap: 1rem;
  text-align: center;
  padding: 2rem;
}
.camera-overlay-msg i { font-size: 3rem; color: var(--accent); }
.camera-overlay-msg h2 { font-size: 1.3rem; font-weight: 800; }
.camera-overlay-msg p  { font-size: .9rem; color: var(--muted); line-height: 1.6; }

/* Big rep flash */
.rep-flash {
  position: absolute;
  top: 50%; left: 50%;
  transform: translate(-50%,-50%) scale(0);
  font-size: 6rem;
  font-weight: 900;
  color: #4ade80;
  text-shadow: 0 0 40px rgba(74,222,128,.6);
  pointer-events: none;
  transition: transform .1s, opacity .3s;
  opacity: 0;
  z-index: 10;
}
.rep-flash.show {
  transform: translate(-50%,-50%) scale(1);
  opacity: 1;
}

/* Form guide bar */
.form-guide {
  position: absolute;
  bottom: 0; left: 0; right: 0;
  padding: .75rem 1rem;
  background: linear-gradient(transparent, rgba(0,0,0,.8));
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.form-guide-text {
  font-size: .8rem;
  font-weight: 600;
  color: #fff;
}
.confidence-bar {
  display: flex; align-items: center; gap: 8px;
  font-size: .75rem; color: var(--muted);
}
.conf-track {
  width: 80px; height: 5px;
  background: var(--surface2);
  border-radius: 999px; overflow: hidden;
}
.conf-fill {
  height: 100%;
  background: var(--green);
  border-radius: 999px;
  transition: width .3s;
  width: 0%;
}

/* ── SIDE PANEL ── */
.side-panel { display: flex; flex-direction: column; gap: 1rem; }

/* Stats cards */
.stats-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .75rem;
}
.stat-card {
  background: var(--surface);
  border: 1px solid var(--surface2);
  border-radius: 16px;
  padding: 1rem 1.25rem;
}
.stat-label {
  font-size: .72rem;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .06em;
  margin-bottom: .3rem;
}
.stat-value {
  font-size: 2rem;
  font-weight: 900;
  line-height: 1;
}
.stat-sub {
  font-size: .72rem;
  color: var(--muted);
  margin-top: .2rem;
}
.stat-value.green  { color: #4ade80; }
.stat-value.blue   { color: #60a5fa; }
.stat-value.orange { color: #fb923c; }
.stat-value.white  { color: #f1f5f9; }

/* Set tracker */
.set-tracker {
  background: var(--surface);
  border: 1px solid var(--surface2);
  border-radius: 16px;
  padding: 1.25rem;
}
.set-tracker-title {
  font-size: .85rem;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .06em;
  margin-bottom: 1rem;
}
.sets-row {
  display: flex;
  gap: .6rem;
  flex-wrap: wrap;
}
.set-bubble {
  width: 44px; height: 44px;
  border-radius: 50%;
  border: 2px solid var(--surface2);
  display: flex; align-items: center; justify-content: center;
  font-size: .8rem; font-weight: 800;
  color: var(--muted);
  transition: all .3s;
  position: relative;
}
.set-bubble.active {
  border-color: var(--accent);
  color: #60a5fa;
  box-shadow: 0 0 12px rgba(59,130,246,.3);
}
.set-bubble.done {
  background: rgba(34,197,94,.15);
  border-color: var(--green);
  color: #4ade80;
}
.set-bubble .set-rep-count {
  position: absolute;
  bottom: -18px;
  font-size: .65rem;
  color: var(--muted);
  white-space: nowrap;
}

/* Rep progress ring */
.ring-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  background: var(--surface);
  border: 1px solid var(--surface2);
  border-radius: 16px;
  padding: 1.5rem 1.25rem 1.25rem;
}
.ring-wrap-title {
  font-size: .85rem; font-weight: 700;
  color: var(--muted); text-transform: uppercase;
  letter-spacing: .06em; margin-bottom: 1rem;
}
.ring-container { position: relative; width: 130px; height: 130px; }
.ring-container svg { transform: rotate(-90deg); }
.ring-bg   { fill: none; stroke: var(--surface2); stroke-width: 10; }
.ring-fill { fill: none; stroke: #4ade80; stroke-width: 10;
             stroke-linecap: round;
             transition: stroke-dashoffset .4s ease;
             stroke-dasharray: 345; stroke-dashoffset: 345; }
.ring-center {
  position: absolute; inset: 0;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 0;
}
.ring-reps  { font-size: 2.5rem; font-weight: 900; line-height: 1; color: #4ade80; }
.ring-total { font-size: .85rem; color: var(--muted); }

/* Phase indicator */
.phase-bar {
  width: 100%; height: 6px;
  background: var(--surface2);
  border-radius: 999px; overflow: hidden;
  margin-top: 1rem;
}
.phase-fill {
  height: 100%; border-radius: 999px;
  background: #4ade80;
  transition: width .2s, background .3s;
  width: 50%;
}

/* Controls */
.controls {
  display: flex;
  flex-direction: column;
  gap: .6rem;
}
.btn {
  padding: .85rem;
  border: none; border-radius: 14px;
  font-family: inherit; font-size: .95rem; font-weight: 700;
  cursor: pointer; display: flex; align-items: center;
  justify-content: center; gap: 8px;
  transition: all .2s;
}
.btn-primary {
  background: linear-gradient(135deg, var(--primary-alt), var(--primary));
  color: #fff;
  box-shadow: 0 6px 20px rgba(10,31,68,.4);
}
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(10,31,68,.5); }
.btn-green  { background: rgba(34,197,94,.15); color: #4ade80; border: 1px solid rgba(34,197,94,.3); }
.btn-green:hover  { background: rgba(34,197,94,.25); }
.btn-orange { background: rgba(249,115,22,.15); color: #fb923c; border: 1px solid rgba(249,115,22,.3); }
.btn-orange:hover { background: rgba(249,115,22,.25); }
.btn-red    { background: rgba(239,68,68,.15);  color: #f87171; border: 1px solid rgba(239,68,68,.3); }
.btn-red:hover    { background: rgba(239,68,68,.25); }
.btn:disabled { opacity: .4; cursor: not-allowed; transform: none !important; }

/* Rest timer overlay */
.rest-overlay {
  position: fixed; inset: 0; z-index: 200;
  background: rgba(15,23,42,.92);
  backdrop-filter: blur(6px);
  display: none;
  flex-direction: column;
  align-items: center; justify-content: center;
  gap: 1rem;
  text-align: center;
}
.rest-overlay.show { display: flex; }
.rest-timer-ring { position: relative; width: 180px; height: 180px; }
.rest-timer-ring svg { transform: rotate(-90deg); }
.rest-ring-bg   { fill:none; stroke: var(--surface2); stroke-width: 12; }
.rest-ring-fill { fill:none; stroke: #fb923c; stroke-width: 12;
                  stroke-linecap: round;
                  stroke-dasharray: 502; stroke-dashoffset: 0;
                  transition: stroke-dashoffset 1s linear; }
.rest-center {
  position: absolute; inset: 0;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
}
.rest-secs  { font-size: 3.5rem; font-weight: 900; color: #fb923c; line-height: 1; }
.rest-label { font-size: .85rem; color: var(--muted); }
.rest-title { font-size: 1.5rem; font-weight: 900; }
.rest-sub   { font-size: .95rem; color: var(--muted); }

/* Workout done screen */
.done-overlay {
  position: fixed; inset: 0; z-index: 200;
  background: rgba(15,23,42,.95);
  backdrop-filter: blur(8px);
  display: none;
  flex-direction: column;
  align-items: center; justify-content: center;
  gap: 1.25rem; text-align: center; padding: 2rem;
}
.done-overlay.show { display: flex; }
.done-icon { font-size: 4rem; }
.done-title { font-size: 2rem; font-weight: 900; }
.done-sub   { font-size: 1rem; color: var(--muted); line-height: 1.7; }
.done-stats {
  display: flex; gap: 2rem; margin-top: .5rem;
}
.done-stat { text-align: center; }
.done-stat-val { font-size: 2rem; font-weight: 900; color: #4ade80; }
.done-stat-lbl { font-size: .8rem; color: var(--muted); margin-top: 2px; }

/* Pose guide tips */
.tips-card {
  background: var(--surface);
  border: 1px solid var(--surface2);
  border-radius: 16px;
  padding: 1.25rem;
}
.tips-title { font-size: .85rem; font-weight: 700; color: var(--muted);
              text-transform: uppercase; letter-spacing: .06em; margin-bottom: .75rem; }
.tip-item {
  display: flex; align-items: flex-start; gap: 8px;
  font-size: .82rem; color: var(--muted); line-height: 1.5;
  margin-bottom: .5rem;
}
.tip-item i { color: var(--accent); font-size: .85rem; margin-top: 2px; flex-shrink: 0; }
</style>
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
  <div class="topbar-left">
    <button class="back-btn" onclick="history.back()">
      <i class="bi bi-arrow-left"></i>
    </button>
    <div>
      <div class="exercise-name" id="exerciseTitle"><?= $exercise ?></div>
      <div class="exercise-meta"><?= $target_sets ?> sets &times; <?= $target_reps ?> reps &nbsp;&middot;&nbsp; Camera Tracking</div>
    </div>
  </div>
  <div class="status-pill ready" id="statusPill">
    <span class="status-dot"></span>
    <span id="statusText">Ready</span>
  </div>
</div>

<!-- MAIN GRID -->
<div class="main-grid">

  <!-- LEFT: CAMERA -->
  <div>
    <div class="camera-panel">
      <video id="videoEl" autoplay playsinline muted></video>
      <canvas id="canvasEl"></canvas>

      <!-- Rep flash -->
      <div class="rep-flash" id="repFlash">+1</div>

      <!-- Initial overlay -->
      <div class="camera-overlay-msg" id="cameraOverlay">
        <i class="bi bi-camera-video"></i>
        <h2>Start Camera</h2>
        <p>I-click ang "Start Camera" para simulan ang<br>pose detection at rep counting.</p>
      </div>

      <!-- Bottom form guide -->
      <div class="form-guide">
        <span class="form-guide-text" id="poseGuideText">—</span>
        <div class="confidence-bar">
          <span>Pose</span>
          <div class="conf-track"><div class="conf-fill" id="confFill"></div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT: SIDE PANEL -->
  <div class="side-panel">

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Reps Done</div>
        <div class="stat-value green" id="statReps">0</div>
        <div class="stat-sub">of <span id="statTargetReps"><?= $target_reps ?></span> target</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Current Set</div>
        <div class="stat-value blue" id="statSet">1</div>
        <div class="stat-sub">of <?= $target_sets ?> sets</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Reps</div>
        <div class="stat-value white" id="statTotal">0</div>
        <div class="stat-sub">all sets</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Time</div>
        <div class="stat-value orange" id="statTime">0:00</div>
        <div class="stat-sub">elapsed</div>
      </div>
    </div>

    <!-- Rep Ring -->
    <div class="ring-wrap">
      <div class="ring-wrap-title">Rep Progress</div>
      <div class="ring-container">
        <svg width="130" height="130" viewBox="0 0 130 130">
          <circle class="ring-bg"   cx="65" cy="65" r="55"/>
          <circle class="ring-fill" id="ringFill" cx="65" cy="65" r="55"/>
        </svg>
        <div class="ring-center">
          <div class="ring-reps"  id="ringReps">0</div>
          <div class="ring-total">/ <span id="ringTotal"><?= $target_reps ?></span></div>
        </div>
      </div>
      <!-- Phase bar: shows down/up position -->
      <div style="width:100%;margin-top:1rem;">
        <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--muted);margin-bottom:4px;">
          <span>DOWN</span><span id="phaseLabel">—</span><span>UP</span>
        </div>
        <div class="phase-bar">
          <div class="phase-fill" id="phaseFill"></div>
        </div>
      </div>
    </div>

    <!-- Set Tracker -->
    <div class="set-tracker">
      <div class="set-tracker-title">Sets</div>
      <div class="sets-row" id="setsRow">
        <?php for ($i = 1; $i <= $target_sets; $i++): ?>
        <div class="set-bubble <?= $i === 1 ? 'active' : '' ?>" id="setBubble<?= $i ?>">
          <?= $i ?>
          <span class="set-rep-count" id="setRepCount<?= $i ?>">0 reps</span>
        </div>
        <?php endfor; ?>
      </div>
    </div>

    <!-- Controls -->
    <div class="controls">
      <button class="btn btn-primary" id="btnCamera" onclick="startCamera()">
        <i class="bi bi-camera-video-fill"></i> Start Camera
      </button>
      <button class="btn btn-green" id="btnStart" onclick="startTracking()" disabled>
        <i class="bi bi-play-fill"></i> Start Tracking
      </button>
      <button class="btn btn-orange" id="btnNextSet" onclick="nextSet()" disabled style="display:none">
        <i class="bi bi-skip-forward-fill"></i> Next Set
      </button>
      <button class="btn btn-red" id="btnStop" onclick="stopTracking()" disabled>
        <i class="bi bi-stop-fill"></i> Stop
      </button>
    </div>

    <!-- Tips -->
    <div class="tips-card" id="tipsCard">
      <div class="tips-title"><i class="bi bi-lightbulb"></i> &nbsp;Form Tips</div>
      <div id="tipsList"></div>
    </div>

  </div>
</div>

<!-- REST OVERLAY -->
<div class="rest-overlay" id="restOverlay">
  <div class="rest-title">💪 Set Complete!</div>
  <div class="rest-timer-ring">
    <svg width="180" height="180" viewBox="0 0 180 180">
      <circle class="rest-ring-bg"   cx="90" cy="90" r="80"/>
      <circle class="rest-ring-fill" id="restRingFill" cx="90" cy="90" r="80"/>
    </svg>
    <div class="rest-center">
      <div class="rest-secs"  id="restSecs">60</div>
      <div class="rest-label">REST</div>
    </div>
  </div>
  <div class="rest-sub">Ihanda ang sarili mo para sa susunod na set.</div>
  <button class="btn btn-primary" style="padding:.85rem 2.5rem;margin-top:.5rem;" onclick="skipRest()">
    <i class="bi bi-skip-forward-fill"></i> Skip Rest
  </button>
</div>

<!-- DONE OVERLAY -->
<div class="done-overlay" id="doneOverlay">
  <div class="done-icon">🏆</div>
  <div class="done-title">Workout Complete!</div>
  <div class="done-sub">
    Magaling! Natapos mo ang <strong id="doneExercise"><?= $exercise ?></strong>.<br>
    Lahat ng sets ay matagumpay na nakumpleto.
  </div>
  <div class="done-stats">
    <div class="done-stat">
      <div class="done-stat-val" id="doneTotalReps">0</div>
      <div class="done-stat-lbl">Total Reps</div>
    </div>
    <div class="done-stat">
      <div class="done-stat-val" id="doneSets"><?= $target_sets ?></div>
      <div class="done-stat-lbl">Sets Done</div>
    </div>
    <div class="done-stat">
      <div class="done-stat-val" id="doneTime">0:00</div>
      <div class="done-stat-lbl">Time</div>
    </div>
  </div>
  <div style="display:flex;gap:.75rem;margin-top:.5rem;flex-wrap:wrap;justify-content:center;">
    <button class="btn btn-primary" style="padding:.85rem 2rem;" onclick="location.reload()">
      <i class="bi bi-arrow-repeat"></i> Do Again
    </button>
    <button class="btn btn-green" style="padding:.85rem 2rem;" onclick="history.back()">
      <i class="bi bi-check-lg"></i> Back to Workout
    </button>
  </div>
</div>

<!-- TensorFlow.js + MoveNet -->
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.17.0/dist/tf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/pose-detection@2.1.3/dist/pose-detection.min.js"></script>

<script>
// ── CONFIG ────────────────────────────────────────────────────────
const EXERCISE     = <?= json_encode($exercise) ?>;
const TARGET_SETS  = <?= $target_sets ?>;
const TARGET_REPS  = <?= $target_reps ?>;
const REST_SECS    = 60;

// Exercise detection config — maps exercise name keywords to detection method + tips
const EXERCISE_CONFIG = {
  'push': {
    type: 'pushup',
    tips: [
      'Ipakita ang buong katawan sa camera (side view)',
      'Panatilihing tuwid ang likod mo',
      'Ibaba ang dibdib hanggang malapit sa sahig',
    ]
  },
  'squat': {
    type: 'squat',
    tips: [
      'Ibaba ang hips hanggang parallel sa knees',
      'Itayo ka nang tuwid para mabilang ang rep',
      'Side view ang pinakamahusay para sa detection',
    ]
  },
  'lunge': {
    type: 'squat',
    tips: [
      'Ihiwalay ang mga paa — isang paa sa harap',
      'Ibaba ang likod na tuhod pababa',
      'Tuwid ang katawan, huwag sumandal',
    ]
  },
  'burpee': {
    type: 'burpee',
    tips: [
      'Simulan ang nakatayo, tapos lumuhod at humiga',
      'Tumayo nang buo at tumalon sa dulo',
      'Facing ang camera para mas magandang detection',
    ]
  },
  'plank': {
    type: 'plank',
    tips: [
      'Panatilihing tuwid ang buong katawan',
      'Side view ang dapat makita ng camera',
      'Ang plank mode ay nagco-count ng seconds',
    ]
  },
  'sit': {
    type: 'situp',
    tips: [
      'Humiga at itaas ang ulo at balikat',
      'Top-down o side view ng camera',
      'Baluktot ang tuhod sa sahig',
    ]
  },
  'crunch': {
    type: 'situp',
    tips: [
      'Humiga at itaas ang ulo at balikat',
      'Hindi kailangang umabot sa tuhod — crunch lang',
      'Overhead view o side view',
    ]
  },
  'curl': {
    type: 'curl',
    tips: [
      'Ipakita ang buong braso sa camera',
      'Itaas ang kamay hanggang balikat level',
      'Side view ang pinakamainam',
    ]
  },
  'press': {
    type: 'press',
    tips: [
      'Itaas ang mga kamay nang buo sa ibabaw ng ulo',
      'Ibaba sa balikat level para mabilang',
      'Facing ang camera',
    ]
  },
  'default': {
    type: 'generic',
    tips: [
      'Tiyaking makikita ng camera ang buong katawan',
      'Maliwanag ang lugar at walang harang',
      'Side view ang pinaka-ideal para sa karamihan ng exercises',
    ]
  }
};

function getExerciseConfig() {
  const name = EXERCISE.toLowerCase();
  for (const key in EXERCISE_CONFIG) {
    if (key !== 'default' && name.includes(key)) return EXERCISE_CONFIG[key];
  }
  return EXERCISE_CONFIG['default'];
}

const exConfig = getExerciseConfig();

// ── STATE ─────────────────────────────────────────────────────────
let detector      = null;
let videoEl       = document.getElementById('videoEl');
let canvasEl      = document.getElementById('canvasEl');
let ctx           = canvasEl.getContext('2d');
let isTracking    = false;
let cameraReady   = false;
let currentSet    = 1;
let currentReps   = 0;
let totalReps     = 0;
let setRepsLog    = {};
let phase         = 'up';   // up | down
let phaseProgress = 0.5;    // 0=down 1=up
let startTime     = null;
let timerInterval = null;
let animFrame     = null;
let lastPoseScore = 0;

// Rep counting smoothing
let angleHistory  = [];
const HISTORY_LEN = 5;

// ── TIPS ──────────────────────────────────────────────────────────
(function buildTips() {
  const list = document.getElementById('tipsList');
  exConfig.tips.forEach(t => {
    list.innerHTML += `<div class="tip-item"><i class="bi bi-info-circle-fill"></i>${t}</div>`;
  });
})();

// ── CAMERA ───────────────────────────────────────────────────────
async function startCamera() {
  const btn = document.getElementById('btnCamera');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Loading...';

  try {
    const stream = await navigator.mediaDevices.getUserMedia({
      video: { width: 640, height: 480, facingMode: 'user' },
      audio: false
    });
    videoEl.srcObject = stream;
    await new Promise(r => videoEl.onloadedmetadata = r);
    await videoEl.play();

    canvasEl.width  = videoEl.videoWidth;
    canvasEl.height = videoEl.videoHeight;

    // Load MoveNet
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Loading AI Model...';
    detector = await poseDetection.createDetector(
      poseDetection.SupportedModels.MoveNet,
      { modelType: poseDetection.movenet.modelType.SINGLEPOSE_LIGHTNING }
    );

    cameraReady = true;
    document.getElementById('cameraOverlay').style.display = 'none';
    document.getElementById('btnStart').disabled = false;
    btn.innerHTML = '<i class="bi bi-camera-video-fill"></i> Camera On';
    btn.style.background = 'rgba(34,197,94,.15)';
    btn.style.color = '#4ade80';
    btn.style.border = '1px solid rgba(34,197,94,.3)';

    setStatus('ready', 'Camera Ready');
    // Start rendering skeleton even before tracking
    renderLoop();

  } catch (e) {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-camera-video-fill"></i> Start Camera';
    alert('Hindi ma-access ang camera. Pakibigyan ng permission ang browser.');
    console.error(e);
  }
}

// ── RENDER LOOP ───────────────────────────────────────────────────
async function renderLoop() {
  if (!cameraReady) return;

  ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);

  try {
    const poses = await detector.estimatePoses(videoEl);
    if (poses && poses.length > 0) {
      const pose = poses[0];
      drawSkeleton(pose);
      lastPoseScore = pose.score ?? 0;

      // Update confidence bar
      document.getElementById('confFill').style.width = (lastPoseScore * 100) + '%';
      document.getElementById('confFill').style.background =
        lastPoseScore > 0.4 ? '#22c55e' : lastPoseScore > 0.25 ? '#f97316' : '#ef4444';

      if (isTracking) {
        countRep(pose);
      }
    }
  } catch(e) {}

  animFrame = requestAnimationFrame(renderLoop);
}

// ── SKELETON DRAWING ──────────────────────────────────────────────
const CONNECTIONS = [
  [5,6],[5,7],[7,9],[6,8],[8,10],
  [5,11],[6,12],[11,12],
  [11,13],[13,15],[12,14],[14,16]
];
const KP_NAMES = [
  'nose','left_eye','right_eye','left_ear','right_ear',
  'left_shoulder','right_shoulder','left_elbow','right_elbow',
  'left_wrist','right_wrist','left_hip','right_hip',
  'left_knee','right_knee','left_ankle','right_ankle'
];

function drawSkeleton(pose) {
  const kps = pose.keypoints;
  const W = canvasEl.width, H = canvasEl.height;

  // Lines
  ctx.lineWidth = 3;
  CONNECTIONS.forEach(([a, b]) => {
    const kpA = kps[a], kpB = kps[b];
    if (kpA.score > 0.3 && kpB.score > 0.3) {
      ctx.beginPath();
      ctx.moveTo(kpA.x, kpA.y);
      ctx.lineTo(kpB.x, kpB.y);
      ctx.strokeStyle = 'rgba(96,165,250,0.8)';
      ctx.stroke();
    }
  });

  // Points
  kps.forEach((kp, i) => {
    if (kp.score > 0.3) {
      ctx.beginPath();
      ctx.arc(kp.x, kp.y, i < 5 ? 5 : 6, 0, 2 * Math.PI);
      ctx.fillStyle = i < 5 ? '#f472b6' : '#4ade80';
      ctx.fill();
    }
  });
}

// ── ANGLE HELPER ─────────────────────────────────────────────────
function angle(a, b, c) {
  const ab = { x: a.x - b.x, y: a.y - b.y };
  const cb = { x: c.x - b.x, y: c.y - b.y };
  const dot = ab.x * cb.x + ab.y * cb.y;
  const magAB = Math.sqrt(ab.x ** 2 + ab.y ** 2);
  const magCB = Math.sqrt(cb.x ** 2 + cb.y ** 2);
  if (magAB === 0 || magCB === 0) return 180;
  const cosine = Math.max(-1, Math.min(1, dot / (magAB * magCB)));
  return Math.acos(cosine) * (180 / Math.PI);
}

function kp(pose, name) {
  return pose.keypoints.find(k => k.name === name);
}

function smoothAngle(newAngle) {
  angleHistory.push(newAngle);
  if (angleHistory.length > HISTORY_LEN) angleHistory.shift();
  return angleHistory.reduce((a, b) => a + b, 0) / angleHistory.length;
}

// ── REP COUNTING ─────────────────────────────────────────────────
function countRep(pose) {
  const type = exConfig.type;
  let mainAngle = null;
  let guideText = '';
  let downThresh = 90, upThresh = 160;

  if (type === 'pushup') {
    // Elbow angle — uses left or right whichever is visible
    const ls = kp(pose,'left_shoulder'),  le = kp(pose,'left_elbow'),  lw = kp(pose,'left_wrist');
    const rs = kp(pose,'right_shoulder'), re = kp(pose,'right_elbow'), rw = kp(pose,'right_wrist');
    const lScore = Math.min(ls?.score??0, le?.score??0, lw?.score??0);
    const rScore = Math.min(rs?.score??0, re?.score??0, rw?.score??0);
    if (lScore > 0.3 || rScore > 0.3) {
      const use = lScore >= rScore ? [ls,le,lw] : [rs,re,rw];
      mainAngle = angle(use[0], use[1], use[2]);
    }
    downThresh = 90; upThresh = 150;
    guideText = phase === 'up' ? 'Lower your body ↓' : 'Push up ↑';

  } else if (type === 'squat') {
    const lh = kp(pose,'left_hip'), lk = kp(pose,'left_knee'), la = kp(pose,'left_ankle');
    const rh = kp(pose,'right_hip'),rk = kp(pose,'right_knee'),ra = kp(pose,'right_ankle');
    const lScore = Math.min(lh?.score??0, lk?.score??0, la?.score??0);
    const rScore = Math.min(rh?.score??0, rk?.score??0, ra?.score??0);
    if (lScore > 0.3 || rScore > 0.3) {
      const use = lScore >= rScore ? [lh,lk,la] : [rh,rk,ra];
      mainAngle = angle(use[0], use[1], use[2]);
    }
    downThresh = 100; upThresh = 160;
    guideText = phase === 'up' ? 'Lower into squat ↓' : 'Stand up ↑';

  } else if (type === 'burpee') {
    // Use hip height relative to shoulder — down = hips near ground
    const ls = kp(pose,'left_shoulder'), rs = kp(pose,'right_shoulder');
    const lh = kp(pose,'left_hip'),      rh = kp(pose,'right_hip');
    if ((ls?.score??0)>0.3 && (lh?.score??0)>0.3) {
      const shoulderY = (ls.y + rs.y) / 2;
      const hipY      = (lh.y + rh.y) / 2;
      // Use vertical distance as proxy
      mainAngle = (hipY - shoulderY) / canvasEl.height * 180;
    }
    downThresh = 30; upThresh = 60;
    guideText = phase === 'up' ? 'Drop down ↓' : 'Jump up ↑';

  } else if (type === 'situp') {
    const ls = kp(pose,'left_shoulder'), lh = kp(pose,'left_hip'), lk = kp(pose,'left_knee');
    const rs = kp(pose,'right_shoulder'),rh = kp(pose,'right_hip'),rk = kp(pose,'right_knee');
    const lScore = Math.min(ls?.score??0, lh?.score??0, lk?.score??0);
    const rScore = Math.min(rs?.score??0, rh?.score??0, rk?.score??0);
    if (lScore > 0.3 || rScore > 0.3) {
      const use = lScore >= rScore ? [ls,lh,lk] : [rs,rh,rk];
      mainAngle = angle(use[0], use[1], use[2]);
    }
    downThresh = 60; upThresh = 110;
    guideText = phase === 'up' ? 'Lie back down ↓' : 'Crunch up ↑';

  } else if (type === 'curl') {
    const ls = kp(pose,'left_shoulder'), le = kp(pose,'left_elbow'), lw = kp(pose,'left_wrist');
    const rs = kp(pose,'right_shoulder'),re = kp(pose,'right_elbow'),rw = kp(pose,'right_wrist');
    const lScore = Math.min(ls?.score??0, le?.score??0, lw?.score??0);
    const rScore = Math.min(rs?.score??0, re?.score??0, rw?.score??0);
    if (lScore > 0.3 || rScore > 0.3) {
      const use = lScore >= rScore ? [ls,le,lw] : [rs,re,rw];
      mainAngle = angle(use[0], use[1], use[2]);
    }
    downThresh = 160; upThresh = 50;
    // Curl is inverted — down = arm extended
    [downThresh, upThresh] = [upThresh, downThresh];
    guideText = phase === 'up' ? 'Lower arm ↓' : 'Curl up ↑';

  } else if (type === 'press') {
    const ls = kp(pose,'left_shoulder'), le = kp(pose,'left_elbow'), lw = kp(pose,'left_wrist');
    if ((ls?.score??0)>0.3 && (le?.score??0)>0.3 && (lw?.score??0)>0.3) {
      mainAngle = angle(ls, le, lw);
    }
    downThresh = 90; upThresh = 160;
    guideText = phase === 'up' ? 'Lower to shoulders ↓' : 'Press up ↑';

  } else if (type === 'plank') {
    // Plank: count seconds held instead of reps
    const ls = kp(pose,'left_shoulder'), lh = kp(pose,'left_hip'), la = kp(pose,'left_ankle');
    if ((ls?.score??0)>0.3 && (lh?.score??0)>0.3 && (la?.score??0)>0.3) {
      mainAngle = angle(ls, lh, la);
      // Good plank = body ~180 degrees (straight)
      const isHolding = mainAngle > 150;
      guideText = isHolding ? '✓ Good form — holding' : 'Straighten your body';
      document.getElementById('poseGuideText').textContent = guideText;
    }
    // For plank, just return — rep counting not applicable
    return;

  } else {
    // Generic: use hip angle
    const lh = kp(pose,'left_hip'), lk = kp(pose,'left_knee'), ls = kp(pose,'left_shoulder');
    if ((lh?.score??0)>0.3 && (lk?.score??0)>0.3 && (ls?.score??0)>0.3) {
      mainAngle = angle(ls, lh, lk);
    }
    downThresh = 100; upThresh = 160;
    guideText = phase === 'up' ? 'Go down ↓' : 'Come up ↑';
  }

  if (mainAngle === null) {
    document.getElementById('poseGuideText').textContent = 'Move into frame...';
    return;
  }

  const smoothed = smoothAngle(mainAngle);

  // Phase progress bar (0 = down, 1 = up)
  const normalized = Math.max(0, Math.min(1, (smoothed - downThresh) / (upThresh - downThresh)));
  phaseProgress = normalized;
  document.getElementById('phaseFill').style.width = (normalized * 100) + '%';
  document.getElementById('phaseFill').style.background = normalized > 0.5 ? '#4ade80' : '#fb923c';
  document.getElementById('phaseLabel').textContent = normalized > 0.6 ? 'UP' : normalized < 0.4 ? 'DOWN' : '...';

  // Rep logic: DOWN then UP = 1 rep
  if (phase === 'up' && smoothed < downThresh) {
    phase = 'down';
    guideText = type === 'curl' ? 'Curl up ↑' :
                type === 'situp' ? 'Come up ↑' : 'Push up ↑';
  } else if (phase === 'down' && smoothed > upThresh) {
    phase = 'up';
    registerRep();
    guideText = type === 'curl' ? 'Lower arm ↓' :
                type === 'situp' ? 'Lie back ↓' : 'Lower down ↓';
  }

  document.getElementById('poseGuideText').textContent = guideText;
}

// ── REP REGISTRATION ─────────────────────────────────────────────
function registerRep() {
  currentReps++;
  totalReps++;

  // Flash
  const flash = document.getElementById('repFlash');
  flash.textContent = currentReps;
  flash.classList.add('show');
  setTimeout(() => flash.classList.remove('show'), 600);

  // Update UI
  updateUI();

  // If target reps hit
  if (currentReps >= TARGET_REPS) {
    setRepsLog[currentSet] = currentReps;
    document.getElementById('setRepCount' + currentSet).textContent = currentReps + ' reps';

    if (currentSet >= TARGET_SETS) {
      finishWorkout();
    } else {
      document.getElementById('btnNextSet').style.display = 'flex';
      document.getElementById('btnNextSet').disabled = false;
      setStatus('rest', 'Set Done!');
      isTracking = false;
      showRest();
    }
  }
}

// ── UPDATE UI ─────────────────────────────────────────────────────
function updateUI() {
  document.getElementById('statReps').textContent  = currentReps;
  document.getElementById('statSet').textContent   = currentSet;
  document.getElementById('statTotal').textContent = totalReps;
  document.getElementById('ringReps').textContent  = currentReps;

  // Ring progress
  const pct = Math.min(currentReps / TARGET_REPS, 1);
  const circumference = 345;
  document.getElementById('ringFill').style.strokeDashoffset =
    circumference - (pct * circumference);

  // Set bubbles
  if (document.getElementById('setRepCount' + currentSet)) {
    document.getElementById('setRepCount' + currentSet).textContent = currentReps + ' reps';
  }
}

// ── TIMER ─────────────────────────────────────────────────────────
function startTimer() {
  startTime = Date.now();
  timerInterval = setInterval(() => {
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    const m = Math.floor(elapsed / 60);
    const s = elapsed % 60;
    document.getElementById('statTime').textContent =
      m + ':' + (s < 10 ? '0' : '') + s;
  }, 1000);
}

// ── REST TIMER ────────────────────────────────────────────────────
let restTimeout = null;
let restInterval = null;

function showRest() {
  const overlay = document.getElementById('restOverlay');
  overlay.classList.add('show');
  let secs = REST_SECS;
  const ring = document.getElementById('restRingFill');
  const numEl = document.getElementById('restSecs');
  const circumference = 502;

  ring.style.strokeDashoffset = 0;
  numEl.textContent = secs;

  restInterval = setInterval(() => {
    secs--;
    numEl.textContent = secs;
    ring.style.strokeDashoffset = circumference * (1 - secs / REST_SECS);
    if (secs <= 0) endRest();
  }, 1000);
}

function endRest() {
  clearInterval(restInterval);
  document.getElementById('restOverlay').classList.remove('show');
  nextSet();
}

function skipRest() {
  clearInterval(restInterval);
  document.getElementById('restOverlay').classList.remove('show');
  nextSet();
}

// ── CONTROLS ──────────────────────────────────────────────────────
function startTracking() {
  if (!cameraReady) return;
  isTracking = true;
  angleHistory = [];
  phase = 'up';
  setStatus('active', 'Tracking');
  document.getElementById('btnStart').disabled  = true;
  document.getElementById('btnStop').disabled   = false;
  document.getElementById('btnNextSet').style.display = 'none';
  startTimer();
}

function stopTracking() {
  isTracking = false;
  clearInterval(timerInterval);
  setStatus('ready', 'Paused');
  document.getElementById('btnStart').disabled = false;
  document.getElementById('btnStop').disabled  = true;
}

function nextSet() {
  if (currentSet >= TARGET_SETS) { finishWorkout(); return; }

  // Mark current set done
  const bubble = document.getElementById('setBubble' + currentSet);
  if (bubble) { bubble.classList.remove('active'); bubble.classList.add('done'); }

  currentSet++;
  currentReps = 0;
  angleHistory = [];
  phase = 'up';

  // Activate new bubble
  const newBubble = document.getElementById('setBubble' + currentSet);
  if (newBubble) newBubble.classList.add('active');

  updateUI();
  document.getElementById('ringFill').style.strokeDashoffset = 345;
  document.getElementById('statSet').textContent = currentSet;
  document.getElementById('btnNextSet').style.display = 'none';
  document.getElementById('btnStart').disabled = false;
  document.getElementById('btnStop').disabled  = true;
  setStatus('ready', 'Rest Done — Ready');
  isTracking = false;
}

function finishWorkout() {
  isTracking = false;
  clearInterval(timerInterval);
  cancelAnimationFrame(animFrame);

  const elapsed = startTime ? Math.floor((Date.now() - startTime) / 1000) : 0;
  const m = Math.floor(elapsed / 60);
  const s = elapsed % 60;

  document.getElementById('doneTotalReps').textContent = totalReps;
  document.getElementById('doneTime').textContent = m + ':' + (s < 10 ? '0' : '') + s;
  document.getElementById('doneOverlay').classList.add('show');
  setStatus('done', 'Done!');
}

// ── STATUS PILL ───────────────────────────────────────────────────
function setStatus(type, text) {
  const pill = document.getElementById('statusPill');
  pill.className = 'status-pill ' + type;
  document.getElementById('statusText').textContent = text;
}
</script>
</body>
</html>