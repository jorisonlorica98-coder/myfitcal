<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();

$user_id      = $_SESSION['user_id'];
$db           = getDB();

$exercise     = htmlspecialchars($_GET['exercise'] ?? 'Push-Up');
$target_sets  = (int)($_GET['sets']  ?? 3);
$target_reps  = 15;
$exercise_idx = (int)($_GET['ex_index'] ?? 0);   // which exercise in the list
$day          = (int)($_GET['day']   ?? 1);
$exercise_id  = (int)($_GET['exercise_id'] ?? ($exercise_idx + 1));

// ── AJAX: save completed exercise ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input      = json_decode(file_get_contents('php://input'), true);
    $action     = $input['action'] ?? '';
    $total_reps = (int)($input['total_reps'] ?? 0);

    if ($action === 'complete_exercise') {
        // 1. Save / update exercise completion
        $stmt = $db->prepare("
            INSERT INTO user_workout_progress
                (user_id, day_id, exercise_id, completed, completed_at, day_number)
            VALUES (?, ?, ?, 1, NOW(), ?)
            ON DUPLICATE KEY UPDATE
                completed    = 1,
                completed_at = NOW()
        ");
        $stmt->execute([$user_id, $day, $exercise_id, $day]);

        // 2. Update users aggregate counters
        $upd = $db->prepare("
            UPDATE users
            SET total_reps = total_reps + ?,
                last_workout_date = NOW()
            WHERE id = ?
        ");
        $upd->execute([$total_reps, $user_id]);

        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode(['success' => false]);
    exit;
}
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
.status-pill.ready  { background: rgba(59,130,246,.15); color: #60a5fa; border: 1px solid rgba(59,130,246,.3); }
.status-pill.active { background: rgba(34,197,94,.15);  color: #4ade80; border: 1px solid rgba(34,197,94,.3); }
.status-pill.rest   { background: rgba(249,115,22,.15); color: #fb923c; border: 1px solid rgba(249,115,22,.3); }
.status-pill.done   { background: rgba(34,197,94,.2);   color: #4ade80; border: 1px solid rgba(34,197,94,.4); }
.status-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: currentColor;
  animation: pulse 1.5s ease-in-out infinite;
}
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

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
  transform: scaleX(-1);
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
  position: absolute; inset: 0;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  background: rgba(15,23,42,.85);
  backdrop-filter: blur(4px);
  gap: 1rem; text-align: center; padding: 2rem;
}
.camera-overlay-msg i { font-size: 3rem; color: var(--accent); }
.camera-overlay-msg h2 { font-size: 1.3rem; font-weight: 800; }
.camera-overlay-msg p  { font-size: .9rem; color: var(--muted); line-height: 1.6; }

.rep-flash {
  position: absolute; top: 50%; left: 50%;
  transform: translate(-50%,-50%) scale(0);
  font-size: 6rem; font-weight: 900;
  color: #4ade80;
  text-shadow: 0 0 40px rgba(74,222,128,.6);
  pointer-events: none;
  transition: transform .1s, opacity .3s;
  opacity: 0; z-index: 10;
}
.rep-flash.show { transform: translate(-50%,-50%) scale(1); opacity: 1; }

.form-guide {
  position: absolute; bottom: 0; left: 0; right: 0;
  padding: .75rem 1rem;
  background: linear-gradient(transparent, rgba(0,0,0,.8));
  display: flex; align-items: center; justify-content: space-between;
}
.form-guide-text { font-size: .8rem; font-weight: 600; color: #fff; }
.confidence-bar { display: flex; align-items: center; gap: 8px; font-size: .75rem; color: var(--muted); }
.conf-track { width: 80px; height: 5px; background: var(--surface2); border-radius: 999px; overflow: hidden; }
.conf-fill  { height: 100%; background: var(--green); border-radius: 999px; transition: width .3s; width: 0%; }

.side-panel { display: flex; flex-direction: column; gap: 1rem; }

.stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
.stat-card {
  background: var(--surface); border: 1px solid var(--surface2);
  border-radius: 16px; padding: 1rem 1.25rem;
}
.stat-label { font-size: .72rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; margin-bottom: .3rem; }
.stat-value { font-size: 2rem; font-weight: 900; line-height: 1; }
.stat-sub   { font-size: .72rem; color: var(--muted); margin-top: .2rem; }
.stat-value.green  { color: #4ade80; }
.stat-value.blue   { color: #60a5fa; }
.stat-value.orange { color: #fb923c; }
.stat-value.white  { color: #f1f5f9; }

.set-tracker {
  background: var(--surface); border: 1px solid var(--surface2);
  border-radius: 16px; padding: 1.25rem;
}
.set-tracker-title {
  font-size: .85rem; font-weight: 700; color: var(--muted);
  text-transform: uppercase; letter-spacing: .06em; margin-bottom: 1rem;
}
.sets-row { display: flex; gap: .6rem; flex-wrap: wrap; }
.set-bubble {
  width: 44px; height: 44px; border-radius: 50%;
  border: 2px solid var(--surface2);
  display: flex; align-items: center; justify-content: center;
  font-size: .8rem; font-weight: 800; color: var(--muted);
  transition: all .3s; position: relative;
}
.set-bubble.active { border-color: var(--accent); color: #60a5fa; box-shadow: 0 0 12px rgba(59,130,246,.3); }
.set-bubble.done   { background: rgba(34,197,94,.15); border-color: var(--green); color: #4ade80; }
.set-bubble .set-rep-count { position: absolute; bottom: -18px; font-size: .65rem; color: var(--muted); white-space: nowrap; }

.ring-wrap {
  display: flex; flex-direction: column; align-items: center;
  background: var(--surface); border: 1px solid var(--surface2);
  border-radius: 16px; padding: 1.5rem 1.25rem 1.25rem;
}
.ring-wrap-title { font-size: .85rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 1rem; }
.ring-container { position: relative; width: 130px; height: 130px; }
.ring-container svg { transform: rotate(-90deg); }
.ring-bg   { fill: none; stroke: var(--surface2); stroke-width: 10; }
.ring-fill { fill: none; stroke: #4ade80; stroke-width: 10; stroke-linecap: round; transition: stroke-dashoffset .4s ease; stroke-dasharray: 345; stroke-dashoffset: 345; }
.ring-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.ring-reps  { font-size: 2.5rem; font-weight: 900; line-height: 1; color: #4ade80; }
.ring-total { font-size: .85rem; color: var(--muted); }

.phase-bar { width: 100%; height: 6px; background: var(--surface2); border-radius: 999px; overflow: hidden; margin-top: 1rem; }
.phase-fill { height: 100%; border-radius: 999px; background: #4ade80; transition: width .2s, background .3s; width: 50%; }

.controls { display: flex; flex-direction: column; gap: .6rem; }
.btn {
  padding: .85rem; border: none; border-radius: 14px;
  font-family: inherit; font-size: .95rem; font-weight: 700;
  cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: all .2s;
}
.btn-primary { background: linear-gradient(135deg, var(--primary-alt), var(--primary)); color: #fff; box-shadow: 0 6px 20px rgba(10,31,68,.4); }
.btn-primary:hover { transform: translateY(-1px); }
.btn-green  { background: rgba(34,197,94,.15); color: #4ade80; border: 1px solid rgba(34,197,94,.3); }
.btn-green:hover  { background: rgba(34,197,94,.25); }
.btn-orange { background: rgba(249,115,22,.15); color: #fb923c; border: 1px solid rgba(249,115,22,.3); }
.btn-orange:hover { background: rgba(249,115,22,.25); }
.btn-red    { background: rgba(239,68,68,.15);  color: #f87171; border: 1px solid rgba(239,68,68,.3); }
.btn-red:hover    { background: rgba(239,68,68,.25); }
.btn:disabled { opacity: .4; cursor: not-allowed; transform: none !important; }

.rest-overlay {
  position: fixed; inset: 0; z-index: 200;
  background: rgba(15,23,42,.92); backdrop-filter: blur(6px);
  display: none; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 1rem; text-align: center;
}
.rest-overlay.show { display: flex; }
.rest-timer-ring { position: relative; width: 180px; height: 180px; }
.rest-timer-ring svg { transform: rotate(-90deg); }
.rest-ring-bg   { fill:none; stroke: var(--surface2); stroke-width: 12; }
.rest-ring-fill { fill:none; stroke: #fb923c; stroke-width: 12; stroke-linecap: round; stroke-dasharray: 502; stroke-dashoffset: 0; transition: stroke-dashoffset 1s linear; }
.rest-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.rest-secs  { font-size: 3.5rem; font-weight: 900; color: #fb923c; line-height: 1; }
.rest-label { font-size: .85rem; color: var(--muted); }
.rest-title { font-size: 1.5rem; font-weight: 900; }
.rest-sub   { font-size: .95rem; color: var(--muted); }

/* ── DONE OVERLAY — now shows saving state + redirects ── */
.done-overlay {
  position: fixed; inset: 0; z-index: 200;
  background: rgba(15,23,42,.95); backdrop-filter: blur(8px);
  display: none; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 1.25rem; text-align: center; padding: 2rem;
}
.done-overlay.show { display: flex; }
.done-icon  { font-size: 4rem; }
.done-title { font-size: 2rem; font-weight: 900; }
.done-sub   { font-size: 1rem; color: var(--muted); line-height: 1.7; }
.done-stats { display: flex; gap: 2rem; margin-top: .5rem; }
.done-stat  { text-align: center; }
.done-stat-val { font-size: 2rem; font-weight: 900; color: #4ade80; }
.done-stat-lbl { font-size: .8rem; color: var(--muted); margin-top: 2px; }

.saving-msg {
  font-size: .9rem; color: var(--muted);
  display: flex; align-items: center; gap: 8px;
}
.spinner {
  width: 18px; height: 18px; border-radius: 50%;
  border: 2px solid var(--surface2);
  border-top-color: #4ade80;
  animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.tips-card { background: var(--surface); border: 1px solid var(--surface2); border-radius: 16px; padding: 1.25rem; }
.tips-title { font-size: .85rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; margin-bottom: .75rem; }
.tip-item { display: flex; align-items: flex-start; gap: 8px; font-size: .82rem; color: var(--muted); line-height: 1.5; margin-bottom: .5rem; }
.tip-item i { color: var(--accent); font-size: .85rem; margin-top: 2px; flex-shrink: 0; }
</style>
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
  <div class="topbar-left">
    <button class="back-btn" onclick="goBack()">
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

<div class="main-grid">
  <div>
    <div class="camera-panel">
      <video id="videoEl" autoplay playsinline muted></video>
      <canvas id="canvasEl"></canvas>
      <div class="rep-flash" id="repFlash">+1</div>
      <div class="camera-overlay-msg" id="cameraOverlay">
        <i class="bi bi-camera-video"></i>
        <h2>Start Camera</h2>
        <p>I-click ang "Start Camera" para simulan ang<br>pose detection at rep counting.</p>
      </div>
      <div class="form-guide">
        <span class="form-guide-text" id="poseGuideText">—</span>
        <div class="confidence-bar">
          <span>Pose</span>
          <div class="conf-track"><div class="conf-fill" id="confFill"></div></div>
        </div>
      </div>
    </div>
  </div>

  <div class="side-panel">
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
      <div style="width:100%;margin-top:1rem;">
        <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--muted);margin-bottom:4px;">
          <span>DOWN</span><span id="phaseLabel">—</span><span>UP</span>
        </div>
        <div class="phase-bar"><div class="phase-fill" id="phaseFill"></div></div>
      </div>
    </div>

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
  <div class="done-title">Exercise Complete!</div>
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
  <div class="saving-msg" id="savingMsg">
    <div class="spinner"></div> Sine-save ang progress...
  </div>
  <div id="savedMsg" style="display:none;color:#4ade80;font-weight:700;font-size:.95rem;">
    <i class="bi bi-check-circle-fill"></i> Nai-save! Babalik sa workout...
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.17.0/dist/tf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/pose-detection@2.1.3/dist/pose-detection.min.js"></script>

<script>
// ── CONFIG ────────────────────────────────────────────────────────
const EXERCISE      = <?= json_encode($exercise) ?>;
const TARGET_SETS   = <?= $target_sets ?>;
const TARGET_REPS   = <?= $target_reps ?>;
const REST_SECS     = 60;
const DAY           = <?= $day ?>;
const EX_INDEX      = <?= $exercise_idx ?>;   // 0-based index in workout list
const EXERCISE_ID   = <?= $exercise_id ?>;

// Back button — go back without completing
function goBack() {
  window.location.href = `/myfitcal_system/user/workout.php?day=${DAY}`;
}

const EXERCISE_CONFIG = {
  'push':    { type: 'pushup',  tips: ['Ipakita ang buong katawan sa camera (side view)', 'Panatilihing tuwid ang likod mo', 'Ibaba ang dibdib hanggang malapit sa sahig'] },
  'squat':   { type: 'squat',   tips: ['Ibaba ang hips hanggang parallel sa knees', 'Itayo ka nang tuwid para mabilang ang rep', 'Side view ang pinakamahusay'] },
  'lunge':   { type: 'squat',   tips: ['Ihiwalay ang mga paa — isang paa sa harap', 'Ibaba ang likod na tuhod pababa', 'Tuwid ang katawan'] },
  'burpee':  { type: 'burpee',  tips: ['Simulan ang nakatayo, tapos lumuhod at humiga', 'Tumayo nang buo at tumalon sa dulo', 'Facing ang camera'] },
  'plank':   { type: 'plank',   tips: ['Panatilihing tuwid ang buong katawan', 'Side view ang dapat makita ng camera', 'Ang plank mode ay nagco-count ng seconds'] },
  'sit':     { type: 'situp',   tips: ['Humiga at itaas ang ulo at balikat', 'Top-down o side view ng camera', 'Baluktot ang tuhod sa sahig'] },
  'crunch':  { type: 'situp',   tips: ['Humiga at itaas ang ulo at balikat', 'Hindi kailangang umabot sa tuhod', 'Overhead view o side view'] },
  'curl':    { type: 'curl',    tips: ['Ipakita ang buong braso sa camera', 'Itaas ang kamay hanggang balikat level', 'Side view ang pinakamainam'] },
  'press':   { type: 'press',   tips: ['Itaas ang mga kamay nang buo sa ibabaw ng ulo', 'Ibaba sa balikat level para mabilang', 'Facing ang camera'] },
  'default': { type: 'generic', tips: ['Tiyaking makikita ng camera ang buong katawan', 'Maliwanag ang lugar at walang harang', 'Side view ang pinaka-ideal'] }
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
let detector = null, videoEl = document.getElementById('videoEl'),
    canvasEl = document.getElementById('canvasEl'), ctx = canvasEl.getContext('2d'),
    isTracking = false, cameraReady = false,
    currentSet = 1, currentReps = 0, totalReps = 0,
    phase = 'up', startTime = null, timerInterval = null, animFrame = null,
    angleHistory = [];
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
    const stream = await navigator.mediaDevices.getUserMedia({ video: { width:640, height:480, facingMode:'user' }, audio:false });
    videoEl.srcObject = stream;
    await new Promise(r => videoEl.onloadedmetadata = r);
    await videoEl.play();
    canvasEl.width  = videoEl.videoWidth;
    canvasEl.height = videoEl.videoHeight;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Loading AI Model...';
    detector = await poseDetection.createDetector(
      poseDetection.SupportedModels.MoveNet,
      { modelType: poseDetection.movenet.modelType.SINGLEPOSE_LIGHTNING }
    );
    cameraReady = true;
    document.getElementById('cameraOverlay').style.display = 'none';
    document.getElementById('btnStart').disabled = false;
    btn.innerHTML = '<i class="bi bi-camera-video-fill"></i> Camera On';
    btn.style.cssText = 'background:rgba(34,197,94,.15);color:#4ade80;border:1px solid rgba(34,197,94,.3)';
    setStatus('ready', 'Camera Ready');
    renderLoop();
  } catch(e) {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-camera-video-fill"></i> Start Camera';
    alert('Hindi ma-access ang camera.');
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
      const score = pose.score ?? 0;
      document.getElementById('confFill').style.width  = (score * 100) + '%';
      document.getElementById('confFill').style.background = score > 0.4 ? '#22c55e' : score > 0.25 ? '#f97316' : '#ef4444';
      if (isTracking) countRep(pose);
    }
  } catch(e) {}
  animFrame = requestAnimationFrame(renderLoop);
}

// ── SKELETON ─────────────────────────────────────────────────────
const CONNECTIONS = [[5,6],[5,7],[7,9],[6,8],[8,10],[5,11],[6,12],[11,12],[11,13],[13,15],[12,14],[14,16]];

function drawSkeleton(pose) {
  const kps = pose.keypoints;
  ctx.lineWidth = 3;
  CONNECTIONS.forEach(([a,b]) => {
    if (kps[a].score > 0.3 && kps[b].score > 0.3) {
      ctx.beginPath(); ctx.moveTo(kps[a].x, kps[a].y); ctx.lineTo(kps[b].x, kps[b].y);
      ctx.strokeStyle = 'rgba(96,165,250,0.8)'; ctx.stroke();
    }
  });
  kps.forEach((kp, i) => {
    if (kp.score > 0.3) {
      ctx.beginPath(); ctx.arc(kp.x, kp.y, i < 5 ? 5 : 6, 0, 2*Math.PI);
      ctx.fillStyle = i < 5 ? '#f472b6' : '#4ade80'; ctx.fill();
    }
  });
}

function angle(a, b, c) {
  const ab = {x:a.x-b.x, y:a.y-b.y}, cb = {x:c.x-b.x, y:c.y-b.y};
  const dot = ab.x*cb.x + ab.y*cb.y;
  const mag = Math.sqrt(ab.x**2+ab.y**2) * Math.sqrt(cb.x**2+cb.y**2);
  if (mag === 0) return 180;
  return Math.acos(Math.max(-1, Math.min(1, dot/mag))) * 180/Math.PI;
}

function kp(pose, name) { return pose.keypoints.find(k => k.name === name); }

function smoothAngle(a) {
  angleHistory.push(a);
  if (angleHistory.length > HISTORY_LEN) angleHistory.shift();
  return angleHistory.reduce((s,v) => s+v, 0) / angleHistory.length;
}

// ── REP COUNTING ─────────────────────────────────────────────────
function countRep(pose) {
  const type = exConfig.type;
  let mainAngle = null, guideText = '', downThresh = 90, upThresh = 160;

  if (type === 'pushup') {
    const ls=kp(pose,'left_shoulder'),le=kp(pose,'left_elbow'),lw=kp(pose,'left_wrist');
    const rs=kp(pose,'right_shoulder'),re=kp(pose,'right_elbow'),rw=kp(pose,'right_wrist');
    const lS=Math.min(ls?.score??0,le?.score??0,lw?.score??0), rS=Math.min(rs?.score??0,re?.score??0,rw?.score??0);
    if (lS>0.3||rS>0.3) { const u=lS>=rS?[ls,le,lw]:[rs,re,rw]; mainAngle=angle(u[0],u[1],u[2]); }
    downThresh=90; upThresh=150; guideText=phase==='up'?'Lower your body ↓':'Push up ↑';
  } else if (type === 'squat') {
    const lh=kp(pose,'left_hip'),lk=kp(pose,'left_knee'),la=kp(pose,'left_ankle');
    const rh=kp(pose,'right_hip'),rk=kp(pose,'right_knee'),ra=kp(pose,'right_ankle');
    const lS=Math.min(lh?.score??0,lk?.score??0,la?.score??0), rS=Math.min(rh?.score??0,rk?.score??0,ra?.score??0);
    if (lS>0.3||rS>0.3) { const u=lS>=rS?[lh,lk,la]:[rh,rk,ra]; mainAngle=angle(u[0],u[1],u[2]); }
    downThresh=100; upThresh=160; guideText=phase==='up'?'Lower into squat ↓':'Stand up ↑';
  } else if (type === 'burpee') {
    const ls=kp(pose,'left_shoulder'),rs=kp(pose,'right_shoulder');
    const lh=kp(pose,'left_hip'),rh=kp(pose,'right_hip');
    if ((ls?.score??0)>0.3&&(lh?.score??0)>0.3) {
      mainAngle = ((lh.y+rh.y)/2 - (ls.y+rs.y)/2) / canvasEl.height * 180;
    }
    downThresh=30; upThresh=60; guideText=phase==='up'?'Drop down ↓':'Jump up ↑';
  } else if (type === 'situp') {
    const ls=kp(pose,'left_shoulder'),lh=kp(pose,'left_hip'),lk=kp(pose,'left_knee');
    const rs=kp(pose,'right_shoulder'),rh=kp(pose,'right_hip'),rk=kp(pose,'right_knee');
    const lS=Math.min(ls?.score??0,lh?.score??0,lk?.score??0), rS=Math.min(rs?.score??0,rh?.score??0,rk?.score??0);
    if (lS>0.3||rS>0.3) { const u=lS>=rS?[ls,lh,lk]:[rs,rh,rk]; mainAngle=angle(u[0],u[1],u[2]); }
    downThresh=60; upThresh=110; guideText=phase==='up'?'Lie back down ↓':'Crunch up ↑';
  } else if (type === 'curl') {
    const ls=kp(pose,'left_shoulder'),le=kp(pose,'left_elbow'),lw=kp(pose,'left_wrist');
    const rs=kp(pose,'right_shoulder'),re=kp(pose,'right_elbow'),rw=kp(pose,'right_wrist');
    const lS=Math.min(ls?.score??0,le?.score??0,lw?.score??0), rS=Math.min(rs?.score??0,re?.score??0,rw?.score??0);
    if (lS>0.3||rS>0.3) { const u=lS>=rS?[ls,le,lw]:[rs,re,rw]; mainAngle=angle(u[0],u[1],u[2]); }
    downThresh=50; upThresh=160; guideText=phase==='up'?'Lower arm ↓':'Curl up ↑';
  } else if (type === 'press') {
    const ls=kp(pose,'left_shoulder'),le=kp(pose,'left_elbow'),lw=kp(pose,'left_wrist');
    if ((ls?.score??0)>0.3&&(le?.score??0)>0.3&&(lw?.score??0)>0.3) mainAngle=angle(ls,le,lw);
    downThresh=90; upThresh=160; guideText=phase==='up'?'Lower to shoulders ↓':'Press up ↑';
  } else if (type === 'plank') {
    const ls=kp(pose,'left_shoulder'),lh=kp(pose,'left_hip'),la=kp(pose,'left_ankle');
    if ((ls?.score??0)>0.3&&(lh?.score??0)>0.3&&(la?.score??0)>0.3) {
      mainAngle = angle(ls,lh,la);
      document.getElementById('poseGuideText').textContent = mainAngle>150 ? '✓ Good form — holding' : 'Straighten your body';
    }
    return;
  } else {
    const lh=kp(pose,'left_hip'),lk=kp(pose,'left_knee'),ls=kp(pose,'left_shoulder');
    if ((lh?.score??0)>0.3&&(lk?.score??0)>0.3&&(ls?.score??0)>0.3) mainAngle=angle(ls,lh,lk);
    downThresh=100; upThresh=160; guideText=phase==='up'?'Go down ↓':'Come up ↑';
  }

  if (mainAngle === null) { document.getElementById('poseGuideText').textContent = 'Move into frame...'; return; }

  const smoothed = smoothAngle(mainAngle);
  const normalized = Math.max(0, Math.min(1, (smoothed-downThresh)/(upThresh-downThresh)));
  document.getElementById('phaseFill').style.width      = (normalized*100)+'%';
  document.getElementById('phaseFill').style.background = normalized>0.5 ? '#4ade80' : '#fb923c';
  document.getElementById('phaseLabel').textContent     = normalized>0.6 ? 'UP' : normalized<0.4 ? 'DOWN' : '...';

  if (phase==='up' && smoothed<downThresh) {
    phase = 'down';
  } else if (phase==='down' && smoothed>upThresh) {
    phase = 'up';
    registerRep();
  }
  document.getElementById('poseGuideText').textContent = guideText;
}

// ── REP REGISTRATION ─────────────────────────────────────────────
function registerRep() {
  currentReps++; totalReps++;
  const flash = document.getElementById('repFlash');
  flash.textContent = currentReps;
  flash.classList.add('show');
  setTimeout(() => flash.classList.remove('show'), 600);
  updateUI();

  if (currentReps >= TARGET_REPS) {
    document.getElementById('setRepCount'+currentSet).textContent = currentReps+' reps';
    if (currentSet >= TARGET_SETS) {
      finishWorkout();
    } else {
      document.getElementById('btnNextSet').style.display = 'flex';
      document.getElementById('btnNextSet').disabled = false;
      setStatus('rest','Set Done!');
      isTracking = false;
      showRest();
    }
  }
}

function updateUI() {
  document.getElementById('statReps').textContent  = currentReps;
  document.getElementById('statSet').textContent   = currentSet;
  document.getElementById('statTotal').textContent = totalReps;
  document.getElementById('ringReps').textContent  = currentReps;
  const pct = Math.min(currentReps/TARGET_REPS,1);
  document.getElementById('ringFill').style.strokeDashoffset = 345 - pct*345;
  if (document.getElementById('setRepCount'+currentSet))
    document.getElementById('setRepCount'+currentSet).textContent = currentReps+' reps';
}

function startTimer() {
  startTime = Date.now();
  timerInterval = setInterval(() => {
    const e = Math.floor((Date.now()-startTime)/1000);
    document.getElementById('statTime').textContent = Math.floor(e/60)+':'+(e%60<10?'0':'')+e%60;
  }, 1000);
}

let restInterval = null;
function showRest() {
  document.getElementById('restOverlay').classList.add('show');
  let secs = REST_SECS;
  const ring = document.getElementById('restRingFill'), numEl = document.getElementById('restSecs');
  ring.style.strokeDashoffset = 0; numEl.textContent = secs;
  restInterval = setInterval(() => {
    secs--;
    numEl.textContent = secs;
    ring.style.strokeDashoffset = 502*(1-secs/REST_SECS);
    if (secs <= 0) endRest();
  }, 1000);
}
function endRest()  { clearInterval(restInterval); document.getElementById('restOverlay').classList.remove('show'); nextSet(); }
function skipRest() { clearInterval(restInterval); document.getElementById('restOverlay').classList.remove('show'); nextSet(); }

function startTracking() {
  if (!cameraReady) return;
  isTracking = true; angleHistory = []; phase = 'up';
  setStatus('active','Tracking');
  document.getElementById('btnStart').disabled  = true;
  document.getElementById('btnStop').disabled   = false;
  document.getElementById('btnNextSet').style.display = 'none';
  startTimer();
}

function stopTracking() {
  isTracking = false; clearInterval(timerInterval);
  setStatus('ready','Paused');
  document.getElementById('btnStart').disabled = false;
  document.getElementById('btnStop').disabled  = true;
}

function nextSet() {
  if (currentSet >= TARGET_SETS) { finishWorkout(); return; }
  const bubble = document.getElementById('setBubble'+currentSet);
  if (bubble) { bubble.classList.remove('active'); bubble.classList.add('done'); }
  currentSet++; currentReps = 0; angleHistory = []; phase = 'up';
  const nb = document.getElementById('setBubble'+currentSet);
  if (nb) nb.classList.add('active');
  updateUI();
  document.getElementById('ringFill').style.strokeDashoffset = 345;
  document.getElementById('statSet').textContent = currentSet;
  document.getElementById('btnNextSet').style.display = 'none';
  document.getElementById('btnStart').disabled = false;
  document.getElementById('btnStop').disabled  = true;
  setStatus('ready','Rest Done — Ready');
  isTracking = false;
}

// ── FINISH — save to DB then redirect ────────────────────────────
async function finishWorkout() {
  isTracking = false;
  clearInterval(timerInterval);
  cancelAnimationFrame(animFrame);

  const elapsed = startTime ? Math.floor((Date.now()-startTime)/1000) : 0;
  const m = Math.floor(elapsed/60), s = elapsed%60;
  document.getElementById('doneTotalReps').textContent = totalReps;
  document.getElementById('doneTime').textContent = m+':'+(s<10?'0':'')+s;
  document.getElementById('doneOverlay').classList.add('show');
  setStatus('done','Done!');

  // Save to database
  try {
    const res = await fetch(window.location.pathname, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action:'complete_exercise', total_reps: totalReps })
    });
    const data = await res.json();
    if (data.success) {
      document.getElementById('savingMsg').style.display = 'none';
      document.getElementById('savedMsg').style.display  = 'flex';
      document.getElementById('savedMsg').style.alignItems = 'center';
      document.getElementById('savedMsg').style.gap = '8px';

      // Redirect back to workout.php with completed index after 2 seconds
      setTimeout(() => {
        window.location.href = `/myfitcal_system/user/workout.php?day=${DAY}&completed=${EX_INDEX}`;
      }, 2000);
    }
  } catch(e) {
    document.getElementById('savingMsg').innerHTML = '<i class="bi bi-exclamation-triangle" style="color:#f87171"></i> Save failed. Redirecting...';
    setTimeout(() => {
      window.location.href = `/myfitcal_system/user/workout.php?day=${DAY}&completed=${EX_INDEX}`;
    }, 2000);
  }
}

function setStatus(type, text) {
  document.getElementById('statusPill').className = 'status-pill '+type;
  document.getElementById('statusText').textContent = text;
}
</script>
</body>
</html>