<?php /* ======================== /app/config.php ======================== */ ?>
<?php
// Simple .env loader (no external deps)
function env_load($path){
if(!file_exists($path)) return;
foreach(file($path) as $line){
if(!trim($line) || str_starts_with(trim($line),'#')) continue;
[$k,$v] = array_map('trim', explode('=', $line, 2));
$v = trim($v, "\"'");
$_ENV[$k] = $v;
}
}
function env($key, $default=null){ return $_ENV[$key] ?? $default; }
?>
