<?php /* ======================== /app/helpers.php ====================== */ ?>
<?php
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }
function now_utc(){ return (new DateTime('now', new DateTimeZone('UTC'))); }
function tz_ist(){ return new DateTimeZone('Asia/Kolkata'); }
function to_ist(DateTime $dt){ $c=clone $dt; $c->setTimezone(tz_ist()); return $c; }
function parse_local_ymd(string $ymd, int $hour=0, int $min=0){
// Interpret given date in IST, return UTC DateTime for storage
$dt = DateTime::createFromFormat('Y-m-d H:i:s', sprintf('%s %02d:%02d:00',$ymd,$hour,$min), tz_ist());
if(!$dt) $dt = new DateTime('now', tz_ist());
$dt->setTimezone(new DateTimeZone('UTC'));
return $dt;
}
function require_login(){ if(empty($_SESSION['uid'])){ header('Location: ?route=login'); exit; } }
function is_admin(){ return ($_SESSION['role']??'student')==='admin'; }
function is_trainer(){ return ($_SESSION['role']??'student')==='trainer'; }
function current_track(){ return $_SESSION['track'] ?? null; }
?>
