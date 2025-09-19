<?php /* ======================== /views/layout.php ==================== */ ?>
<?php
function render_layout($title, $content){
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($title)?></title>
<style>
:root{--bg:#0f172a;--card:#111827;--muted:#94a3b8;--text:#e5e7eb;--accent:#60a5fa;--danger:#f87171;--ok:#4ade80;--border:#1f2937}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial}
.wrap{max-width:1100px;margin:0 auto;padding:24px}header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.card{background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.03));border:1px solid var(--border);border-radius:16px;padding:18px}
.btn{appearance:none;border:0;border-radius:12px;padding:10px 14px;background:var(--accent);color:#0b1220;font-weight:700;cursor:pointer}
.btn.ghost{background:transparent;border:1px solid #334155;color:var(--text)}.btn.danger{background:var(--danger);color:#111827}
input,select{background:#0b1220;border:1px solid #263244;color:#e5e7eb;padding:10px 12px;border-radius:10px}
.grid{display:grid;gap:12px}.grid.cols-2{grid-template-columns:repeat(2,minmax(0,1fr))}
.slot{display:flex;align-items:center;justify-content:space-between;padding:12px;border:1px solid var(--border);border-radius:12px}
.slot.busy{opacity:.55}.kicker{color:#cbd5e1;margin:6px 0 12px}
nav a{margin-right:10px;color:#93c5fd;text-decoration:none}.active{font-weight:700;text-decoration:underline}
</style>
</head><body><div class="wrap">
<header>
<div>
<h1 style="margin:0 0 6px 0;">Lab Scheduler</h1>
<nav>
<?php if(!empty($_SESSION['uid'])): ?>
<a href="?route=home" class="<?=($_GET['route']??'home')==='home'?'active':''?>">Home</a>
<?php if($_SESSION['role']==='admin'): ?><a href="?route=admin" class="<?=($_GET['route']??'')==='admin'?'active':''?>">Admin</a><?php endif; ?>
<a href="?route=logout">Logout (<?=h($_SESSION['username'])?>)</a>
<?php else: ?>
<a href="?route=login" class="<?=($_GET['route']??'')==='login'?'active':''?>">Login</a>
<a href="?route=register" class="<?=($_GET['route']??'')==='register'?'active':''?>">Register</a>
<?php endif; ?>
</nav>
</div>
</header>
<?= $content ?>
</div></body></html>
<?php }
?>
