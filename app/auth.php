<?php /* ======================== /app/auth.php ========================= */ ?>
<?php
class Auth {
public static function login($username,$password){
$st = DB::$pdo->prepare("SELECT * FROM users WHERE lower(username)=lower(?) LIMIT 1");
$st->execute([$username]);
$u = $st->fetch();
if(!$u) return [false,'Invalid credentials'];
if(!password_verify($password, $u['password_hash'])) return [false,'Invalid credentials'];
if($u['status'] !== 'approved') return [false,'Your account is awaiting approval.'];
$_SESSION['uid']=$u['id'];
$_SESSION['username']=$u['username'];
$_SESSION['role']=$u['role'];
$_SESSION['track']=$u['track'];
$_SESSION['timezone']=$u['timezone'] ?: 'Asia/Kolkata';
return [true,null];
}
public static function register($username,$email,$password,$track,$exam_date){
// create user pending + approval row
DB::$pdo->beginTransaction();
try{
$st = DB::$pdo->prepare("INSERT INTO users(username,email,password_hash,status,role,track,exam_date,credits,timezone) VALUES(?,?,?,?,?,?,?,?,?)");
$hash = password_hash($password, PASSWORD_DEFAULT);
$st->execute([$username,$email,$hash,'pending','student',$track,$exam_date,null,'Asia/Kolkata']);
$uid = DB::$pdo->lastInsertId();
$ap = DB::$pdo->prepare("INSERT INTO approvals(user_id,requested_track,requested_exam_date,status) VALUES(?,?,?,'pending')");
$ap->execute([$uid,$track,$exam_date]);
DB::$pdo->commit();
return [true,$uid];
}catch(Throwable $e){ DB::$pdo->rollBack(); return [false,$e->getMessage()]; }
}
public static function approve($user_id,$decider_id){
DB::$pdo->beginTransaction();
try{
// set status=approved, set starting credits by track policy
$u = DB::$pdo->query("SELECT id,track FROM users WHERE id=".(int)$user_id." FOR UPDATE")->fetch();
if(!$u) throw new Exception('User not found');
$pol = DB::$pdo->prepare("SELECT starting_credits FROM track_policies WHERE track=?");
$pol->execute([$u['track']]);
$p = $pol->fetch(); if(!$p) throw new Exception('Track policy missing');
$upd = DB::$pdo->prepare("UPDATE users SET status='approved', credits=? WHERE id=?");
$upd->execute([$p['starting_credits'],$u['id']]);
$ap = DB::$pdo->prepare("UPDATE approvals SET status='approved', decided_by=?, decided_at=NOW() WHERE user_id=?");
$ap->execute([$decider_id,$u['id']]);
DB::$pdo->commit();
return [true,null];
}catch(Throwable $e){ DB::$pdo->rollBack(); return [false,$e->getMessage()]; }
}
}
?>
