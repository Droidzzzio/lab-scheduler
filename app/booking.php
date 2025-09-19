<?php /* ======================== /app/booking.php ===================== */ ?>
try{
// Insert booking (unique key enforces no double booking for confirmed status)
$ins = DB::$pdo->prepare("INSERT INTO bookings(user_id,resource_id,date,slot_idx,start_ts,end_ts,status,module,attributes_json) VALUES(?,?,?,?,? ,? ,'confirmed', ?, JSON_OBJECT())");
$module = null; // store DC module here; for Security keep NULL and store rack via resource
if($track==='datacenter'){
if(!in_array($module_or_rack,['Nexus','UCS','ACI'], true)) throw new Exception('Module required');
$module = $module_or_rack;
}
$ins->execute([$uid,$resource_id,$date,$slot_idx,$startUtc->format('Y-m-d H:i:s'),$endUtc->format('Y-m-d H:i:s'),$module]);
$bid = DB::$pdo->lastInsertId();


// Deduct credits for students
if($role==='student'){
$u = DB::$pdo->prepare("UPDATE users SET credits=credits-? WHERE id=?");
$u->execute([$cost,$uid]);
$led = DB::$pdo->prepare("INSERT INTO credit_ledger(user_id,booking_id,delta,reason) VALUES(?,?,?,?)");
$led->execute([$uid,$bid,-$cost,$track==='datacenter'?'dc booking':'security booking']);
}


DB::$pdo->commit();
return [true,$bid];
}catch(Throwable $e){ DB::$pdo->rollBack(); return [false,$e->getMessage()]; }
}
public static function cancel($user,$booking_id){
$uid = $user['id']; $role=$user['role'];
DB::$pdo->beginTransaction();
try{
$st = DB::$pdo->prepare("SELECT * FROM bookings WHERE id=? FOR UPDATE");
$st->execute([$booking_id]);
$b = $st->fetch(); if(!$b) throw new Exception('Booking not found');
if($b['status']!=='confirmed') throw new Exception('Already cancelled');
$owner = (int)$b['user_id'] === (int)$uid;


// 72h rule
$now = now_utc(); $start = new DateTime($b['start_ts'], new DateTimeZone('UTC'));
$diffH = ($start->getTimestamp() - $now->getTimestamp())/3600;
$canStudentCancel = $owner && $diffH >= 72;


if(!($canStudentCancel || $role==='trainer' || $role==='admin')) throw new Exception('Locked (<72h)');


// Cancel
$upd = DB::$pdo->prepare("UPDATE bookings SET status='cancelled', cancelled_at=NOW(), cancelled_by=? WHERE id=?");
$upd->execute([$uid,$booking_id]);


// Refund only if student owner and ≥72h
if($canStudentCancel){
// determine track cost
$trk = DB::$pdo->prepare("SELECT track FROM users WHERE id=?"); $trk->execute([$b['user_id']]); $u=$trk->fetch();
$pol = self::trackPolicy($u['track']); $cost=(int)$pol['credits_per_slot'];
DB::$pdo->prepare("UPDATE users SET credits=credits+? WHERE id=?")->execute([$cost,$b['user_id']]);
DB::$pdo->prepare("INSERT INTO credit_ledger(user_id,booking_id,delta,reason) VALUES(?,?,?,?)")
->execute([$b['user_id'],$booking_id,$cost,'refund (>=72h)']);
}


DB::$pdo->commit();
return [true,null];
}catch(Throwable $e){ DB::$pdo->rollBack(); return [false,$e->getMessage()]; }
}
}
?>
