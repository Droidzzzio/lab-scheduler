<?php
// strict dev mode (optional while debugging)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// sessions & routing vars
session_start();
$route  = $_GET['route']  ?? 'home';
$action = $_POST['action'] ?? null;

// defaults used below
$ok = $ok ?? false;
$msg = $msg ?? '';
$flash = null;

// include your app bootstrap so DB::$pdo, Booking, tz_ist(), require_login(), is_admin() exist
require_once __DIR__ . '/../app/bootstrap.php';   // <-- adjust path/name to your actual bootstrap
ob_start();

$flash = $ok? 'Booked!' : ('Error: '.$msg);

{
if($action==='cancel' && !empty($_SESSION['uid'])){
$user = DB::$pdo->query("SELECT id,role FROM users WHERE id=".(int)$_SESSION['uid'])->fetch();
[$ok,$msg]=Booking::cancel($user,(int)$_POST['booking_id']);
$flash = $ok? 'Cancelled.' : ('Error: '.$msg);
}
}


// Routes
if($route==='logout'){ session_destroy(); header('Location: ?route=login'); exit; }


if ($route === 'login') {
    // Handle POST → verify user
    if (($_POST['action'] ?? '') === 'login') {
        $u = trim($_POST['username'] ?? '');
        $p = $_POST['password'] ?? '';

        try {
            $stmt = DB::$pdo->prepare("SELECT id, password_hash, status, role FROM users WHERE username = :u LIMIT 1");
            $stmt->execute([':u' => $u]);
            $row = $stmt->fetch();

            if (!$row || !password_verify($p, $row['password_hash'])) {
                $flash = 'Invalid username or password';
                error_log("Login failed for '$u' (bad creds).");
            } elseif (($row['status'] ?? '') !== 'approved') {
                $flash = 'Your account is pending approval.';
                error_log("Login blocked for user_id={$row['id']} (status={$row['status']}).");
            } else {
                session_regenerate_id(true);
                $_SESSION['uid']  = (int)$row['id'];
                $_SESSION['role'] = $row['role'];
                error_log("Login OK for user_id={$row['id']}, role={$row['role']}.");

                // Send admins to dashboard, others to home
                header('Location: ' . ($row['role'] === 'admin' ? '?route=admin' : '?route=home'));
                exit;
            }
        } catch (Throwable $e) {
            $flash = 'Server error. Try again.';
            error_log("Login exception for '$u': " . $e->getMessage());
        }
    }

    // Render the login page
    require __DIR__ . '/../views/login.php';
    exit;
}

// REGISTER
if ($route === 'register') {
    if (($_POST['action'] ?? '') === 'register') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $pass     = $_POST['password'] ?? '';
        $trackRaw = trim($_POST['track'] ?? '');
        $exam     = trim($_POST['exam_date'] ?? '');   // from <input type="date" name="exam_date">

        // normalize track input
        $t = strtolower($trackRaw);
        if (in_array($t, ['ccie data center','data center','datacenter','dc'], true)) $t = 'datacenter';
        if (in_array($t, ['ccie security','security'], true)) $t = 'security';

        // basic validation (require exam date so NOT NULL column is satisfied)
        if (!$username || !$email || !$pass || !in_array($t, ['datacenter','security'], true) || !$exam) {
            $flash = 'Please fill all fields and choose a valid track + exam date.';
        } else {
            try {
                DB::$pdo->beginTransaction();
                // create user as PENDING; write exam_date
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $stmt = DB::$pdo->prepare(
                "INSERT INTO users
                (username,email,password_hash,status,role,track,credits,timezone,exam_date)
                VALUES (:u,:e,:h,'pending','student',:t,10,'Asia/Kolkata',:exam)"
                );
                $stmt->execute([
                    ':u'    => $username,
                    ':e'    => $email,
                    ':h'    => $hash,
                    ':t'    => $t,          // 'datacenter' or 'security'
                    ':exam' => $exam ?: null   // from <input type='date' name='exam_date'>
                ]);

                $uid = (int)DB::$pdo->lastInsertId();
                DB::$pdo->prepare("INSERT INTO approvals (user_id,status,created_at) VALUES (:uid,'pending',NOW())")
                    ->execute([':uid' => $uid]);
                DB::$pdo->commit();
                header('Location: ?route=login'); exit;


            } catch (PDOException $e) {
                DB::$pdo->rollBack();
                // 23000 = duplicate (username/email unique)
                $flash = ($e->getCode()==='23000')
                    ? 'Username or email already exists.'
                    : 'Registration error: '.$e->getMessage();
            } catch (Throwable $e) {
                DB::$pdo->rollBack();
                $flash = 'Registration error: '.$e->getMessage();
            }
        }
    }

    // Always render the form
    require __DIR__ . '/../views/register.php';
    exit;
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
foreach($resources as $r){ $map=Booking::bookingsForDate($r['id'],$date); if(isset($map[$idx])){ /* keep */ } else { $allTaken=false; }
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
