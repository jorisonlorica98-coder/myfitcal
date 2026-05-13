<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();

$db  = getDB();
$uid = $_SESSION['user_id'];

$uq = $db->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$uq->execute([$uid]);
$user = $uq->fetch();

$gq = $db->prepare("SELECT * FROM user_goals WHERE user_id=? LIMIT 1");
$gq->execute([$uid]);
$goals = $gq->fetch();

$fq = $db->prepare("SELECT * FROM user_fitness WHERE user_id=? LIMIT 1");
$fq->execute([$uid]);
$fitness = $fq->fetch();

$pq2 = $db->prepare("SELECT * FROM user_profiles WHERE user_id=? LIMIT 1");
$pq2->execute([$uid]);
$profile2 = $pq2->fetch();

if (empty($user['height_cm']) && !empty($profile2['height_cm'])) $user['height_cm'] = $profile2['height_cm'];
if (empty($user['weight_kg']) && !empty($profile2['weight_kg'])) $user['weight_kg'] = $profile2['weight_kg'];

$cq = $db->prepare("SELECT DISTINCT day_number FROM user_workout_progress WHERE user_id=? AND completed=1");
$cq->execute([$uid]);
$completed_days = $cq->fetchAll(PDO::FETCH_COLUMN);
$total_workout_days = count($completed_days);

try {
    $s = $db->prepare("SELECT COALESCE(SUM(calories),0) FROM calorie_logs WHERE user_id=?");
    $s->execute([$uid]); $total_cal_consumed = (int)$s->fetchColumn();
} catch(Exception $e) { $total_cal_consumed = 0; }

try {
    $s = $db->prepare("SELECT COUNT(*) FROM user_workout_progress WHERE user_id=? AND completed=1");
    $s->execute([$uid]); $total_burned = (int)$s->fetchColumn() * 50;
} catch(Exception $e) { $total_burned = 0; }

try {
    $s = $db->prepare("SELECT COUNT(*) FROM calorie_logs WHERE user_id=?");
    $s->execute([$uid]); $total_cal_logs = (int)$s->fetchColumn();
} catch(Exception $e) { $total_cal_logs = 0; }

$bmi = null;
try {
    $s = $db->prepare("SELECT * FROM bmi_logs WHERE user_id=? ORDER BY logged_at DESC LIMIT 1");
    $s->execute([$uid]); $bmi = $s->fetch();
} catch(Exception $e) {}

if (!$bmi && !empty($user['height_cm']) && !empty($user['weight_kg'])) {
    $h = (float)$user['height_cm']; $w = (float)$user['weight_kg'];
    $bv = round($w / (($h/100) * ($h/100)), 1);
    $bc = $bv < 18.5 ? 'Underweight' : ($bv < 25 ? 'Normal' : ($bv < 30 ? 'Overweight' : 'Obese'));
    $bmi = ['bmi'=>$bv,'category'=>$bc,'height_cm'=>$h,'weight_kg'=>$w,'logged_at'=>date('Y-m-d H:i:s'),'computed'=>true];
    try {
        $db->prepare("INSERT INTO bmi_logs (user_id,bmi,category,height_cm,weight_kg,logged_at) VALUES (?,?,?,?,?,NOW())")
           ->execute([$uid,$bv,$bc,$h,$w]);
    } catch(Exception $e) {}
}

$notifications = [];
try {
    $rq = $db->prepare("SELECT reminder_time, next_workout_day FROM workout_reminders WHERE user_id=? AND is_active=1");
    $rq->execute([$uid]); $rem = $rq->fetch();
    if ($rem) $notifications[] = ['icon'=>'bi-alarm-fill','color'=>'#f97316','bg'=>'#fff7ed','title'=>'Workout Reminder Set','msg'=>'Day '.$rem['next_workout_day'].' at '.$rem['reminder_time'],'time'=>'Active'];
} catch(Exception $e) {}

if ($total_workout_days > 0) $notifications[] = ['icon'=>'bi-trophy-fill','color'=>'#16a34a','bg'=>'#dcfce7','title'=>'Workout Progress','msg'=>$total_workout_days.' of 30 days completed','time'=>'Ongoing'];
if ($bmi) $notifications[] = ['icon'=>'bi-heart-pulse-fill','color'=>'#2563eb','bg'=>'#eff6ff','title'=>'Latest BMI Reading','msg'=>'BMI '.$bmi['bmi'].' — '.$bmi['category'],'time'=>date('M j',strtotime($bmi['logged_at']))];
$notifications[] = ['icon'=>'bi-person-check-fill','color'=>'#9333ea','bg'=>'#fdf4ff','title'=>'Account Active','msg'=>'Member since '.($user['created_at'] ? date('F Y',strtotime($user['created_at'])) : 'N/A'),'time'=>'Always'];

$member_since = $user['created_at'] ? date('F Y', strtotime($user['created_at'])) : 'N/A';
$age          = !empty($user['birthdate']) ? (int)date_diff(date_create($user['birthdate']), date_create())->y : null;
$first_name   = explode(' ', $user['name'])[0];
$avatar_ltr   = strtoupper(substr($user['name'], 0, 1));
$is_female    = strtolower($user['gender'] ?? '') === 'female';
$goal_labels  = ['lose'=>'Weight Loss','maintain'=>'Maintenance','gain'=>'Weight Gain','muscle'=>'Muscle Gain'];
$level_labels = ['beginner'=>'Beginner','normal'=>'Normal','expert'=>'Expert','advance'=>'Advance'];

$success = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $gender = $_POST['gender'] ?? '';
        $bdate = $_POST['birthdate'] ?? '';
        $height = (float)($_POST['height_cm'] ?? 0);
        $weight = (float)($_POST['weight_kg'] ?? 0);
        if (empty($name)) { $error = 'Name is required.'; }
        else {
            $db->prepare("UPDATE users SET name=?,gender=?,birthdate=?,height_cm=?,weight_kg=? WHERE id=?")
               ->execute([$name,$gender,$bdate,$height?:null,$weight?:null,$uid]);
            $_SESSION['name'] = $name;
            $uq2 = $db->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
            $uq2->execute([$uid]); $user = $uq2->fetch();
            $success = 'Profile updated successfully!';
        }
    }
    if ($action === 'change_password') {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        if (!password_verify($cur, $user['password'])) $error = 'Current password is incorrect.';
        elseif (strlen($new) < 6) $error = 'New password must be at least 6 characters.';
        elseif ($new !== $conf) $error = 'Passwords do not match.';
        else {
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
            $success = 'Password changed successfully!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Profile — MyFitCal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:#f5f5f4;color:#1c1917;min-height:100vh;}

/* ── SIDEBAR ── */
.sidebar{position:fixed;left:0;top:0;bottom:0;width:220px;background:#1c1917;display:flex;flex-direction:column;z-index:200;overflow:hidden;}
.sb-top{padding:18px 14px 14px;border-bottom:1px solid rgba(255,255,255,.06);flex-shrink:0;}
.sb-brand{display:flex;align-items:center;gap:9px;}
.sb-logo{width:30px;height:30px;border-radius:6px;overflow:hidden;flex-shrink:0;}
.sb-logo img{width:100%;height:100%;object-fit:contain;}
.sb-name{font-size:14px;font-weight:600;color:#fafaf9;}
.sb-plan{font-size:10px;color:#78716c;margin-top:1px;}
.sb-nav{flex:1;padding:10px 8px;overflow-y:auto;min-height:0;}
.sb-lbl{font-size:10px;font-weight:600;color:#57534e;text-transform:uppercase;letter-spacing:.6px;padding:10px 6px 4px;display:block;}
.sb-link{display:flex;align-items:center;gap:9px;padding:7px 8px;border-radius:6px;font-size:13px;font-weight:500;color:#a8a29e;text-decoration:none;margin-bottom:1px;transition:all .12s;}
.sb-link:hover{background:rgba(255,255,255,.05);color:#e7e5e4;}
.sb-link.active{background:rgba(255,255,255,.08);color:#fafaf9;}
.sb-link i{font-size:14px;width:16px;text-align:center;}
.sb-foot{padding:10px 14px;border-top:1px solid rgba(255,255,255,.06);display:flex;align-items:center;gap:9px;flex-shrink:0;}
.sb-av{width:28px;height:28px;border-radius:50%;background:#292524;color:#e7e5e4;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;flex-shrink:0;}
.sb-uname{font-size:12px;font-weight:500;color:#e7e5e4;}
.sb-role{font-size:10px;color:#78716c;}
.sb-out{margin-left:auto;color:#57534e;text-decoration:none;font-size:15px;transition:color .12s;}
.sb-out:hover{color:#f87171;}

/* ── MAIN ── */
.main{margin-left:220px;min-height:100vh;display:flex;flex-direction:column;}
.topbar{background:#fff;border-bottom:1px solid #e7e5e4;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;}
.topbar-l h2{font-size:14px;font-weight:600;color:#1c1917;}
.topbar-l p{font-size:12px;color:#78716c;margin-top:1px;}
.topbar-r{display:flex;align-items:center;gap:8px;}
.tb-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:6px;border:1px solid #e7e5e4;background:#fff;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:500;color:#78716c;text-decoration:none;transition:all .12s;cursor:pointer;}
.tb-btn:hover{border-color:#1c1917;color:#1c1917;}
.notif-btn{position:relative;width:34px;height:34px;border-radius:6px;border:1px solid #e7e5e4;background:#fff;display:flex;align-items:center;justify-content:center;color:#78716c;cursor:pointer;transition:all .12s;}
.notif-btn:hover{border-color:#1c1917;color:#1c1917;}
.notif-dot{position:absolute;top:6px;right:6px;width:7px;height:7px;border-radius:50%;background:#16a34a;border:2px solid #fff;}

/* NOTIFICATION PANEL */
.notif-panel{position:absolute;top:56px;right:24px;width:300px;background:#fff;border-radius:8px;border:1px solid #e7e5e4;box-shadow:0 8px 24px rgba(0,0,0,.1);z-index:200;display:none;}
.notif-panel.show{display:block;}
.notif-panel-head{padding:12px 14px;border-bottom:1px solid #e7e5e4;display:flex;align-items:center;justify-content:space-between;}
.notif-panel-title{font-size:13px;font-weight:600;color:#1c1917;}
.notif-count{background:#1c1917;color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;}
.notif-list{max-height:280px;overflow-y:auto;}
.notif-item{display:flex;align-items:flex-start;gap:10px;padding:10px 14px;border-bottom:1px solid #f5f5f4;transition:background .1s;}
.notif-item:last-child{border-bottom:none;}
.notif-item:hover{background:#fafaf9;}
.notif-icon-wrap{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;}
.nt-title{font-size:12px;font-weight:600;color:#1c1917;margin-bottom:2px;}
.nt-msg{font-size:11px;color:#78716c;}
.nt-time{font-size:10px;color:#a8a29e;margin-top:2px;}
.notif-footer{padding:10px 14px;border-top:1px solid #e7e5e4;text-align:center;}
.notif-footer a{font-size:12px;font-weight:600;color:#1c1917;text-decoration:none;}

/* CONTENT */
.content{padding:24px;flex:1;}

/* HERO */
.hero{background:#1c1917;border-radius:8px;padding:20px 22px;margin-bottom:16px;color:#fff;display:flex;align-items:center;gap:16px;}
.hero-av{width:56px;height:56px;border-radius:50%;background:#292524;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:#fafaf9;flex-shrink:0;}
.hero-info{flex:1;}
.hero-name{font-size:18px;font-weight:700;color:#fafaf9;margin-bottom:4px;}
.hero-pills{display:flex;flex-wrap:wrap;gap:5px;}
.hpill{display:inline-flex;align-items:center;gap:4px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.55);font-size:11px;font-weight:500;padding:2px 8px;border-radius:5px;}
.hero-stats{display:flex;gap:10px;flex-shrink:0;}
.hstat{text-align:center;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:10px 14px;}
.hstat-val{font-size:20px;font-weight:700;color:#fafaf9;line-height:1;}
.hstat-lbl{font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;margin-top:2px;}

/* ALERT */
.alert{border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:12px;font-weight:600;display:flex;align-items:center;gap:6px;}
.alert-ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;}
.alert-err{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;}

/* TABS */
.tabs{display:flex;gap:4px;background:#fff;border-radius:8px;padding:4px;border:1px solid #e7e5e4;margin-bottom:14px;width:fit-content;}
.tab{padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;color:#78716c;cursor:pointer;border:none;background:transparent;font-family:'DM Sans',sans-serif;transition:all .12s;display:flex;align-items:center;gap:5px;}
.tab.active{background:#1c1917;color:#fafaf9;}
.tab:hover:not(.active){color:#1c1917;}
.tab-panel{display:none;}
.tab-panel.active{display:block;}

/* CARDS */
.card{background:#fff;border-radius:8px;border:1px solid #e7e5e4;padding:18px;margin-bottom:12px;}
.card-title{font-size:13px;font-weight:600;color:#1c1917;margin-bottom:14px;display:flex;align-items:center;gap:6px;}
.card-title i{color:#16a34a;}

/* FORM */
.fg{display:flex;flex-direction:column;gap:4px;margin-bottom:12px;}
.fg label{font-size:11px;font-weight:600;color:#78716c;text-transform:uppercase;letter-spacing:.05em;}
.fg input,.fg select{padding:8px 11px;border-radius:6px;border:1px solid #e7e5e4;font-family:'DM Sans',sans-serif;font-size:13px;color:#1c1917;background:#fafaf9;outline:none;transition:border-color .12s;}
.fg input:focus,.fg select:focus{border-color:#1c1917;background:#fff;}
.fg input:disabled{opacity:.5;cursor:not-allowed;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.form-row.three{grid-template-columns:1fr 1fr 1fr;}
.btn-save{background:#1c1917;color:#fafaf9;font-family:'DM Sans',sans-serif;font-weight:600;font-size:13px;padding:9px 20px;border-radius:6px;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:all .12s;margin-top:4px;}
.btn-save:hover{background:#292524;}
.btn-danger{background:#dc2626;}
.btn-danger:hover{background:#b91c1c;}

/* INFO GRID */
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.iitem{background:#fafaf9;border-radius:6px;border:1px solid #e7e5e4;padding:12px;display:flex;align-items:center;gap:10px;}
.iitem-icon{width:32px;height:32px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;}
.iitem-label{font-size:10px;font-weight:600;color:#78716c;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;}
.iitem-value{font-size:13px;font-weight:600;color:#1c1917;}

/* BMI */
.bmi-row{display:flex;align-items:center;gap:14px;background:#fafaf9;border-radius:6px;border:1px solid #e7e5e4;padding:14px;margin-bottom:12px;}
.bmi-num{font-size:28px;font-weight:700;line-height:1;}
.bmi-badge{font-size:11px;font-weight:600;padding:2px 8px;border-radius:4px;margin-top:4px;display:inline-block;}

/* PROGRESS */
.prog-wrap{background:#f5f5f4;border-radius:999px;height:5px;overflow:hidden;margin-top:6px;}
.prog-fill{height:100%;border-radius:999px;background:#1c1917;}

@media(max-width:900px){.main{margin-left:0;}.sidebar{display:none;}.form-row,.form-row.three{grid-template-columns:1fr;}.info-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>

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
    <a href="/myfitcal_system/user/<?= $is_female ? 'dashboard_female' : 'dashboard' ?>.php" class="sb-link"><i class="bi bi-grid-1x2"></i> Dashboard</a>
    <a href="/myfitcal_system/user/<?= $is_female ? 'workout_female' : 'workout' ?>.php?day=1" class="sb-link"><i class="bi bi-lightning-charge"></i> Workout</a>
    <a href="/myfitcal_system/user/meals.php" class="sb-link"><i class="bi bi-egg-fried"></i> Meals</a>
    <span class="sb-lbl">Track</span>
    <a href="/myfitcal_system/user/calendar.php" class="sb-link"><i class="bi bi-calendar3"></i> Calendar</a>
    <a href="/myfitcal_system/user/chatbot.php" class="sb-link"><i class="bi bi-robot"></i> FitBot</a>
    <span class="sb-lbl">Account</span>
    <a href="/myfitcal_system/user/profile.php" class="sb-link active"><i class="bi bi-person-circle"></i> My Profile</a>
  </nav>
  <div class="sb-foot">
    <div class="sb-av"><?= $avatar_ltr ?></div>
    <div>
      <div class="sb-uname"><?= htmlspecialchars($first_name) ?></div>
      <div class="sb-role">Member</div>
    </div>
    <a href="/myfitcal_system/logout.php" class="sb-out"><i class="bi bi-box-arrow-right"></i></a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div class="topbar-l">
      <h2>My Profile</h2>
      <p><?= date('l, F j, Y') ?></p>
    </div>
    <div class="topbar-r">
      <div class="notif-btn" onclick="toggleNotif()" id="notifBtn">
        <i class="bi bi-bell-fill"></i>
        <?php if (!empty($notifications)): ?><span class="notif-dot"></span><?php endif; ?>
      </div>
    
    </div>
  </div>

  <div class="notif-panel" id="notifPanel">
    <div class="notif-panel-head">
      <span class="notif-panel-title">Notifications</span>
      <span class="notif-count"><?= count($notifications) ?></span>
    </div>
    <div class="notif-list">
      <?php foreach ($notifications as $n): ?>
      <div class="notif-item">
        <div class="notif-icon-wrap" style="background:<?= $n['bg'] ?>;color:<?= $n['color'] ?>;"><i class="bi <?= $n['icon'] ?>"></i></div>
        <div>
          <div class="nt-title"><?= htmlspecialchars($n['title']) ?></div>
          <div class="nt-msg"><?= htmlspecialchars($n['msg']) ?></div>
          <div class="nt-time"><?= htmlspecialchars($n['time']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="notif-footer"><a href="#">Mark all as read</a></div>
  </div>

  <div class="content">
    <?php if ($success): ?><div class="alert alert-ok"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-err"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- HERO -->
    <div class="hero">
      <div class="hero-av"><?= $avatar_ltr ?></div>
      <div class="hero-info">
        <div class="hero-name"><?= htmlspecialchars($user['name']) ?></div>
        <div class="hero-pills">
          <span class="hpill"><i class="bi bi-envelope"></i> <?= htmlspecialchars($user['email']) ?></span>
          <?php if ($user['gender']): ?><span class="hpill"><i class="bi bi-<?= $is_female ? 'gender-female' : 'gender-male' ?>"></i> <?= ucfirst($user['gender']) ?></span><?php endif; ?>
          <?php if ($age): ?><span class="hpill"><i class="bi bi-cake"></i> <?= $age ?> yrs</span><?php endif; ?>
          <span class="hpill"><i class="bi bi-calendar-check"></i> Since <?= $member_since ?></span>
          <?php if ($goals): ?><span class="hpill"><i class="bi bi-bullseye"></i> <?= $goal_labels[$goals['goal_type'] ?? ''] ?? '' ?></span><?php endif; ?>
        </div>
      </div>
      <div class="hero-stats">
        <div class="hstat"><div class="hstat-val"><?= $total_workout_days ?></div><div class="hstat-lbl">Days Done</div></div>
        <div class="hstat"><div class="hstat-val"><?= number_format($total_burned) ?></div><div class="hstat-lbl">kcal</div></div>
        <div class="hstat"><div class="hstat-val"><?= $total_cal_logs ?></div><div class="hstat-lbl">Meal Logs</div></div>
      </div>
    </div>

    <!-- TABS -->
    <div class="tabs">
      <button class="tab active" onclick="switchTab('personal',this)"><i class="bi bi-person"></i> Personal</button>
      <button class="tab" onclick="switchTab('health',this)"><i class="bi bi-heart-pulse"></i> Health</button>
      <button class="tab" onclick="switchTab('goal',this)"><i class="bi bi-bullseye"></i> Goal</button>
      <button class="tab" onclick="switchTab('security',this)"><i class="bi bi-shield-lock"></i> Security</button>
    </div>

    <!-- PERSONAL -->
    <div class="tab-panel active" id="panel-personal">
      <div class="card">
        <div class="card-title"><i class="bi bi-person-fill"></i> Personal Information</div>
        <form method="POST">
          <input type="hidden" name="action" value="update_profile">
          <div class="form-row">
            <div class="fg"><label>Full Name</label><input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required></div>
            <div class="fg"><label>Email</label><input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled></div>
          </div>
          <div class="form-row three">
            <div class="fg">
              <label>Gender</label>
              <select name="gender">
                <option value="">Prefer not to say</option>
                <option value="male" <?= ($user['gender']??'')==='male'?'selected':'' ?>>Male</option>
                <option value="female" <?= ($user['gender']??'')==='female'?'selected':'' ?>>Female</option>
                <option value="other" <?= ($user['gender']??'')==='other'?'selected':'' ?>>Other</option>
              </select>
            </div>
            <div class="fg"><label>Birthdate</label><input type="date" name="birthdate" value="<?= htmlspecialchars($user['birthdate'] ?? '') ?>"></div>
            <div class="fg"><label>Height (cm)</label><input type="number" name="height_cm" step="0.1" value="<?= $user['height_cm'] ?? '' ?>" placeholder="170"></div>
          </div>
          <div class="form-row" style="max-width:200px;">
            <div class="fg"><label>Weight (kg)</label><input type="number" name="weight_kg" step="0.1" value="<?= $user['weight_kg'] ?? '' ?>" placeholder="65"></div>
          </div>
          <button type="submit" class="btn-save"><i class="bi bi-check-lg"></i> Save Changes</button>
        </form>
      </div>
    </div>

    <!-- HEALTH -->
    <div class="tab-panel" id="panel-health">
      <div class="card">
        <div class="card-title"><i class="bi bi-heart-pulse-fill"></i> Health Summary</div>
        <?php if ($bmi):
          $bval = (float)$bmi['bmi'];
          $bclr = $bval < 18.5 ? '#2563eb' : ($bval < 25 ? '#16a34a' : ($bval < 30 ? '#f97316' : '#dc2626'));
        ?>
        <div class="bmi-row">
          <div>
            <div class="bmi-num" style="color:<?= $bclr ?>"><?= number_format($bval,1) ?></div>
            <div class="bmi-badge" style="background:<?= $bclr ?>18;color:<?= $bclr ?>"><?= htmlspecialchars($bmi['category'] ?? '') ?></div>
          </div>
          <div>
            <div style="font-size:13px;font-weight:600;color:#1c1917;margin-bottom:2px;">Latest BMI</div>
            <div style="font-size:11px;color:#78716c;"><?= !empty($bmi['computed']) ? 'Computed from profile' : 'Recorded: '.date('F j, Y', strtotime($bmi['logged_at'])) ?></div>
            <div style="font-size:11px;color:#78716c;margin-top:2px;"><?= $bmi['weight_kg'] ?> kg · <?= $bmi['height_cm'] ?> cm</div>
          </div>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:20px;color:#78716c;font-size:12px;"><i class="bi bi-heart-pulse" style="font-size:24px;display:block;margin-bottom:6px;opacity:.3;"></i>No BMI records yet.</div>
        <?php endif; ?>
        <div class="info-grid">
          <div class="iitem"><div class="iitem-icon" style="background:#eff6ff;color:#2563eb;"><i class="bi bi-rulers"></i></div><div><div class="iitem-label">Height</div><div class="iitem-value"><?= $user['height_cm'] ? $user['height_cm'].' cm' : '—' ?></div></div></div>
          <div class="iitem"><div class="iitem-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-speedometer2"></i></div><div><div class="iitem-label">Weight</div><div class="iitem-value"><?= $user['weight_kg'] ? $user['weight_kg'].' kg' : '—' ?></div></div></div>
          <div class="iitem"><div class="iitem-icon" style="background:#fee2e2;color:#dc2626;"><i class="bi bi-fire"></i></div><div><div class="iitem-label">Est. kcal Burned</div><div class="iitem-value"><?= number_format($total_burned) ?></div></div></div>
          <div class="iitem"><div class="iitem-icon" style="background:#fdf4ff;color:#9333ea;"><i class="bi bi-trophy-fill"></i></div><div><div class="iitem-label">Workout Days</div><div class="iitem-value"><?= $total_workout_days ?> / 30</div><div class="prog-wrap"><div class="prog-fill" style="width:<?= round($total_workout_days/30*100) ?>%"></div></div></div></div>
        </div>
      </div>
    </div>

    <!-- GOAL -->
    <div class="tab-panel" id="panel-goal">
      <div class="card">
        <div class="card-title"><i class="bi bi-bullseye"></i> Fitness Goal & Plan</div>
        <div class="info-grid">
          <div class="iitem"><div class="iitem-icon" style="background:#f0fdf4;color:#16a34a;"><i class="bi bi-flag-fill"></i></div><div><div class="iitem-label">Goal</div><div class="iitem-value"><?= $goal_labels[$goals['goal_type'] ?? ''] ?? '—' ?></div></div></div>
          <div class="iitem"><div class="iitem-icon" style="background:#fff7ed;color:#f97316;"><i class="bi bi-fire"></i></div><div><div class="iitem-label">Daily Calories</div><div class="iitem-value"><?= $goals['daily_calories'] ?? '—' ?> kcal</div></div></div>
          <div class="iitem"><div class="iitem-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-egg-fried"></i></div><div><div class="iitem-label">Daily Protein</div><div class="iitem-value"><?= $goals['daily_protein_g'] ?? '—' ?>g</div></div></div>
          <div class="iitem"><div class="iitem-icon" style="background:#eff6ff;color:#2563eb;"><i class="bi bi-bar-chart-steps"></i></div><div><div class="iitem-label">Fitness Level</div><div class="iitem-value"><?= $level_labels[$fitness['fitness_level'] ?? ''] ?? '—' ?></div></div></div>
          <div class="iitem"><div class="iitem-icon" style="background:#fdf4ff;color:#9333ea;"><i class="bi bi-calendar-week"></i></div><div><div class="iitem-label">Days/Week</div><div class="iitem-value"><?= $fitness['days_per_week'] ?? '—' ?> days</div></div></div>
          <div class="iitem"><div class="iitem-icon" style="background:#f0fdf4;color:#16a34a;"><i class="bi bi-activity"></i></div><div><div class="iitem-label">Activity Level</div><div class="iitem-value" style="font-size:12px;"><?= str_replace('_',' ',$fitness['activity_level'] ?? '—') ?></div></div></div>
        </div>
        <p style="font-size:11px;color:#78716c;margin-top:12px;display:flex;align-items:center;gap:5px;"><i class="bi bi-info-circle" style="color:#16a34a;"></i> To update your goal, re-do the <a href="/myfitcal_system/setup/step1-profile.php" style="color:#1c1917;font-weight:600;">Setup steps</a>.</p>
      </div>
    </div>

    <!-- SECURITY -->
    <div class="tab-panel" id="panel-security">
      <div class="card">
        <div class="card-title"><i class="bi bi-shield-lock-fill"></i> Change Password</div>
        <form method="POST" style="max-width:380px;">
          <input type="hidden" name="action" value="change_password">
          <div class="fg"><label>Current Password</label><input type="password" name="current_password" required placeholder="Enter current password"></div>
          <div class="fg"><label>New Password</label><input type="password" name="new_password" required placeholder="Min. 6 characters"></div>
          <div class="fg"><label>Confirm New Password</label><input type="password" name="confirm_password" required placeholder="Repeat new password"></div>
          <button type="submit" class="btn-save btn-danger"><i class="bi bi-lock-fill"></i> Change Password</button>
        </form>
      </div>
    </div>

  </div>
</div>

<script>
function switchTab(name, el) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.getElementById('panel-' + name).classList.add('active');
  el.classList.add('active');
}
function toggleNotif() {
  document.getElementById('notifPanel').classList.toggle('show');
}
document.addEventListener('click', function(e) {
  var panel = document.getElementById('notifPanel');
  var btn = document.getElementById('notifBtn');
  if (!panel.contains(e.target) && !btn.contains(e.target)) panel.classList.remove('show');
});
</script>
</body>
</html>