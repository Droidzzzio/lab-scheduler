<?php /* ======================== /views/dashboard_dc.php ============== */ ?>
<?php
ob_start(); ?>
<section class="card">
<h2>My Dashboard — CCIE Data Center</h2>
<p class="kicker">Credits: <strong><?=h($credits)?></strong> • Slots used: <strong><?=h($used)?>/30</strong> • Exam date: <strong><?=h($exam_date)?></strong></p>
<form method="get" class="" style="margin-bottom:12px">
<input type="hidden" name="route" value="home">
<label>Date &nbsp;<input type="date" name="date" value="<?=h($date)?>" min="<?=h((new DateTime('now', tz_ist()))->format('Y-m-d'))?>"></label>
<button class="btn ghost" type="submit">Go</button>
</form>


<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr));">
<?php foreach($slots as $s):
$idx=$s['idx']; $taken = isset($day[$idx]); $by = $taken? $day[$idx]['username']:null; $byYou = $taken && $day[$idx]['user_id']===$_SESSION['uid'];
$startUtc = parse_local_ymd($date,$s['h'],$s['min']); $lock = ($startUtc->getTimestamp() - now_utc()->getTimestamp()) < 72*3600;
?>
<div class="slot <?= $taken? 'busy':'' ?>">
<div>
<div><strong><?=h($s['label'])?></strong></div>
<div class="kicker">
<?php if($taken): ?>
<?php if(is_admin()||is_trainer()): ?>Booked by <strong><?=h($by)?></strong><?php else: ?>Booked<?php endif; ?>
<?php else: ?>Available<?php endif; ?>
</div>
</div>
<div>
<?php if(!$taken): ?>
<form method="post" style="display:inline-block">
<input type="hidden" name="action" value="book_dc">
<input type="hidden" name="date" value="<?=h($date)?>">
<input type="hidden" name="slot" value="<?=h($idx)?>">
<label>Module
<select name="module" required>
<option value="Nexus">Nexus</option>
<option value="UCS">UCS</option>
<option value="ACI">ACI</option>
</select>
</label>
<button class="btn" type="submit">Book</button>
</form>
<?php else: ?>
<?php if($byYou): ?>
<?php if(!$lock): ?>
<form method="post" style="display:inline-block">
<input type="hidden" name="action" value="cancel">
<input type="hidden" name="booking_id" value="<?=h($day[$idx]['id'])?>">
<button class="btn danger" type="submit">Cancel</button>
</form>
<?php else: ?>
<button class="btn" disabled>Locked</button>
<?php endif; ?>
<?php else: ?><button class="btn" disabled>Unavailable</button><?php endif; ?>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
</div>
</section>
<?php $content = ob_get_clean(); render_layout('Dashboard — DC', $content); ?>
