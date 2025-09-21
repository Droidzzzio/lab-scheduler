<?php
// app/bootstrap.php

// Show errors while developing
error_reporting(E_ALL);
// Simple file logger
$logDir = __DIR__ . '/../storage/logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }
ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/app.log');
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment variables (from .env if you’re using vlucas/phpdotenv)
// If you’re not using dotenv, you can hardcode your config here.
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('DB_NAME') ?: 'lab_scheduler';
$DB_USER = getenv('DB_USER') ?: 'lab_user';
$DB_PASS = getenv('DB_PASS') ?: 'Droidzz@123';

// Simple DB wrapper using PDO
class DB {
    public static $pdo;

    public static function init($host, $dbname, $user, $pass) {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}
DB::init($DB_HOST, $DB_NAME, $DB_USER, $DB_PASS);

// Autoload classes (Booking, etc.)
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/' . $class . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Common helpers
function require_login() {
    if (empty($_SESSION['uid'])) {
        header("Location: ?route=login");
        exit;
    }
}

function is_admin() {
    if (!isset($_SESSION['uid'])) return false;
    $row = DB::$pdo->query("SELECT role FROM users WHERE id=".(int)$_SESSION['uid'])->fetch();
    return $row && $row['role'] === 'admin';
}

function current_track() {
    if (!isset($_SESSION['uid'])) return null;
    $row = DB::$pdo->query("SELECT track FROM users WHERE id=".(int)$_SESSION['uid'])->fetch();
    return $row['track'] ?? null;
}

function tz_ist() {
    return new DateTimeZone('Asia/Kolkata');
}

/**
 * Escape for safe HTML output.
 * Usage: <?= h($var) ?>
 */
function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
// UTC helper (some code may expect this)
function now_utc(): DateTime {
    return new DateTime('now', new DateTimeZone('UTC'));
}

/**
 * Minimal layout renderer.
 * Usage patterns it supports:
 *   render_layout('Login', function () { ?>
 *       <form>...</form>
 *   <?php });
 *
 * Or:
 *   $html = '<form>...</form>';
 *   render_layout('Login', $html);
 */



function render_layout(string $title, $body, ?string $flash = null, string $active = 'home'): void {
    // swallow any accidental output before layout
    if (ob_get_level() > 0) { @ob_clean(); }

    // capture body if closure
    if (is_callable($body)) { ob_start(); $body(); $content = ob_get_clean(); }
    else { $content = (string)$body; }

    $isLoggedIn = !empty($_SESSION['uid']);
    $is = fn(string $n) => $active === $n ? 'active' : '';

    echo "<!doctype html>
<html lang=\"en\">
<head>
<meta charset=\"utf-8\">
<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
<title>" . h($title) . "</title>
<style>
  *,*::before,*::after{ box-sizing:border-box; }
  :root{
    --bg1:#0b1224; --bg2:#0a2b7a; --bg3:#000814;
    --card:#0f162b; --card2:#111a34;
    --text:#e5e7eb; --muted:#9aa3b2;
    --accent:#60a5fa; --border:rgba(255,255,255,.08);
    --shadow:0 10px 30px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.03);
    --r:16px;
  }
  html,body{height:100%}
  body{
    margin:0; color:var(--text);
    font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Arial;
    background:
      radial-gradient(1200px 800px at 10% -10%, rgba(96,165,250,.15), transparent 60%),
      radial-gradient(900px 600px at 110% 10%, rgba(59,130,246,.12), transparent 60%),
      linear-gradient(135deg, var(--bg1), var(--bg2) 45%, var(--bg3) 100%);
    background-attachment:fixed;
  }

  /* top nav */
  .nav-wrap{ position:sticky; top:0; z-index:10; backdrop-filter:saturate(150%) blur(8px);
    background:linear-gradient(180deg, rgba(15,23,42,.65), rgba(15,23,42,.25));
    border-bottom:1px solid var(--border);
  }
  .nav{ max-width:1100px; margin:0 auto; padding:.8rem 1rem; display:flex; justify-content:space-between; align-items:center; }
  .brand{ font-weight:700; letter-spacing:.3px }
  .tabs{ display:flex; gap:.4rem; }
  .tab{ padding:.5rem .9rem; border-radius:999px; text-decoration:none; color:var(--text);
    border:1px solid var(--border);
    background:linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.01));
    box-shadow:var(--shadow);
    transition:.15s ease transform, .2s ease box-shadow, .2s ease border-color;
  }
  .tab:hover{ transform:translateY(-1px); border-color:rgba(96,165,250,.35); box-shadow:var(--shadow), 0 0 18px rgba(96,165,250,.25); }
  .tab.active{ border-color:rgba(96,165,250,.55); box-shadow:var(--shadow), 0 0 22px rgba(96,165,250,.35);
    background:linear-gradient(180deg, rgba(96,165,250,.08), rgba(96,165,250,.02)); }

  .container{ max-width:1100px; margin:2rem auto; padding:0 1rem; }
  .grid{ display:grid; grid-template-columns:1fr; justify-items:center; }
  .card{
    width:100%; max-width:520px; padding:1.2rem 1.2rem 1rem;
    background:linear-gradient(180deg, var(--card), var(--card2));
    border:1px solid var(--border); border-radius:var(--r); box-shadow:var(--shadow);
  }
  .card h1{ font-size:1.35rem; margin:.2rem 0 1rem; }

  form{ width:100%; margin:0; }
  label{ display:block; font-size:.9rem; color:var(--muted); margin:.65rem 0 .35rem; }
  input,select,button{
    display:block; width:100%; max-width:100%;
    border-radius:12px; border:1px solid rgba(255,255,255,.08);
    background:#0b1326; color:var(--text); padding:.65rem .8rem; font-size:.95rem;
    outline:none; transition:.15s ease border-color, .15s ease box-shadow;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.03);
  }
  input:focus,select:focus{ border-color:rgba(96,165,250,.55); box-shadow:0 0 0 3px rgba(96,165,250,.18); }

  /* date picker contrast on dark */
  input[type=\"date\"]{ color-scheme:dark; }
  input[type=\"date\"]::-webkit-calendar-picker-indicator{
    filter: invert(1) brightness(1.2) drop-shadow(0 0 6px rgba(96,165,250,.7)); opacity:.9;
  }
  input[type=\"date\"]::-webkit-datetime-edit,
  input[type=\"date\"]::-webkit-datetime-edit-fields-wrapper,
  input[type=\"date\"]::-webkit-datetime-edit-text,
  input[type=\"date\"]::-webkit-datetime-edit-month-field,
  input[type=\"date\"]::-webkit-datetime-edit-day-field,
  input[type=\"date\"]::-webkit-datetime-edit-year-field{ color:var(--text); }

  .actions{ display:flex; gap:.6rem; justify-content:flex-end; margin-top:1rem; }
  .btn{ cursor:pointer; background:linear-gradient(180deg, rgba(59,130,246,.25), rgba(59,130,246,.15));
    border:1px solid rgba(96,165,250,.45); color:#fff; font-weight:600; letter-spacing:.2px; }
  .btn:hover{ transform:translateY(-1px); box-shadow:var(--shadow), 0 0 22px rgba(96,165,250,.35); }

  .flash{ margin:0 auto 1rem; max-width:520px; padding:.75rem 1rem; border-radius:12px;
    background:linear-gradient(180deg, rgba(34,197,94,.12), rgba(34,197,94,.06)); border:1px solid rgba(34,197,94,.35); }
  .flash.error{ background:linear-gradient(180deg, rgba(239,68,68,.12), rgba(239,68,68,.06)); border-color:rgba(239,68,68,.35); }
</style>
</head>
<body>

  <div class=\"nav-wrap\">
    <div class=\"nav\">
      <div class=\"brand\">⚡ Lab Scheduler</div>
      <nav class=\"tabs\">
        <a class=\"tab {$is('home')}\" href=\"?route=home\">Schedule</a>
        <a class=\"tab {$is('login')}\" href=\"?route=login\">Login</a>
        <a class=\"tab {$is('register')}\" href=\"?route=register\">Register</a>" .
        ($isLoggedIn ? "<a class=\"tab\" href=\"?route=logout\">Logout</a>" : "") .
      "</nav>
    </div>
  </div>

  <div class=\"container\">" .
    ($flash ? '<div class=\"flash\">'.h($flash).'</div>' : '') . "
    <div class=\"grid\">
      <div class=\"card\">{$content}</div>
    </div>
  </div>

</body>
</html>";
}

