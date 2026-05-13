<?php
require_once 'includes/auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? 'admin/dashboard.php' : 'user/dashboard.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>MyFitCal — Your 30-Day Fitness Journey</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --dark:#1c1917;
  --dark2:#292524;
  --dark3:#44403c;
  --cream:#f5f5f4;
  --border:#e7e5e4;
  --muted:#78716c;
  --muted2:#a8a29e;
  --green:#16a34a;
  --green-d:#15803d;
  --green-light:#f0fdf4;
  --green-soft:#bbf7d0;
  --white:#ffffff;
}
html{scroll-behavior:smooth;}
body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--dark);overflow-x:hidden;}

/* ── NAV ── */
nav{
  position:fixed;top:0;left:0;right:0;z-index:100;
  background:var(--dark);
  border-bottom:1px solid rgba(255,255,255,.06);
  padding:0 5%;height:64px;
  display:flex;align-items:center;justify-content:space-between;
}
.nav-brand{display:flex;align-items:center;gap:9px;}
.nav-logo{width:30px;height:30px;border-radius:6px;overflow:hidden;flex-shrink:0;}
.nav-logo img{width:100%;height:100%;object-fit:contain;}
.nav-name{font-size:16px;font-weight:600;color:#d6d3d1;}
.nav-links{display:flex;align-items:center;gap:4px;}
.nav-link{
  font-size:14px;font-weight:500;
  color:rgba(255,255,255,.45);
  text-decoration:none;padding:7px 13px;
  border-radius:6px;transition:all .12s;
}
.nav-link:hover,.nav-link.active{color:#e7e5e4;background:rgba(255,255,255,.08);}
.nav-actions{display:flex;align-items:center;gap:10px;}
.btn-primary{
  display:inline-flex;align-items:center;gap:7px;
  padding:8px 18px;border-radius:6px;
  background:var(--green);color:#fff;
  font-family:'DM Sans',sans-serif;
  font-size:13px;font-weight:600;
  text-decoration:none;transition:all .12s;
}
.btn-primary:hover{background:var(--green-d);}

/* ── HERO ── */
.hero{
  min-height:100vh;width:100%;
  background-color:var(--dark);
  background-image:url('assets/image/home.png'),url('assets/image/back.png');
  background-repeat:no-repeat,no-repeat;
  background-position:right bottom,center center;
  background-size:32%,cover;
  display:flex;flex-direction:column;
  align-items:flex-start;justify-content:center;
  text-align:left;padding:100px 8% 80px;
  position:relative;overflow:hidden;
}
.hero::before{
  content:'';position:absolute;inset:0;
  background:linear-gradient(to right,rgba(28,25,23,.92) 40%,rgba(28,25,23,.4) 70%,rgba(28,25,23,.1) 100%);
  pointer-events:none;
}
.hero-eyebrow{
  display:inline-flex;align-items:center;gap:7px;
  background:rgba(22,163,74,.08);
  border:1px solid rgba(22,163,74,.18);
  color:#4ade80;font-size:11px;font-weight:600;
  letter-spacing:1px;text-transform:uppercase;
  padding:6px 16px;border-radius:999px;
  margin-bottom:24px;
  animation:fadeUp .5s ease both;
  position:relative;z-index:1;
}
.pulse-dot{width:6px;height:6px;border-radius:50%;background:#4ade80;animation:pulse 1.5s ease-in-out infinite;}
@keyframes pulse{0%,100%{opacity:1;}50%{opacity:.25;}}
.hero-title{
  font-size:clamp(3rem,6.5vw,5rem);
  font-weight:700;color:#e7e5e4;
  line-height:1.1;letter-spacing:-.025em;
  margin-bottom:20px;
  animation:fadeUp .5s .1s ease both;
  position:relative;z-index:1;
}
.hero-title span{color:#4ade80;}
.hero-sub{
  font-size:clamp(1rem,1.8vw,1.15rem);
  color:rgba(255,255,255,.4);
  max-width:480px;line-height:1.8;
  margin:0 0 36px;
  animation:fadeUp .5s .2s ease both;
  position:relative;z-index:1;
}
.hero-btns{
  display:flex;align-items:center;gap:14px;
  flex-wrap:wrap;
  animation:fadeUp .5s .3s ease both;
  position:relative;z-index:1;
}
.hbtn-main{
  display:inline-flex;align-items:center;gap:8px;
  padding:12px 24px;border-radius:7px;
  background:var(--green);color:#fff;
  font-family:'DM Sans',sans-serif;
  font-size:14px;font-weight:600;
  text-decoration:none;transition:all .12s;
}
.hbtn-main:hover{background:var(--green-d);}
.hbtn-sec{
  display:inline-flex;align-items:center;gap:8px;
  padding:12px 24px;border-radius:7px;
  background:rgba(255,255,255,.05);
  color:rgba(255,255,255,.5);
  border:1px solid rgba(255,255,255,.1);
  font-family:'DM Sans',sans-serif;
  font-size:14px;font-weight:500;
  text-decoration:none;transition:all .12s;
}
.hbtn-sec:hover{background:rgba(255,255,255,.09);color:rgba(255,255,255,.75);}

.hero-stats{
  display:flex;align-items:center;
  border:1px solid rgba(255,255,255,.08);
  border-radius:8px;overflow:hidden;
  margin-top:56px;
  animation:fadeUp .5s .4s ease both;
  position:relative;z-index:1;
}
.hstat{padding:18px 34px;text-align:center;border-right:1px solid rgba(255,255,255,.06);}
.hstat:last-child{border-right:none;}
.hstat-val{font-size:24px;font-weight:700;color:#d6d3d1;line-height:1;}
.hstat-lbl{font-size:10px;color:rgba(255,255,255,.22);text-transform:uppercase;letter-spacing:.7px;margin-top:4px;}
.scroll-hint{position:absolute;bottom:24px;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.12);font-size:18px;animation:bounce 2s ease-in-out infinite;}
@keyframes bounce{0%,100%{transform:translateX(-50%) translateY(0);}50%{transform:translateX(-50%) translateY(7px);}}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}

/* ── SHARED SECTION ── */
.section{
  width:100%;
  padding:80px 5%;
}
.section-label{
  display:inline-flex;align-items:center;gap:7px;
  font-size:10px;font-weight:700;
  letter-spacing:1.2px;text-transform:uppercase;
  color:#4ade80;margin-bottom:12px;
}
.section-label::before{content:'';width:18px;height:2px;background:#4ade80;border-radius:2px;}
.section-title{
  font-size:clamp(1.9rem,3.8vw,2.7rem);
  font-weight:800;line-height:1.15;
  letter-spacing:-.02em;margin-bottom:12px;
}
.section-sub{font-size:13.5px;line-height:1.75;max-width:560px;}

/* ── FEATURES ── */
.features-section{
  background:var(--dark);
  width:100%;
  padding:80px 5%;
}
.features-header{text-align:center;margin-bottom:48px;}
.features-header .section-label{justify-content:center;}
.features-header .section-title{color:#d6d3d1;}
.features-header .section-sub{color:rgba(255,255,255,.3);margin:0 auto;}

.feat-cards{
  display:grid;
  grid-template-columns:repeat(5,1fr);
  gap:16px;
  width:100%;
}
.feat-item{
  position:relative;
  border-radius:10px;
  overflow:hidden;
  aspect-ratio:3/4;
  cursor:default;
  border:1px solid rgba(255,255,255,.07);
  transition:transform .2s ease, box-shadow .2s ease;
}
.feat-item:hover{
  transform:translateY(-4px);
  box-shadow:0 10px 28px rgba(0,0,0,.4);
}
.feat-item img{
  width:100%;height:100%;
  object-fit:cover;display:block;
  transition:transform .4s ease;
}
.feat-item:hover img{transform:scale(1.05);}
.feat-overlay{
  position:absolute;inset:0;
  background:linear-gradient(to top,rgba(0,0,0,.82) 0%,rgba(0,0,0,.2) 55%,rgba(0,0,0,.05) 100%);
  transition:background .25s;
}
.feat-item:hover .feat-overlay{
  background:linear-gradient(to top,rgba(22,163,74,.65) 0%,rgba(0,0,0,.2) 60%,transparent 100%);
}
.feat-border-ring{
  position:absolute;inset:0;border-radius:10px;
  border:2px solid rgba(22,163,74,0);
  transition:border-color .25s;pointer-events:none;
}
.feat-item:hover .feat-border-ring{border-color:rgba(22,163,74,.5);}
.feat-body{
  position:absolute;bottom:0;left:0;right:0;
  padding:14px 14px 16px;
}
.feat-icon-sm{
  width:34px;height:34px;border-radius:7px;
  background:rgba(22,163,74,.18);
  border:1px solid rgba(22,163,74,.25);
  display:flex;align-items:center;justify-content:center;
  font-size:15px;color:#4ade80;
  margin-bottom:8px;
}
.feat-item-title{font-size:13px;font-weight:700;color:#e7e5e4;margin-bottom:4px;}
.feat-item-desc{font-size:11px;color:rgba(255,255,255,.38);line-height:1.6;}

/* ── SERVICES ── */
.services-section{
  background:var(--dark);
  width:100%;
  padding:0 5% 80px;
}
.services-header{text-align:center;margin-bottom:44px;}
.services-header .section-label{justify-content:center;}
.services-header .section-title{color:#d6d3d1;}
.services-header .section-sub{color:rgba(255,255,255,.3);margin:8px auto 0;}

.services-grid{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:20px;
  width:100%;
}
.svc-card{
  position:relative;border-radius:10px;overflow:hidden;
  aspect-ratio:3/4;cursor:pointer;
  border:1px solid rgba(255,255,255,.07);
  transition:transform .2s ease, box-shadow .2s ease;
}
.svc-card:hover{transform:translateY(-4px);box-shadow:0 12px 32px rgba(0,0,0,.4);}
.svc-card img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .4s ease;}
.svc-card:hover img{transform:scale(1.05);}
.svc-overlay{
  position:absolute;inset:0;
  background:linear-gradient(to top,rgba(0,0,0,.78) 0%,rgba(0,0,0,.08) 60%,transparent 100%);
  transition:background .25s ease;
}
.svc-card:hover .svc-overlay{
  background:linear-gradient(to top,rgba(22,163,74,.68) 0%,rgba(0,0,0,.15) 60%,transparent 100%);
}
.svc-border-ring{
  position:absolute;inset:0;border-radius:10px;
  border:2px solid rgba(22,163,74,0);
  transition:border-color .25s ease;pointer-events:none;
}
.svc-card:hover .svc-border-ring{border-color:rgba(22,163,74,.55);}
.svc-label{
  position:absolute;bottom:0;left:0;right:0;
  padding:16px 18px;
  font-size:16px;font-weight:700;color:#e7e5e4;
  text-shadow:0 1px 4px rgba(0,0,0,.5);
}

/* ── HOW IT WORKS ── */
.how-section{
  background:#0f0e0d;
  width:100%;
  padding:80px 5%;
}
.how-header{text-align:center;margin-bottom:52px;}
.how-header .section-label{justify-content:center;}
.how-header .section-title{color:#d6d3d1;}
.how-header .section-sub{color:rgba(255,255,255,.3);margin:0 auto;}

.how-steps{
  display:flex;align-items:stretch;gap:0;
  width:100%;
}
.how-step{
  flex:1;display:flex;align-items:flex-start;gap:14px;
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.07);
  border-radius:10px;padding:28px 22px;
  transition:all .15s;
}
.how-step:hover{background:rgba(255,255,255,.06);border-color:rgba(22,163,74,.22);}
.how-step-num{
  width:40px;height:40px;border-radius:50%;
  background:var(--green);color:#fff;
  font-size:14px;font-weight:800;
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;
}
.how-step-icon{font-size:17px;color:#4ade80;margin-bottom:7px;display:block;}
.how-step-title{font-size:13px;font-weight:700;color:#d6d3d1;margin-bottom:5px;}
.how-step-desc{font-size:11.5px;color:rgba(255,255,255,.32);line-height:1.65;}
.how-arrow{
  flex-shrink:0;width:44px;
  display:flex;align-items:center;justify-content:center;
  color:rgba(22,163,74,.5);font-size:20px;
}

/* ── ABOUT ── */
.about-section{
  background:#111;
  width:100%;
  padding:80px 5%;
}
.about-inner{
  width:100%;
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:60px;
  align-items:center;
}
.about-img-wrap{
  position:relative;
  width:100%;
  display:flex;
  align-items:center;
  justify-content:center;
}
.about-img-wrap img{
  width:100%;
  max-width:100%;
  height:auto;
  display:block;
  object-fit:cover;
  border-radius:12px;
}
.about-img-badge{
  position:absolute;
  bottom:-10px;
  right:20px;
  background:#22c55e;
  color:#fff;
  font-size:12px;
  font-weight:700;
  padding:10px 15px;
  border-radius:8px;
}
.about-content p{
  font-size:14px;
  color:rgba(255,255,255,.6);
  line-height:1.7;
  margin-bottom:14px;
}
.about-features{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:10px;
  margin-top:20px;
}
.af-item{
  display:flex;
  align-items:center;
  gap:10px;
  background:rgba(255,255,255,.05);
  border:1px solid rgba(255,255,255,.08);
  border-radius:8px;
  padding:10px 14px;
}
.af-item i{color:#4ade80;font-size:14px;flex-shrink:0;}
.af-item span{
  font-size:12.5px;
  color:rgba(255,255,255,.7);
}

/* ── CONTACT ── */
.contact-section{
  background:var(--dark);
  width:100%;
  padding:80px 5%;
}
.contact-inner{
  width:100%;
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:60px;
  align-items:start;
}
.contact-info .section-label{color:#4ade80;}
.contact-info .section-label::before{background:#4ade80;}
.contact-info .section-title{color:#d6d3d1;}
.contact-info p{font-size:13.5px;color:rgba(255,255,255,.35);line-height:1.8;margin-bottom:24px;}
.cinfo-item{display:flex;align-items:center;gap:12px;margin-bottom:14px;}
.cinfo-icon{
  width:36px;height:36px;border-radius:8px;
  background:rgba(22,163,74,.1);border:1px solid rgba(22,163,74,.16);
  display:flex;align-items:center;justify-content:center;
  font-size:15px;color:#4ade80;flex-shrink:0;
}
.cinfo-label{font-size:10px;color:rgba(255,255,255,.24);text-transform:uppercase;letter-spacing:.6px;}
.cinfo-val{font-size:13px;color:rgba(255,255,255,.5);font-weight:500;margin-top:1px;}

.contact-form{
  background:rgba(255,255,255,.03);
  border:1px solid rgba(255,255,255,.07);
  border-radius:10px;padding:26px;
}
.form-group{margin-bottom:13px;}
.form-label{
  display:block;font-size:10px;font-weight:600;
  color:rgba(255,255,255,.35);
  text-transform:uppercase;letter-spacing:.6px;margin-bottom:5px;
}
.form-input,.form-textarea{
  width:100%;
  background:rgba(255,255,255,.05);
  border:1px solid rgba(255,255,255,.09);
  border-radius:7px;padding:9px 13px;
  font-family:'DM Sans',sans-serif;
  font-size:13px;color:#d6d3d1;outline:none;
  transition:border-color .15s;
}
.form-input::placeholder,.form-textarea::placeholder{color:rgba(255,255,255,.18);}
.form-input:focus,.form-textarea:focus{border-color:rgba(22,163,74,.4);}
.form-textarea{resize:vertical;min-height:108px;}
.btn-send{
  width:100%;background:var(--green);color:#fff;
  border:none;border-radius:7px;padding:11px;
  font-family:'DM Sans',sans-serif;
  font-size:13px;font-weight:700;
  cursor:pointer;transition:background .15s;
  display:flex;align-items:center;justify-content:center;gap:7px;
  margin-top:4px;
}
.btn-send:hover{background:var(--green-d);}

/* ── FOOTER ── */
footer{
  background:var(--dark);
  border-top:1px solid rgba(255,255,255,.05);
  padding:20px 5%;
  display:flex;align-items:center;
  justify-content:space-between;flex-wrap:wrap;gap:10px;
  width:100%;
}
.footer-brand{display:flex;align-items:center;gap:8px;}
.footer-logo{width:24px;height:24px;border-radius:5px;overflow:hidden;}
.footer-logo img{width:100%;height:100%;object-fit:contain;}
.footer-name{font-size:13px;font-weight:600;color:#a8a29e;}
.footer-copy{font-size:12px;color:rgba(255,255,255,.14);}
.footer-links{display:flex;gap:16px;}
.footer-links a{font-size:12px;color:rgba(255,255,255,.2);text-decoration:none;transition:color .12s;}
.footer-links a:hover{color:rgba(255,255,255,.5);}

/* ── REVEAL ── */
.reveal{opacity:0;transform:translateY(18px);transition:opacity .5s ease,transform .5s ease;}
.reveal.visible{opacity:1;transform:translateY(0);}
.d1{transition-delay:.09s;}.d2{transition-delay:.18s;}.d3{transition-delay:.27s;}.d4{transition-delay:.36s;}

/* ── RESPONSIVE ── */
@media(max-width:1100px){
  .feat-cards{grid-template-columns:repeat(3,1fr);}
  .how-steps{flex-direction:column;gap:10px;}
  .how-arrow{transform:rotate(90deg);}
}
@media(max-width:900px){
  .hero-stats{display:none;}
  .nav-links{display:none;}
  .services-grid{grid-template-columns:1fr 1fr;}
  .about-inner,.contact-inner{grid-template-columns:1fr;gap:36px;}
}
@media(max-width:640px){
  .feat-cards{grid-template-columns:1fr 1fr;}
  .services-grid{grid-template-columns:1fr;}
  .about-features{grid-template-columns:1fr;}
}
</style>
<script
  src="https://www.tuqlas.com/chatbot.js"
  data-key="tq_live_c206424d5e3dc4554470c750194af988f1e63a1c"
  data-api="https://www.tuqlas.com"
  defer
></script>
</head>
<body>

<!-- ── NAV ── -->
<nav>
  <div class="nav-brand">
    <div class="nav-logo"><img src="assets/image/logo.png" alt="MyFitCal"></div>
    <span class="nav-name">MyFitCal</span>
  </div>
  <div class="nav-links">
    <a href="#home"     class="nav-link active">Home</a>
    <a href="#features" class="nav-link">Features</a>
    <a href="#service"  class="nav-link">Service</a>
    <a href="#how"      class="nav-link">How It Works</a>
    <a href="#about"    class="nav-link">About</a>
    <a href="#contact"  class="nav-link">Contact</a>
  </div>
  <div class="nav-actions">
    <a href="login.php" class="btn-primary"><i class="bi bi-play-fill"></i> Sign In</a>
  </div>
</nav>

<!-- ── HERO ── -->
<section class="hero" id="home">
  <div class="hero-eyebrow"><span class="pulse-dot"></span> 30-Day Personalized Program</div>
  <h1 class="hero-title">MyFitCal<br><span>Fitness</span></h1>
  <p class="hero-sub">MyFitCal builds a personalized 30-day workout and meal plan tailored to your goals, fitness level, and lifestyle — no gym required.</p>
  <div class="hero-btns">
    <a href="register.php" class="hbtn-main"><i class="bi bi-play-fill"></i> Start for Free</a>
    <a href="#features"    class="hbtn-sec"><i class="bi bi-arrow-down"></i> Learn More</a>
  </div>
  <div class="hero-stats">
    <div class="hstat"><div class="hstat-val">30</div><div class="hstat-lbl">Day Program</div></div>
    <div class="hstat"><div class="hstat-val">4</div><div class="hstat-lbl">Goal Types</div></div>
    <div class="hstat"><div class="hstat-val">2</div><div class="hstat-lbl">Gender Plans</div></div>
    <div class="hstat"><div class="hstat-val">100%</div><div class="hstat-lbl">Free</div></div>
  </div>
  <div class="scroll-hint"><i class="bi bi-chevron-down"></i></div>
</section>

<!-- ── FEATURES ── -->
<section class="features-section" id="features">
  <div class="features-header reveal">
    <div class="section-label">Features</div>
    <h2 class="section-title">Everything you need to achieve your fitness goals.</h2>
    <p class="section-sub">From personalized workouts to AI-powered guidance — MyFitCal has everything to keep you on track.</p>
  </div>
  <div class="feat-cards">
    <div class="feat-item reveal d1">
      <img src="assets/image/Strength.png" alt="Workout Plans">
      <div class="feat-overlay"></div>
      <div class="feat-border-ring"></div>
      <div class="feat-body">
        <div class="feat-icon-sm"><i class="bi bi-lightning-charge-fill"></i></div>
        <div class="feat-item-title">Workout Plans</div>
        <div class="feat-item-desc">Personalized workout routines based on your goals and fitness level.</div>
      </div>
    </div>
    <div class="feat-item reveal d2">
      <img src="Meal.png/tofu.png" alt="Calorie Tracking">
      <div class="feat-overlay"></div>
      <div class="feat-border-ring"></div>
      <div class="feat-body">
        <div class="feat-icon-sm"><i class="bi bi-fire"></i></div>
        <div class="feat-item-title">Calorie Tracking</div>
        <div class="feat-item-desc">Track your daily calories and macros to stay within your target.</div>
      </div>
    </div>
    <div class="feat-item reveal d3">
      <img src="assets/image/email.png" alt="Email Notifications">
      <div class="feat-overlay"></div>
      <div class="feat-border-ring"></div>
      <div class="feat-body">
        <div class="feat-icon-sm"><i class="bi bi-envelope-fill"></i></div>
        <div class="feat-item-title">Email Notifications</div>
        <div class="feat-item-desc">Get Email Notifications about your upcoming workouts.</div>
      </div>
    </div>
    <div class="feat-item reveal d4">
      <img src="assets/image/ai.png" alt="AI Fitness Assistant">
      <div class="feat-overlay"></div>
      <div class="feat-border-ring"></div>
      <div class="feat-body">
        <div class="feat-icon-sm"><i class="bi bi-robot"></i></div>
        <div class="feat-item-title">User AI Fitness Assistant</div>
        <div class="feat-item-desc">Get answers, tips, and guidance from your AI fitness buddy.</div>
      </div>
    </div>
    <div class="feat-item reveal d4">
      <img src="assets/image/calendar.png" alt="Progress Calendar">
      <div class="feat-overlay"></div>
      <div class="feat-border-ring"></div>
      <div class="feat-body">
        <div class="feat-icon-sm"><i class="bi bi-calendar3"></i></div>
        <div class="feat-item-title">Progress Calendar</div>
        <div class="feat-item-desc">Visualize your 30-day journey and track your consistency every day.</div>
      </div>
    </div>
  </div>
</section>

<!-- ── SERVICE ── -->
<section class="services-section" id="service">
  <div class="services-header reveal">
    <div class="section-label">Our Services</div>
    <h2 class="section-title">What We Offer</h2>
    <p class="section-sub">Choose your path — from fat loss to muscle gain, we have a personalized plan ready for you.</p>
  </div>
  <div class="services-grid">
    <div class="svc-card reveal d1">
      <img src="assets/image/Physicalfitness.png" alt="Physical Fitness">
      <div class="svc-overlay"></div><div class="svc-border-ring"></div>
      <div class="svc-label">Physical Fitness</div>
    </div>
    <div class="svc-card reveal d2">
      <img src="assets/image/Gainweight.png" alt="Weightlifting">
      <div class="svc-overlay"></div><div class="svc-border-ring"></div>
      <div class="svc-label">Weightlifting</div>
    </div>
    <div class="svc-card reveal d3">
      <img src="assets/image/Strength.png" alt="Strength Training">
      <div class="svc-overlay"></div><div class="svc-border-ring"></div>
      <div class="svc-label">Strength Training</div>
    </div>
    <div class="svc-card reveal d2">
      <img src="assets/image/Fatloss.png" alt="Fat Loss">
      <div class="svc-overlay"></div><div class="svc-border-ring"></div>
      <div class="svc-label">Weight Loss</div>
    </div>
    <div class="svc-card reveal d3">
      <img src="assets/image/Musclegain.png" alt="Muscle Gain">
      <div class="svc-overlay"></div><div class="svc-border-ring"></div>
      <div class="svc-label">Muscle Gain</div>
    </div>
    <div class="svc-card reveal d4">
      <img src="assets/image/Running.png" alt="Running">
      <div class="svc-overlay"></div><div class="svc-border-ring"></div>
      <div class="svc-label">Maintenance</div>
    </div>
  </div>
</section>

<!-- ── HOW IT WORKS ── -->
<section class="how-section" id="how">
  <div class="how-header reveal">
    <div class="section-label">How It Works</div>
    <h2 class="section-title">Three simple steps to your best shape.</h2>
    <p class="section-sub">Getting started is easy. Follow these simple steps and let MyFitCal guide you.</p>
  </div>
  <div class="how-steps">
    <div class="how-step reveal d1">
      <div class="how-step-num">01</div>
      <div>
        <i class="bi bi-person-fill how-step-icon"></i>
        <div class="how-step-title">Create Your Profile</div>
        <div class="how-step-desc">Tell us about yourself — your goals, fitness level, activity level, and preferences.</div>
      </div>
    </div>
    <div class="how-arrow reveal d2"><i class="bi bi-arrow-right"></i></div>
    <div class="how-step reveal d2">
      <div class="how-step-num">02</div>
      <div>
        <i class="bi bi-clipboard2-check-fill how-step-icon"></i>
        <div class="how-step-title">Get Your Personalized Plan</div>
        <div class="how-step-desc">We generate a 30-day workout and meal plan tailored just for you.</div>
      </div>
    </div>
    <div class="how-arrow reveal d3"><i class="bi bi-arrow-right"></i></div>
    <div class="how-step reveal d3">
      <div class="how-step-num">03</div>
      <div>
        <i class="bi bi-trophy-fill how-step-icon"></i>
        <div class="how-step-title">Track &amp; Achieve</div>
        <div class="how-step-desc">Follow your plan, track your progress, and achieve your fitness goals.</div>
      </div>
    </div>
  </div>
</section>

<!-- ── ABOUT ── -->
<section class="about-section" id="about">
  <div class="about-inner">
    <div class="about-img-wrap reveal">
      <img src="assets/image/about.png" alt="About MyFitCal">
      <div class="about-img-badge">🏆 30-Day Program</div>
    </div>
    <div class="about-content reveal d2">
      <div class="section-label">About Us</div>
      <h2 class="section-title" style="color:#d6d3d1;">Built for real people<br>with <span style="color:#4ade80;">real goals.</span></h2>
      <p>MyFitCal is a web portal designed for personalized monitoring of your fitness activities and calorie intake. Manage your health journey with real-time progress tracking and detailed analytics — all in one place.</p>
      <p>Receive email notifications and guidance tailored to your goals. Keep on top of your daily workouts and nutrition with accurate reminders, making fitness management easy and motivating.</p>
      <p>Experience a smarter way to achieve your fitness objectives — from tracking exercise routines to monitoring calorie consumption — all integrated online for your convenience.</p>
      <div class="about-features">
        <div class="af-item"><i class="bi bi-check-circle-fill"></i><span>Personalized Plans</span></div>
        <div class="af-item"><i class="bi bi-check-circle-fill"></i><span>Calorie Tracking</span></div>
        <div class="af-item"><i class="bi bi-check-circle-fill"></i><span>Email Notifications</span></div>
        <div class="af-item"><i class="bi bi-check-circle-fill"></i><span>AI Assistant</span></div>
        <div class="af-item"><i class="bi bi-check-circle-fill"></i><span>Male &amp; Female Plans</span></div>
        <div class="af-item"><i class="bi bi-check-circle-fill"></i><span>Progress Calendar</span></div>
      </div>
    </div>
  </div>
</section>

<!-- ── CONTACT ── -->
<section class="contact-section" id="contact">
  <div class="contact-inner">
    <div class="contact-info reveal">
      <div class="section-label">Contact Us</div>
      <h2 class="section-title" style="color:#d6d3d1;">We'd love to<br><span style="color:#4ade80;">hear from you!</span></h2>
      <p>Have questions, feedback, or suggestions? Send us a message and we'll get back to you.</p>
      <div class="cinfo-item">
        <div class="cinfo-icon"><i class="bi bi-envelope-fill"></i></div>
        <div>
          <div class="cinfo-label">Email</div>
          <div class="cinfo-val">myfitcal@gmail.com</div>
        </div>
      </div>
      <div class="cinfo-item">
        <div class="cinfo-icon"><i class="bi bi-geo-alt-fill"></i></div>
        <div>
          <div class="cinfo-label">Location</div>
          <div class="cinfo-val">Philippines</div>
        </div>
      </div>
      <div class="cinfo-item">
        <div class="cinfo-icon"><i class="bi bi-clock-fill"></i></div>
        <div>
          <div class="cinfo-label">Response Time</div>
          <div class="cinfo-val">We typically reply within 24 hours.</div>
        </div>
      </div>
    </div>
    <div class="contact-form reveal d2">
      <div class="form-group">
        <label class="form-label">Your Name</label>
        <input class="form-input" type="text" placeholder="Enter your name">
      </div>
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input class="form-input" type="email" placeholder="Enter your email">
      </div>
      <div class="form-group">
        <label class="section-label" style="display:block;text-transform:uppercase;letter-spacing:.6px;font-size:10px;color:rgba(255,255,255,.35);font-weight:600;margin-bottom:5px;">Subject</label>
        <input class="form-input" type="text" placeholder="Enter subject">
      </div>
      <div class="form-group">
        <label class="form-label">Message</label>
        <textarea class="form-textarea" placeholder="Type your message..."></textarea>
      </div>
      <button class="btn-send"><i class="bi bi-send-fill"></i> Send Message</button>
    </div>
  </div>
</section>


<script>
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('visible'); observer.unobserve(e.target); }
  });
}, { threshold: 0.1 });
document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

const sections = document.querySelectorAll('section[id]');
const navLinks = document.querySelectorAll('.nav-link');
window.addEventListener('scroll', () => {
  let current = 'home';
  sections.forEach(s => { if (window.scrollY >= s.offsetTop - 80) current = s.id; });
  navLinks.forEach(l => {
    l.classList.toggle('active', l.getAttribute('href') === '#' + current);
  });
});
</script>
</body>
</html>