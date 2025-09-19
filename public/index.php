<?php /* ======================== /public/index.php ==================== */ ?>
$flash = $ok? 'Booked!' : ('Error: '.$msg);
}
}
if($action==='cancel' && !empty($_SESSION['uid'])){
$user = DB::$pdo->query("SELECT id,role FROM users WHERE id=".(int)$_SESSION['uid'])->fetch();
[$ok,$msg]=Booking::cancel($user,(int)$_POST['booking_id']);
$flash = $ok? 'Cancelled.' : ('Error: '.$msg);
}
}


// Routes
if($route==='logout'){ session_destroy(); header('Location: ?route=login'); exit; }


if($route==='login'){
require __DIR__.'/../views/login.php'; exit;
}
if($route==='register'){
require __DIR__.'/../views/register.php'; exit;
}
if($route==='admin'){
require_login(); if(!is_admin()){ http_response_code(403); echo 'Forbidden'; exit; }
$pending = DB::$pdo->query("SELECT a.*, u.username FROM approvals a JOIN users u ON u.id=a.user_id WHERE a.status='pending' ORDER BY a.created_at ASC")->fetchAll();
require __DIR__.'/../views/admin.php'; exit;
}


// Home (track‑gated dashboards)
require_login();
$track = current_track();
if(!$track){ echo 'Your account has no track set.'; exit; }


// Common stats
$urow = DB::$pdo->query("SELECT credits, exam_date FROM users WHERE id=".(int)$_SESSION['uid'])->fetch();
$credits = (int)($urow['credits'] ?? 0);
$exam_date = $urow['exam_date'] ?? null;
$used = (int)DB::$pdo->query("SELECT COUNT(*) c FROM bookings WHERE user_id=".(int)$_SESSION['uid']." AND status='confirmed'")->fetch()['c'];
$date = $_GET['date'] ?? (new DateTime('now', tz_ist()))->format('Y-m-d');
$slots = Booking::slotsForDay($track);


if($track==='datacenter'){
// Single resource (DC Rack)
$res = DB::$pdo->query("SELECT id FROM resources WHERE track='datacenter' LIMIT 1")->fetch();
$day = Booking::bookingsForDate($res['id'],$date);
require __DIR__.'/../views/dashboard_dc.php';
exit;
}
if($track==='security'){
$resources = Booking::resourcesForTrack('security');
// For each rack: we show availability per slot only by chosen rack at book time.
// To show conflicts, we can merge racks → but booking prevents per‑rack double bookings anyway.
// For display, mark slot as "Booked" if ALL racks are taken; otherwise "Available" and user picks rack in form.
// Simplify for now: show as Available if at least one rack is free; resolve at booking time.
$day = [];
foreach($slots as $s){
$idx=$s['idx']; $allTaken=true; $username = null; $bid=null; $uid=null;
foreach($resources as $r){ $map=Booking::bookingsForDate($r['id'],$date'); if(isset($map[$idx])){ /* keep */ } else { $allTaken=false; }
if($username===null && isset($map[$idx])){ $username=$map[$idx]['username']; $bid=$map[$idx]['id']; $uid=$map[$idx]['user_id']; }
}
if($allTaken && $username!==null){ $day[$idx] = ['id'=>$bid,'username'=>$username,'user_id'=>$uid]; }
}
require __DIR__.'/../views/dashboard_sec.php';
exit;
}


http_response_code(404); echo 'Unknown track';
?>


<?php /* ======================== Admin seed (run once in MySQL) =======
INSERT INTO users (username,email,password_hash,status,role,track,credits,timezone)
VALUES ('admin','admin@example.com', '$2y$10$w2Y7mC9M9g4wXk2f/8E0lOOv9z1e.RgA7hL0m2t7v7x4m3h3Z0r9C', 'approved','admin', NULL, NULL,'Asia/Kolkata');
-- The hash above is just a placeholder; generate your own with PHP's password_hash.
*/ ?>
