<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../config/exercises.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$db = getDB();
$day = (int)($_GET['day'] ?? 1);
if ($day < 1 || $day > 30) { header('Location: /myfitcal_system/user/dashboard.php'); exit; }

// ── Auto-redirect female users ────────────────────────────────
$genderq = $db->prepare("SELECT gender FROM users WHERE id=? LIMIT 1");
$genderq->execute([$user_id]);
$gender = $genderq->fetchColumn() ?: 'male';
$is_female = strtolower($gender) === 'female';
if (strtolower($gender) === 'female') {
    header("Location: /myfitcal_system/user/workout_female.php?day=$day"); exit;
}

$gq = $db->prepare("SELECT * FROM user_goals WHERE user_id=?");
$gq->execute([$user_id]);
$goals = $gq->fetch();

$fq = $db->prepare("SELECT * FROM user_fitness WHERE user_id=?");
$fq->execute([$user_id]);
$fitness = $fq->fetch();

$level    = $fitness['fitness_level'] ?? 'beginner';
$activity = $fitness['activity_level'] ?? 'sedentary';
$goal_type= $goals['goal_type'] ?? 'maintain';
$days_pw  = $fitness['days_per_week'] ?? 3;

$schedule = getExercisePlan($level, $activity, $goal_type, $days_pw);
$today    = $schedule[$day];

if ($today['is_rest']) { header('Location: /myfitcal_system/user/dashboard.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete') {
    $ins = $db->prepare("
        INSERT INTO user_workout_progress (user_id, day_number, exercise_id, completed, completed_at)
        VALUES (?, ?, 1, 1, NOW())
        ON DUPLICATE KEY UPDATE completed=1, completed_at=NOW()
    ");
    $ins->execute([$user_id, $day]);
    header('Location: /myfitcal_system/user/complete.php?day='.$day); exit;
}

$uq = $db->prepare("SELECT name FROM users WHERE id=?");
$uq->execute([$user_id]);
$user = $uq->fetch();

$level_colors = ['beginner'=>'#3b82f6','normal'=>'#16a34a','expert'=>'#f97316','advance'=>'#dc2626'];
$lcolor = $level_colors[$level] ?? '#16a34a';

$exercise_poses = [
    'Jumping Jacks'         => 'jump',
    'Wall Push-Up'          => 'pushup',
    'Chair Squat'           => 'squat',
    'Standing March'        => 'march',
    'Glute Bridge'          => 'bridge',
    'Seated Leg Raise'      => 'legrise',
    'Knee Push-Up'          => 'pushup',
    'Standing Side Bend'    => 'bend',
    'Push-Up'               => 'pushup',
    'Bodyweight Squat'      => 'squat',
    'Plank'                 => 'plank',
    'Reverse Lunge'         => 'squat',
    'Tricep Dip'            => 'pushup',
    'Superman'              => 'bridge',
    'High Knees'            => 'march',
    'Mountain Climber'      => 'march',
    'Diamond Push-Up'       => 'pushup',
    'Jump Squat'            => 'jump',
    'Burpee'                => 'jump',
    'Pike Push-Up'          => 'pushup',
    'Bulgarian Split Squat' => 'squat',
    'Plank to Push-Up'      => 'plank',
    'Speed Skater'          => 'jump',
    'V-Up'                  => 'legrise',
    'Clap Push-Up'          => 'pushup',
    'Pistol Squat'          => 'squat',
    'Burpee Tuck Jump'      => 'jump',
    'Archer Push-Up'        => 'pushup',
    'Single Leg Deadlift'   => 'bend',
    'Dragon Flag'           => 'legrise',
    'Lateral Bound'         => 'jump',
    'L-Sit Hold'            => 'plank',
];

$exercise_svgs = [
    'jump' => '<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" class="pose-svg pose-jump"><circle cx="50" cy="10" r="9" fill="none" stroke="#22c55e" stroke-width="2.5"/><line x1="50" y1="19" x2="50" y2="55" stroke="#22c55e" stroke-width="3" stroke-linecap="round"/><line x1="50" y1="32" x2="8" y2="18" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="50" y1="32" x2="92" y2="18" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="50" y1="55" x2="22" y2="88" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="50" y1="55" x2="78" y2="88" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/></svg>',
    'pushup' => '<svg viewBox="0 0 130 75" xmlns="http://www.w3.org/2000/svg" class="pose-svg pose-pushup"><circle cx="108" cy="12" r="9" fill="none" stroke="#22c55e" stroke-width="2.5"/><line x1="108" y1="21" x2="78" y2="38" stroke="#22c55e" stroke-width="3" stroke-linecap="round"/><line x1="78" y1="38" x2="22" y2="38" stroke="#22c55e" stroke-width="3" stroke-linecap="round"/><line x1="88" y1="31" x2="88" y2="55" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="55" y1="38" x2="55" y2="58" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="22" y1="38" x2="22" y2="55" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/></svg>',
    'squat' => '<svg viewBox="0 0 80 100" xmlns="http://www.w3.org/2000/svg" class="pose-svg pose-squat"><circle cx="40" cy="10" r="9" fill="none" stroke="#22c55e" stroke-width="2.5"/><line x1="40" y1="19" x2="40" y2="52" stroke="#22c55e" stroke-width="3" stroke-linecap="round"/><line x1="40" y1="32" x2="12" y2="42" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="40" y1="32" x2="68" y2="42" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="40" y1="52" x2="18" y2="76" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="18" y1="76" x2="12" y2="92" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="40" y1="52" x2="62" y2="76" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="62" y1="76" x2="68" y2="92" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/></svg>',
    'plank' => '<svg viewBox="0 0 140 60" xmlns="http://www.w3.org/2000/svg" class="pose-svg pose-plank"><circle cx="115" cy="14" r="9" fill="none" stroke="#22c55e" stroke-width="2.5"/><line x1="115" y1="23" x2="18" y2="36" stroke="#22c55e" stroke-width="3" stroke-linecap="round"/><line x1="90" y1="27" x2="90" y2="50" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="48" y1="33" x2="48" y2="52" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="18" y1="36" x2="18" y2="52" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/></svg>',
    'march' => '<svg viewBox="0 0 80 110" xmlns="http://www.w3.org/2000/svg" class="pose-svg pose-march"><circle cx="40" cy="10" r="9" fill="none" stroke="#22c55e" stroke-width="2.5"/><line x1="40" y1="19" x2="40" y2="60" stroke="#22c55e" stroke-width="3" stroke-linecap="round"/><line x1="40" y1="33" x2="15" y2="52" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="40" y1="33" x2="65" y2="42" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="40" y1="60" x2="25" y2="88" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="25" y1="88" x2="22" y2="105" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="40" y1="60" x2="58" y2="72" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="58" y1="72" x2="62" y2="62" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/></svg>',
    'bridge' => '<svg viewBox="0 0 130 72" xmlns="http://www.w3.org/2000/svg" class="pose-svg pose-bridge"><circle cx="15" cy="24" r="9" fill="none" stroke="#22c55e" stroke-width="2.5"/><line x1="24" y1="24" x2="68" y2="28" stroke="#22c55e" stroke-width="3" stroke-linecap="round"/><line x1="68" y1="28" x2="95" y2="10" stroke="#22c55e" stroke-width="3" stroke-linecap="round"/><line x1="68" y1="28" x2="82" y2="58" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="82" y1="58" x2="82" y2="68" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="95" y1="10" x2="112" y2="42" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="112" y1="42" x2="112" y2="58" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/></svg>',
    'legrise' => '<svg viewBox="0 0 140 68" xmlns="http://www.w3.org/2000/svg" class="pose-svg pose-legrise"><circle cx="12" cy="34" r="9" fill="none" stroke="#22c55e" stroke-width="2.5"/><line x1="21" y1="34" x2="92" y2="40" stroke="#22c55e" stroke-width="3" stroke-linecap="round"/><line x1="56" y1="20" x2="56" y2="52" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="92" y1="40" x2="128" y2="15" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="92" y1="40" x2="118" y2="60" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="118" y1="60" x2="118" y2="65" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/></svg>',
    'bend' => '<svg viewBox="0 0 80 100" xmlns="http://www.w3.org/2000/svg" class="pose-svg pose-bend"><circle cx="40" cy="10" r="9" fill="none" stroke="#22c55e" stroke-width="2.5"/><line x1="40" y1="19" x2="52" y2="55" stroke="#22c55e" stroke-width="3" stroke-linecap="round"/><line x1="40" y1="32" x2="12" y2="38" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="40" y1="32" x2="66" y2="56" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="52" y1="55" x2="38" y2="82" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/><line x1="52" y1="55" x2="66" y2="80" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"/></svg>',
];

$exercise_videos = [
    'Jumping Jacks'         => '/myfitcal_system/video/jumping jack.mp4',
    'Wall Push-Up'          => '/myfitcal_system/video/wall push up.mp4',
    'Chair Squat'           => '/myfitcal_system/video/squats.mp4',
    'Standing March'        => '/myfitcal_system/video/standing march.mp4',
    'Glute Bridge'          => '/myfitcal_system/video/glute bridge.mp4',
    'Seated Leg Raise'      => '/myfitcal_system/video/seated leg raise.mp4',
    'Knee Push-Up'          => '/myfitcal_system/video/knee push up.mp4',
    'Standing Side Bend'    => '/myfitcal_system/video/standing side bend.mp4',
    'Push-Up'               => '/myfitcal_system/video/push up.mp4',
    'Bodyweight Squat'      => '/myfitcal_system/video/bodyweight.mp4',
    'Plank'                 => '/myfitcal_system/video/plank.mp4',
    'Reverse Lunge'         => '/myfitcal_system/video/lunges.mp4',
    'Tricep Dip'            => '/myfitcal_system/video/tricep dips.mp4',
    'Superman'              => '/myfitcal_system/video/superman.mp4',
    'High Knees'            => '/myfitcal_system/video/high knee.mp4',
    'Mountain Climber'      => '/myfitcal_system/video/mountain.mp4',
    'Diamond Push-Up'       => '/myfitcal_system/video/diamond push up.mp4',
    'Jump Squat'            => '/myfitcal_system/video/jumping squat.mp4',
    'Burpee'                => '/myfitcal_system/video/burpees.mp4',
    'Pike Push-Up'          => '/myfitcal_system/video/pike push up.mp4',
    'Bulgarian Split Squat' => '/myfitcal_system/video/Bulgarian split squat.mp4',
    'Plank to Push-Up'      => '/myfitcal_system/video/plank push up.mp4',
    'Speed Skater'          => '/myfitcal_system/video/speed skater.mp4',
    'V-Up'                  => '/myfitcal_system/video/v up.mp4',
    'Clap Push-Up'          => '/myfitcal_system/video/clap push up.mp4',
    'Pistol Squat'          => '/myfitcal_system/video/pistol squat.mp4',
    'Burpee Tuck Jump'      => '/myfitcal_system/video/burpees tack jump.mp4',
    'Archer Push-Up'        => '/myfitcal_system/video/Archer push up.mp4',
    'Single Leg Deadlift'   => '/myfitcal_system/video/Single leg deadlift.mp4',
    'Dragon Flag'           => '/myfitcal_system/video/dragon flag.mp4',
    'Lateral Bound'         => '/myfitcal_system/video/Lateral bound.mp4',
    'L-Sit Hold'            => '/myfitcal_system/video/L sit hold.mp4',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Day <?= $day ?> Workout — MyFitCal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

body{
  font-family:'DM Sans',sans-serif;
  background:#f5f5f4;
  color:#1c1917;
  min-height:100vh;
}

/* ── SIDEBAR ── */
.sidebar{
  position:fixed;left:0;top:0;bottom:0;width:220px;
  background:#1c1917;
  display:flex;flex-direction:column;
  z-index:200;
  overflow:hidden;
}
.sb-top{padding:18px 14px 14px;border-bottom:1px solid rgba(255,255,255,.06);flex-shrink:0;}
.sb-brand{display:flex;align-items:center;gap:9px;}
.sb-logo{width:30px;height:30px;border-radius:6px;overflow:hidden;flex-shrink:0;}
.sb-logo img{width:100%;height:100%;object-fit:contain;}
.sb-name{font-size:14px;font-weight:600;color:#fafaf9;}
.sb-plan{font-size:10px;color:#78716c;margin-top:1px;}

.sb-nav{flex:1;padding:10px 8px;overflow-y:auto;min-height:0;}
.sb-lbl{font-size:10px;font-weight:600;color:#57534e;text-transform:uppercase;letter-spacing:.6px;padding:10px 6px 4px;display:block;}
.sb-link{
  display:flex;align-items:center;gap:9px;
  padding:7px 8px;border-radius:6px;
  font-size:13px;font-weight:500;
  color:#a8a29e;text-decoration:none;
  margin-bottom:1px;transition:all .12s;
}
.sb-link:hover{background:rgba(255,255,255,.05);color:#e7e5e4;}
.sb-link.active{background:rgba(255,255,255,.08);color:#fafaf9;}
.sb-link i{font-size:14px;width:16px;text-align:center;}

.sb-foot{
  padding:10px 14px;
  border-top:1px solid rgba(255,255,255,.06);
  display:flex;align-items:center;gap:9px;
  flex-shrink:0;
  margin-top:auto;
}
.sb-av{width:28px;height:28px;border-radius:50%;background:#292524;color:#e7e5e4;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;flex-shrink:0;}
.sb-uname{font-size:12px;font-weight:500;color:#e7e5e4;}
.sb-role{font-size:10px;color:#78716c;}
.sb-out{margin-left:auto;color:#57534e;text-decoration:none;font-size:15px;transition:color .12s;}
.sb-out:hover{color:#f87171;}

/* ── MAIN LAYOUT ── */
.main{margin-left:220px;min-height:100vh;display:flex;flex-direction:column;}

/* ── TOPBAR ── */
.topbar{
  background:#fff;
  border-bottom:1px solid #e7e5e4;
  padding:12px 24px;
  display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:50;
}
.topbar-l h2{font-size:14px;font-weight:600;color:#1c1917;}
.topbar-l p{font-size:12px;color:#78716c;margin-top:1px;}
.topbar-r{display:flex;align-items:center;gap:8px;}
.tb-btn{
  display:inline-flex;align-items:center;gap:5px;
  padding:6px 12px;border-radius:6px;
  border:1px solid #e7e5e4;background:#fff;
  font-family:'DM Sans',sans-serif;
  font-size:12px;font-weight:500;color:#78716c;
  text-decoration:none;transition:all .12s;cursor:pointer;
}
.tb-btn:hover{border-color:#1c1917;color:#1c1917;}

/* ── CONTENT ── */
.content{padding:24px;flex:1;}

/* ── WORKOUT HEADER ── */
.workout-header{
  background:#1c1917;
  border-radius:8px;
  padding:20px 22px;
  margin-bottom:16px;
  color:#fff;
}
.wh-label{font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#78716c;margin-bottom:4px;}
.wh-title{font-size:20px;font-weight:700;margin-bottom:8px;color:#fafaf9;}
.wh-meta{display:flex;gap:14px;flex-wrap:wrap;}
.wh-tag{font-size:12px;font-weight:500;color:#a8a29e;display:flex;align-items:center;gap:5px;}

/* ── PROGRESS BAR ── */
.progress-bar-wrap{
  background:#fff;
  border-radius:8px;
  border:1px solid #e7e5e4;
  padding:14px 18px;
  margin-bottom:16px;
}
.pb-label{display:flex;justify-content:space-between;font-size:12px;font-weight:500;color:#78716c;margin-bottom:8px;}
.pb-track{height:6px;background:#f5f5f4;border-radius:999px;overflow:hidden;}
.pb-fill{height:100%;background:#1c1917;border-radius:999px;transition:width .5s ease;}

/* ── EXERCISE CARDS ── */
.ex-card{
  background:#fff;
  border-radius:8px;
  border:1px solid #e7e5e4;
  margin-bottom:8px;
  overflow:hidden;
  transition:all .2s;
}
.ex-card.active{border-color:#16a34a;box-shadow:0 2px 12px rgba(22,163,74,.1);}
.ex-card.done-card{opacity:.55;}

.ex-card-header{
  display:flex;align-items:center;gap:12px;
  padding:12px 16px;cursor:pointer;user-select:none;
}
.ex-card-header:hover{background:#fafaf9;}

.ex-num-badge{
  width:28px;height:28px;border-radius:6px;
  display:flex;align-items:center;justify-content:center;
  font-size:11px;font-weight:600;
  background:#f5f5f4;color:#78716c;
  flex-shrink:0;transition:all .2s;
}
.ex-card.active .ex-num-badge{background:#1c1917;color:#fff;}
.ex-card.done-card .ex-num-badge{background:#f0fdf4;color:#16a34a;}

.ex-title-wrap{flex:1;}
.ex-card-name{font-size:13px;font-weight:600;color:#1c1917;}
.ex-card-meta{font-size:11px;color:#78716c;margin-top:2px;}
.ex-status{font-size:14px;}

.ex-body{display:none;border-top:1px solid #f5f5f4;}
.ex-card.active .ex-body{display:block;}

/* ── MEDIA BOX ── */
.ex-media{
  position:relative;
  width:100%;
  height:320px;
  overflow:hidden;
  background:#000;
}

/* VIDEO — covers the full box, no white gaps */
.ex-video{
  position:absolute;
  top:0;
  left:0;
  width:100%;
  height:100%;
  object-fit:cover;
  display:block;
}

.ex-media-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;filter:brightness(.15) saturate(.3);z-index:0;}
.ex-media-tint{position:absolute;inset:0;background:radial-gradient(ellipse at center,rgba(22,163,74,.05) 0%,transparent 70%);z-index:1;}
.pose-container{position:absolute;inset:0;z-index:2;display:flex;align-items:center;justify-content:center;}
.pose-svg{width:130px;height:130px;}
.muscle-badge{
  position:absolute;bottom:10px;left:10px;
  background:rgba(28,25,23,.7);color:#a8a29e;
  font-size:11px;font-weight:500;
  padding:4px 10px;border-radius:5px;
  backdrop-filter:blur(4px);z-index:3;
}

/* pose animations */
.pose-jump{animation:poseJump .7s ease-in-out infinite alternate;}
.pose-pushup{animation:posePush 1s ease-in-out infinite alternate;}
.pose-squat{animation:poseSquat 1s ease-in-out infinite alternate;}
.pose-plank{animation:posePlank 2s ease-in-out infinite alternate;}
.pose-march{animation:poseMarch .55s ease-in-out infinite alternate;}
.pose-bridge{animation:poseBridge 1.2s ease-in-out infinite alternate;}
.pose-legrise{animation:poseLeg 1s ease-in-out infinite alternate;}
.pose-bend{animation:poseBend 1.2s ease-in-out infinite alternate;}
@keyframes poseJump{0%{transform:translateY(0) scale(1);}100%{transform:translateY(-14px) scale(1.04);}}
@keyframes posePush{0%{transform:translateY(0);}100%{transform:translateY(9px) scaleY(.93);}}
@keyframes poseSquat{0%{transform:scaleY(1) translateY(0);}100%{transform:scaleY(.83) translateY(11px);}}
@keyframes posePlank{0%{transform:rotate(0deg);}100%{transform:rotate(1deg) scaleX(1.01);}}
@keyframes poseMarch{0%{transform:rotate(-4deg);}100%{transform:rotate(4deg);}}
@keyframes poseBridge{0%{transform:translateY(0);}100%{transform:translateY(-9px);}}
@keyframes poseLeg{0%{transform:scaleY(1);}100%{transform:scaleY(.95) translateY(5px);}}
@keyframes poseBend{0%{transform:rotate(0deg);}100%{transform:rotate(11deg);}}

/* ── EXERCISE DETAILS ── */
.ex-details{padding:14px 16px;}

.detail-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px;}
.dg-item{text-align:center;background:#f5f5f4;border-radius:6px;padding:10px 6px;}
.dg-val{font-size:14px;font-weight:700;color:#1c1917;}
.dg-label{font-size:10px;font-weight:600;color:#a8a29e;text-transform:uppercase;letter-spacing:.07em;margin-top:2px;}

.instructions-box{
  background:#f0fdf4;border:1px solid #bbf7d0;
  border-radius:6px;padding:10px 12px;margin-bottom:12px;
}
.ib-label{font-size:10px;font-weight:700;color:#15803d;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;}
.ib-text{font-size:12px;color:#166534;line-height:1.7;}

.btn-done{
  width:100%;background:#1c1917;color:#fafaf9;
  border:none;border-radius:6px;padding:11px;
  font-family:'DM Sans',sans-serif;font-weight:600;font-size:13px;
  cursor:pointer;transition:all .15s;
  display:flex;align-items:center;justify-content:center;gap:6px;
}
.btn-done:hover{background:#292524;}
.btn-done.completed{background:#f0fdf4;color:#16a34a;cursor:default;}

/* ── FINISH SECTION ── */
.finish-section{
  background:#fff;border-radius:8px;border:1px solid #e7e5e4;
  padding:28px;text-align:center;margin-top:16px;display:none;
}
.finish-section.show{display:block;}
.finish-title{font-size:16px;font-weight:700;color:#1c1917;margin-bottom:4px;}
.finish-sub{font-size:12px;color:#78716c;margin-bottom:20px;}
.btn-finish{
  display:block;background:#1c1917;color:#fafaf9;
  border:none;border-radius:6px;padding:12px 2rem;
  font-family:'DM Sans',sans-serif;font-weight:600;font-size:13px;
  cursor:pointer;width:100%;text-decoration:none;transition:background .15s;
}
.btn-finish:hover{background:#292524;color:#fafaf9;}
</style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<aside class="sidebar">
  <div class="sb-top">
    <div class="sb-brand">
      <div class="sb-logo"><img src="/myfitcal_system/assets/image/logo.png" alt="MyFitCal"></div>
      <div>
        <div class="sb-name">MyFitCal</div>
        <div class="sb-plan"><?= $is_female ? 'Female Plan' : 'Male Plan' ?></div>
      </div>
    </div>
  </div>
  <nav class="sb-nav">
    <span class="sb-lbl">Main</span>
    <a href="/myfitcal_system/user/<?= $is_female ? 'dashboard_female' : 'dashboard' ?>.php" class="sb-link">
      <i class="bi bi-grid-1x2"></i> Dashboard
    </a>
    <a href="/myfitcal_system/user/<?= $is_female ? 'workout_female' : 'workout' ?>.php?day=1" class="sb-link active">
      <i class="bi bi-lightning-charge"></i> Workout
    </a>
    <a href="/myfitcal_system/user/meals.php" class="sb-link">
      <i class="bi bi-egg-fried"></i> Meals
    </a>
    <span class="sb-lbl">Track</span>
    <a href="/myfitcal_system/user/calendar.php" class="sb-link">
      <i class="bi bi-calendar3"></i> Calendar
    </a>
    <a href="/myfitcal_system/user/chatbot.php" class="sb-link">
      <i class="bi bi-robot"></i> FitBot
    </a>
    <span class="sb-lbl">Account</span>
    <a href="/myfitcal_system/user/profile.php" class="sb-link">
      <i class="bi bi-person-circle"></i> My Profile
    </a>
  </nav>
  <div class="sb-foot">
    <div class="sb-av"><?= strtoupper(substr($user['name'],0,1)) ?></div>
    <div>
      <div class="sb-uname"><?= htmlspecialchars(explode(' ',$user['name'])[0]) ?></div>
      <div class="sb-role">Member</div>
    </div>
    <a class="sb-out" href="/myfitcal_system/logout.php"><i class="bi bi-box-arrow-right"></i></a>
  </div>
</aside>

<!-- ── MAIN ── -->
<div class="main">

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-l">
      <h2>Workout</h2>
      <p>Day <?= $day ?> of 30</p>
    </div>
    <div class="topbar-r">
      <a href="/myfitcal_system/user/<?= $is_female ? 'dashboard_female' : 'dashboard' ?>.php" class="tb-btn">
      </a>
    </div>
  </div>

  <div class="content">

    <!-- WORKOUT HEADER -->
    <div class="workout-header">
      <div class="wh-label">Day <?= $day ?> of 30</div>
      <div class="wh-title"><?= htmlspecialchars($today['focus']) ?></div>
      <div class="wh-meta">
        <span class="wh-tag"><i class="bi bi-list-check"></i> <?= count($today['exercises']) ?> exercises</span>
        <span class="wh-tag"><i class="bi bi-bar-chart"></i> <?= ucfirst($level) ?> level</span>
        <span class="wh-tag"><i class="bi bi-fire"></i> ~<?= array_sum(array_column($today['exercises'],'calories')) * 3 ?> kcal</span>
      </div>
    </div>

    <!-- PROGRESS BAR -->
    <div class="progress-bar-wrap">
      <div class="pb-label">
        <span>Progress</span>
        <span id="progressText">0 / <?= count($today['exercises']) ?> done</span>
      </div>
      <div class="pb-track">
        <div class="pb-fill" id="progressFill" style="width:0%"></div>
      </div>
    </div>

    <!-- EXERCISE CARDS -->
    <?php foreach ($today['exercises'] as $i => $ex):
      $pose_type = $exercise_poses[$ex['name']] ?? 'march';
      $pose_svg  = $exercise_svgs[$pose_type];
      $video_src = $exercise_videos[$ex['name']] ?? null;
    ?>
    <div class="ex-card <?= $i===0?'active':'' ?>" id="card<?= $i ?>">
      <div class="ex-card-header" onclick="toggleCard(<?= $i ?>)">
        <div class="ex-num-badge" id="badge<?= $i ?>"><?= $i+1 ?></div>
        <div class="ex-title-wrap">
          <div class="ex-card-name"><?= htmlspecialchars($ex['name']) ?></div>
          <div class="ex-card-meta"><?= $ex['sets'] ?> sets &times; <?= $ex['reps'] ?> &middot; <?= $ex['muscle'] ?></div>
        </div>
        <div class="ex-status" id="status<?= $i ?>">⬜</div>
      </div>
      <div class="ex-body">
        <div class="ex-media">
          <?php if ($video_src): ?>
          <video class="ex-video" autoplay loop muted playsinline preload="auto">
            <source src="<?= htmlspecialchars($video_src) ?>" type="video/mp4">
          </video>
          <?php else: ?>
          <img src="<?= $ex['img'] ?>" class="ex-media-bg" alt="">
          <div class="ex-media-tint"></div>
          <div class="pose-container"><?= $pose_svg ?></div>
          <?php endif; ?>
          <div class="muscle-badge"><i class="bi bi-bullseye"></i> <?= htmlspecialchars($ex['muscle']) ?></div>
        </div>
        <div class="ex-details">
          <div class="detail-grid">
            <div class="dg-item"><div class="dg-val"><?= $ex['sets'] ?></div><div class="dg-label">Sets</div></div>
            <div class="dg-item"><div class="dg-val"><?= $ex['reps'] ?></div><div class="dg-label">Reps</div></div>
            <div class="dg-item"><div class="dg-val"><?= $ex['rest'] ?>s</div><div class="dg-label">Rest</div></div>
          </div>
          <div class="instructions-box">
            <div class="ib-label"><i class="bi bi-info-circle"></i> How to do it</div>
            <div class="ib-text"><?= htmlspecialchars($ex['instructions']) ?></div>
          </div>
          <button class="btn-done" id="btn<?= $i ?>" onclick="markDone(<?= $i ?>)">
            <i class="bi bi-check-lg"></i> Mark as Done
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- FINISH SECTION -->
    <div class="finish-section" id="finishSection">
      <div style="font-size:2.5rem;margin-bottom:12px;">🏆</div>
      <div class="finish-title">All Exercises Done!</div>
      <div class="finish-sub">Amazing work on Day <?= $day ?>. Tap below to save your progress.</div>
      <form method="POST">
        <input type="hidden" name="action" value="complete">
        <button type="submit" class="btn-finish"><i class="bi bi-check-circle-fill"></i> Complete Workout</button>
      </form>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<script>
var total = <?= count($today['exercises']) ?>;
var done  = new Array(total).fill(false);

function toggleCard(i) {
  var card = document.getElementById('card' + i);
  var wasActive = card.classList.contains('active');
  document.querySelectorAll('.ex-card').forEach(function(c) { c.classList.remove('active'); });
  if (!wasActive) card.classList.add('active');
}

function markDone(i) {
  if (done[i]) return;
  done[i] = true;
  var card = document.getElementById('card' + i);
  card.classList.remove('active');
  card.classList.add('done-card');
  var badge = document.getElementById('badge' + i);
  badge.innerHTML = '<i class="bi bi-check-lg"></i>';
  badge.style.background = '#f0fdf4';
  badge.style.color = '#16a34a';
  document.getElementById('status' + i).textContent = '✅';
  var btn = document.getElementById('btn' + i);
  btn.className = 'btn-done completed';
  btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Done!';
  var count = done.filter(Boolean).length;
  document.getElementById('progressText').textContent = count + ' / ' + total + ' done';
  document.getElementById('progressFill').style.width = (count / total * 100) + '%';
  if (i + 1 < total && !done[i + 1]) {
    document.getElementById('card' + (i + 1)).classList.add('active');
    document.getElementById('card' + (i + 1)).scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  if (count === total) {
    var fin = document.getElementById('finishSection');
    fin.classList.add('show');
    fin.scrollIntoView({ behavior: 'smooth' });
  }
}
</script>
</body>
</html>