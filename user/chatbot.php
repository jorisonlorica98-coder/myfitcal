<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$db = getDB();

$uq = $db->prepare("SELECT name, gender FROM users WHERE id=?");
$uq->execute([$user_id]);
$user = $uq->fetch();
$is_female = strtolower($user['gender'] ?? 'male') === 'female';
$first_name = htmlspecialchars(explode(' ', $user['name'])[0]);

$history = [];
try {
    $db->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        role ENUM('user','assistant') NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $hq = $db->prepare("SELECT role, message, created_at FROM chat_messages
        WHERE user_id=? ORDER BY created_at DESC LIMIT 30");
    $hq->execute([$user_id]);
    $history = array_reverse($hq->fetchAll());
} catch(Exception $e) {}

$gq = $db->prepare("SELECT goal_type FROM user_goals WHERE user_id=?");
$gq->execute([$user_id]);
$goal_type = $gq->fetchColumn() ?: 'maintain';
$goal_map = ['lose'=>'Weight Loss','gain'=>'Weight Gain','maintain'=>'Maintenance','muscle'=>'Muscle Gain'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>FitBot — MyFitCal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;font-family:'DM Sans',sans-serif;background:#f5f5f4;color:#1c1917;}
body{display:flex;}

/* ── SIDEBAR — exact match sa dashboard ── */
.sidebar{
  position:fixed;left:0;top:0;bottom:0;width:220px;
  background:#1c1917;
  display:flex;flex-direction:column;
  z-index:200;overflow:hidden;
}
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
.sb-foot{
  padding:10px 14px;border-top:1px solid rgba(255,255,255,.06);
  display:flex;align-items:center;gap:9px;
  flex-shrink:0;
}
.sb-av{width:28px;height:28px;border-radius:50%;background:#292524;color:#e7e5e4;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;flex-shrink:0;}
.sb-uname{font-size:12px;font-weight:500;color:#e7e5e4;}
.sb-role{font-size:10px;color:#78716c;}
.sb-out{margin-left:auto;color:#57534e;text-decoration:none;font-size:15px;transition:color .12s;}
.sb-out:hover{color:#f87171;}

/* ── MAIN LAYOUT ── */
.main{margin-left:220px;min-height:100vh;display:flex;flex-direction:column;width:calc(100% - 220px);}

/* ── TOPBAR — exact match sa dashboard ── */
.topbar{
  background:#fff;border-bottom:1px solid #e7e5e4;
  padding:12px 24px;
  display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:50;flex-shrink:0;
}
.topbar-l h2{font-size:14px;font-weight:600;color:#1c1917;}
.topbar-l p{font-size:12px;color:#78716c;margin-top:1px;}
.tb-btn{
  display:inline-flex;align-items:center;gap:5px;
  padding:6px 12px;border-radius:6px;
  border:1px solid #e7e5e4;background:#fff;
  font-family:'DM Sans',sans-serif;
  font-size:12px;font-weight:500;color:#78716c;
  text-decoration:none;transition:all .12s;
}
.tb-btn:hover{border-color:#1c1917;color:#1c1917;}

/* ── CONTENT AREA ── */
.content{
  flex:1;display:flex;gap:16px;
  padding:24px;overflow:hidden;
  min-height:0;
}

/* ── CHAT SIDEBAR ── */
.chat-sidebar{width:260px;flex-shrink:0;display:flex;flex-direction:column;gap:12px;overflow-y:auto;}

.bot-card{background:#fff;border-radius:8px;border:1px solid #e7e5e4;padding:18px;text-align:center;}
.bot-avatar{
  width:56px;height:56px;border-radius:12px;
  background:#1c1917;
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 12px;font-size:24px;color:#fff;
}
.bot-name{font-size:15px;font-weight:700;color:#1c1917;margin-bottom:4px;}
.bot-desc{font-size:11px;color:#78716c;line-height:1.6;}
.bot-status{
  display:inline-flex;align-items:center;gap:5px;
  margin-top:10px;background:#f0fdf4;border:1px solid #bbf7d0;
  color:#15803d;font-size:11px;font-weight:600;
  padding:3px 10px;border-radius:999px;
}
.status-dot{width:6px;height:6px;border-radius:50%;background:#16a34a;animation:pulse 1.5s ease-in-out infinite;}
@keyframes pulse{0%,100%{opacity:1;}50%{opacity:.3;}}

.suggest-card{background:#fff;border-radius:8px;border:1px solid #e7e5e4;padding:14px;}
.suggest-title{font-size:11px;font-weight:700;color:#1c1917;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;display:flex;align-items:center;gap:5px;}
.suggest-title i{color:#16a34a;}
.suggest-btn{
  display:flex;align-items:flex-start;gap:6px;width:100%;
  padding:8px 10px;border-radius:6px;
  border:1px solid #e7e5e4;background:#fff;
  font-family:'DM Sans',sans-serif;font-size:12px;font-weight:500;color:#78716c;
  cursor:pointer;text-align:left;transition:all .12s;margin-bottom:5px;line-height:1.4;
}
.suggest-btn:last-child{margin-bottom:0;}
.suggest-btn:hover{border-color:#16a34a;color:#16a34a;background:#f0fdf4;}
.suggest-btn i{color:#16a34a;font-size:12px;margin-top:1px;flex-shrink:0;}

.clear-btn{
  display:flex;align-items:center;justify-content:center;gap:5px;
  width:100%;padding:8px;border-radius:6px;
  border:1px solid #fecaca;background:#fff;
  font-family:'DM Sans',sans-serif;font-size:12px;font-weight:600;color:#dc2626;
  cursor:pointer;transition:all .12s;
}
.clear-btn:hover{background:#fef2f2;}

/* ── CHAT MAIN ── */
.chat-main{
  flex:1;display:flex;flex-direction:column;
  background:#fff;border-radius:8px;border:1px solid #e7e5e4;
  overflow:hidden;min-width:0;
}

.chat-header{
  padding:12px 16px;border-bottom:1px solid #e7e5e4;
  display:flex;align-items:center;justify-content:space-between;
  flex-shrink:0;
}
.ch-left{display:flex;align-items:center;gap:10px;}
.ch-avatar{
  width:36px;height:36px;border-radius:8px;
  background:#1c1917;
  display:flex;align-items:center;justify-content:center;
  font-size:16px;color:#fff;
}
.ch-name{font-size:13px;font-weight:600;color:#1c1917;}
.ch-sub{font-size:11px;color:#78716c;}
.ch-online{display:flex;align-items:center;gap:5px;font-size:11px;font-weight:600;color:#15803d;}
.ch-online-dot{width:7px;height:7px;border-radius:50%;background:#16a34a;animation:pulse 1.5s ease-in-out infinite;}

/* ── MESSAGES ── */
.chat-messages{
  flex:1;overflow-y:auto;
  padding:16px;display:flex;flex-direction:column;gap:10px;
  scroll-behavior:smooth;min-height:0;
}
.chat-messages::-webkit-scrollbar{width:4px;}
.chat-messages::-webkit-scrollbar-thumb{background:#e7e5e4;border-radius:99px;}

.welcome-state{
  display:flex;flex-direction:column;
  align-items:center;justify-content:center;
  flex:1;text-align:center;padding:32px 16px;
}
.ws-icon{
  width:64px;height:64px;border-radius:16px;
  background:#1c1917;
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 14px;font-size:28px;color:#fff;
}
.ws-title{font-size:18px;font-weight:700;color:#1c1917;margin-bottom:6px;}
.ws-title span{color:#16a34a;}
.ws-sub{font-size:12px;color:#78716c;line-height:1.65;max-width:320px;}

.msg{display:flex;gap:8px;align-items:flex-end;max-width:80%;}
.msg.user{align-self:flex-end;flex-direction:row-reverse;}
.msg.bot{align-self:flex-start;}
.msg-av{
  width:28px;height:28px;border-radius:50%;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  font-size:11px;font-weight:700;
}
.msg.bot .msg-av{background:#1c1917;color:#fff;}
.msg.user .msg-av{background:#16a34a;color:#fff;}
.msg-bubble{padding:10px 13px;border-radius:12px;font-size:13px;line-height:1.6;}
.msg.bot .msg-bubble{
  background:#f5f5f4;color:#1c1917;
  border-bottom-left-radius:3px;border:1px solid #e7e5e4;
}
.msg.user .msg-bubble{
  background:#1c1917;color:#fafaf9;
  border-bottom-right-radius:3px;
}
.msg-time{font-size:10px;color:#a8a29e;margin-top:3px;padding:0 3px;}
.msg.user .msg-time{text-align:right;}
.msg-wrap{display:flex;flex-direction:column;}
.msg.user .msg-wrap{align-items:flex-end;}
.msg.bot .msg-bubble strong{font-weight:700;color:#1c1917;}

/* Typing indicator */
.typing-indicator{display:none;align-self:flex-start;}
.typing-indicator.show{display:flex;}
.typing-bubble{
  background:#f5f5f4;border:1px solid #e7e5e4;
  border-radius:12px;border-bottom-left-radius:3px;
  padding:10px 14px;display:flex;gap:4px;align-items:center;
}
.typing-dot{width:6px;height:6px;border-radius:50%;background:#a8a29e;animation:typingDot 1.4s ease-in-out infinite;}
.typing-dot:nth-child(2){animation-delay:.2s;}
.typing-dot:nth-child(3){animation-delay:.4s;}
@keyframes typingDot{0%,60%,100%{transform:translateY(0);}30%{transform:translateY(-6px);}}

/* ── INPUT ── */
.chat-input-area{padding:12px 16px;border-top:1px solid #e7e5e4;flex-shrink:0;}
.input-row{display:flex;gap:8px;align-items:flex-end;}
.input-box{
  flex:1;border:1px solid #e7e5e4;border-radius:8px;
  padding:10px 12px;font-family:'DM Sans',sans-serif;
  font-size:13px;color:#1c1917;background:#fafaf9;
  resize:none;outline:none;transition:all .12s;
  min-height:42px;max-height:110px;line-height:1.5;
}
.input-box:focus{border-color:#1c1917;background:#fff;}
.input-box::placeholder{color:#a8a29e;}
.send-btn{
  width:42px;height:42px;border-radius:8px;border:none;flex-shrink:0;
  background:#1c1917;color:#fff;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  font-size:15px;transition:all .12s;
}
.send-btn:hover{background:#292524;}
.send-btn:disabled{opacity:.4;cursor:not-allowed;}
.input-hint{font-size:10px;color:#a8a29e;margin-top:6px;text-align:center;}

@media(max-width:768px){
  .chat-sidebar{display:none;}
  .main{margin-left:0;width:100%;}
  
}

/* ── RESPONSIVE MOBILE ── */
.mob-bar{display:none;}
.mob-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:250;}
.mob-overlay.show{display:block;}
@media(max-width:768px){
  .mob-bar{display:flex;align-items:center;justify-content:space-between;position:fixed;top:0;left:0;right:0;z-index:200;background:#1c1917;padding:10px 16px;height:52px;}
  .mob-bar-brand{display:flex;align-items:center;gap:8px;}
  .mob-bar-brand img{width:26px;height:26px;border-radius:5px;object-fit:contain;}
  .mob-bar-brand span{font-size:14px;font-weight:600;color:#fafaf9;}
  .mob-hamburger{background:none;border:none;color:#fafaf9;font-size:22px;cursor:pointer;padding:4px;display:flex;align-items:center;}
  .sidebar{transform:translateX(-100%);transition:transform .25s ease;z-index:300;width:240px;}
  .sidebar.open{transform:translateX(0);}
  .main{margin-left:0 !important;padding-top:52px;width:100% !important;}
  .topbar{display:none;}
  .content{padding:16px !important;}
  body{overflow-x:hidden;}
}
</style>
</head>
<body>

<div class="mob-overlay" id="mobOverlay" onclick="closeSidebar()"></div>
<div class="mob-bar">
  <div class="mob-bar-brand">
    <img src="/myfitcal_system/assets/image/logo.png" alt="">
    <span>MyFitCal</span>
  </div>
  <button class="mob-hamburger" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
</div>
<script>
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('mobOverlay').classList.toggle('show');
}
function closeSidebar(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('mobOverlay').classList.remove('show');
}
document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('.sb-link').forEach(function(l){l.addEventListener('click',closeSidebar);});
});
</script>


<!-- ── SIDEBAR — exact match sa dashboard ── -->
<aside class="sidebar" id="sidebar">
  <div class="sb-top">
    <div class="sb-brand">
      <div class="sb-logo">
        <img src="/myfitcal_system/assets/image/logo.png" alt="MyFitCal">
      </div>
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
    <a href="/myfitcal_system/user/chatbot.php" class="sb-link active"><i class="bi bi-robot"></i> FitBot</a>
    <span class="sb-lbl">Account</span>
    <a href="/myfitcal_system/user/profile.php" class="sb-link"><i class="bi bi-person-circle"></i> My Profile</a>
  </nav>
  <div class="sb-foot">
    <div class="sb-av"><?= strtoupper(substr($_SESSION['name'] ?? ($user['name'] ?? 'U'),0,1)) ?></div>
    <div>
      <div class="sb-uname"><?= htmlspecialchars(explode(' ', $_SESSION['name'] ?? ($user['name'] ?? 'User'))[0]) ?></div>
      <div class="sb-role">Member</div>
    </div>
    <a href="/myfitcal_system/logout.php" class="sb-out"><i class="bi bi-box-arrow-right"></i></a>
  </div>
</aside>

<!-- ── MAIN ── -->
<div class="main">

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-l">
      <h2>FitBot</h2>
      <p>AI Fitness Assistant</p>
    </div>
    <a href="/myfitcal_system/user/<?= $is_female ? 'dashboard_female' : 'dashboard' ?>.php" class="tb-btn">
   
    </a>
  </div>

  <div class="content">

    <!-- CHAT SIDEBAR -->
    <aside class="chat-sidebar">
      <div class="bot-card">
        <div class="bot-avatar"><i class="bi bi-robot"></i></div>
        <div class="bot-name">FitBot</div>
        <div class="bot-desc">Your personal AI fitness assistant. Ask me anything about your workout, nutrition, or health goals.</div>
        <div class="bot-status"><div class="status-dot"></div> Online & Ready</div>
      </div>

      <div class="suggest-card">
        <div class="suggest-title"><i class="bi bi-stars"></i> Suggested Questions</div>
        <?php
        $suggestions = [
          ['icon'=>'bi-fire',       'text'=>'How many calories should I eat per day?'],
          ['icon'=>'bi-trophy',     'text'=>'What is my workout plan for today?'],
          ['icon'=>'bi-egg-fried',  'text'=>'What should I eat for my ' . ($goal_map[$goal_type] ?? 'fitness') . ' goal?'],
          ['icon'=>'bi-droplet',    'text'=>'How much water should I drink daily?'],
          ['icon'=>'bi-activity',   'text'=>'How can I improve my workout performance?'],
          ['icon'=>'bi-moon-stars', 'text'=>'How important is sleep for my fitness?'],
        ];
        foreach($suggestions as $s): ?>
        <button class="suggest-btn" onclick="sendSuggestion(this.dataset.msg)" data-msg="<?= htmlspecialchars($s['text']) ?>">
          <i class="bi <?= $s['icon'] ?>"></i><?= htmlspecialchars($s['text']) ?>
        </button>
        <?php endforeach; ?>
      </div>

      <button class="clear-btn" onclick="clearChat()">
        <i class="bi bi-trash3"></i> Clear Chat History
      </button>
    </aside>

    <!-- CHAT MAIN -->
    <div class="chat-main">
      <div class="chat-header">
        <div class="ch-left">
          <div class="ch-avatar"><i class="bi bi-robot"></i></div>
          <div>
            <div class="ch-name">FitBot</div>
            <div class="ch-sub">AI Fitness Assistant</div>
          </div>
        </div>
        <div class="ch-online"><div class="ch-online-dot"></div> Online</div>
      </div>

      <div class="chat-messages" id="chatMessages">
        <?php if (empty($history)): ?>
        <div class="welcome-state" id="welcomeState">
          <div class="ws-icon"><i class="bi bi-robot"></i></div>
          <div class="ws-title">Hello, <span><?= $first_name ?>!</span></div>
          <div class="ws-sub">I'm FitBot, your personal AI fitness assistant. Ask me anything about your workouts, nutrition, calorie targets, or health goals — I'm here to help!</div>
        </div>
        <?php else: ?>
        <?php foreach($history as $h):
          $is_user = $h['role'] === 'user';
          $time = date('g:i A', strtotime($h['created_at']));
          $initials = $is_user ? strtoupper(substr($user['name'],0,1)) : 'F';
        ?>
        <div class="msg <?= $is_user ? 'user' : 'bot' ?>">
          <div class="msg-av"><?= $initials ?></div>
          <div class="msg-wrap">
            <div class="msg-bubble"><?= nl2br(htmlspecialchars($h['message'])) ?></div>
            <div class="msg-time"><?= $time ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <div class="typing-indicator" id="typingIndicator">
          <div class="msg-av" style="background:#1c1917;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;">F</div>
          <div class="typing-bubble">
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
          </div>
        </div>
      </div>

      <div class="chat-input-area">
        <div class="input-row">
          <textarea
            id="chatInput"
            class="input-box"
            placeholder="Ask FitBot about your fitness, nutrition, or workout plan..."
            rows="1"
            onkeydown="handleKey(event)"
            oninput="autoResize(this)"
          ></textarea>
          <button class="send-btn" id="sendBtn" onclick="sendMessage()">
            <i class="bi bi-send-fill"></i>
          </button>
        </div>
        <div class="input-hint">FitBot answers questions about fitness, nutrition, and your MyFitCal program only.</div>
      </div>
    </div>

  </div>
</div>

<script>
const userInitial = '<?= strtoupper(substr($user['name'],0,1)) ?>';
let chatHistory = <?= json_encode(array_map(fn($h) => ['role'=>$h['role'],'content'=>$h['message']], $history)) ?>;

function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 110) + 'px';
}

function handleKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
}

function scrollBottom() {
  const c = document.getElementById('chatMessages');
  c.scrollTop = c.scrollHeight;
}

function addMessage(role, text, time) {
  const welcome = document.getElementById('welcomeState');
  if (welcome) welcome.remove();

  const isUser = role === 'user';
  const msgs   = document.getElementById('chatMessages');
  const typing = document.getElementById('typingIndicator');
  const now    = time || new Date().toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'});

  const div = document.createElement('div');
  div.className = 'msg ' + role;
  div.innerHTML = `
    <div class="msg-av">${isUser ? userInitial : 'F'}</div>
    <div class="msg-wrap">
      <div class="msg-bubble">${formatText(text)}</div>
      <div class="msg-time">${now}</div>
    </div>`;
  msgs.insertBefore(div, typing);
  scrollBottom();
}

function formatText(text) {
  return text
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>')
    .replace(/\n/g,'<br>');
}

function showTyping(show) {
  document.getElementById('typingIndicator').classList.toggle('show', show);
  if (show) scrollBottom();
}

async function sendMessage() {
  const input = document.getElementById('chatInput');
  const btn   = document.getElementById('sendBtn');
  const msg   = input.value.trim();
  if (!msg) return;

  input.value = '';
  input.style.height = 'auto';
  btn.disabled = true;

  addMessage('user', msg);
  chatHistory.push({role:'user', content:msg});
  showTyping(true);

  const startTime = Date.now();
  const minDelay  = 1200;

  try {
    const res = await fetch('/myfitcal_system/user/chat_api.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({message: msg, history: chatHistory})
    });
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); }
    catch(e) { throw new Error('Server returned non-JSON: ' + text.substring(0,100)); }

    const elapsed   = Date.now() - startTime;
    const remaining = Math.max(0, minDelay - elapsed);
    if (remaining > 0) await new Promise(r => setTimeout(r, remaining));

    showTyping(false);
    const reply = data.reply || data.error || "Sorry, I couldn't process that. Please try again.";
    addMessage('assistant', reply);
    chatHistory.push({role:'assistant', content:reply});

  } catch(e) {
    const elapsed   = Date.now() - startTime;
    const remaining = Math.max(0, minDelay - elapsed);
    if (remaining > 0) await new Promise(r => setTimeout(r, remaining));
    showTyping(false);
    addMessage('assistant', 'Connection error: ' + e.message + '. Please refresh and try again.');
  }

  btn.disabled = false;
  input.focus();
}

function sendSuggestion(text) {
  document.getElementById('chatInput').value = text;
  sendMessage();
}

async function clearChat() {
  if (!confirm('Clear all chat history?')) return;
  try {
    await fetch('/myfitcal_system/user/chat_clear.php', {method:'POST'});
    const msgs = document.getElementById('chatMessages');
    msgs.innerHTML = `
      <div class="welcome-state" id="welcomeState">
        <div class="ws-icon"><i class="bi bi-robot"></i></div>
        <div class="ws-title">Chat cleared!</div>
        <div class="ws-sub">Start a new conversation with FitBot anytime.</div>
      </div>
      <div class="typing-indicator" id="typingIndicator">
        <div class="msg-av" style="background:#1c1917;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;">F</div>
        <div class="typing-bubble">
          <div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>
        </div>
      </div>`;
    chatHistory = [];
  } catch(e) {}
}

scrollBottom();
</script>
</body>
</html>