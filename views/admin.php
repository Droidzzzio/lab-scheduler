<?php /* ======================== /views/admin.php ===================== */ ?>
<?php
ob_start(); ?>
<section class="card">
<h2>Admin — Approvals</h2>
<?php if(!empty($flash)): ?><p class="kicker"><?=h($flash)?></p><?php endif; ?>
<?php if(empty($pending)): ?>
<p class="kicker">No pending approvals.</p>
<?php else: ?>
<ul>
<?php foreach($pending as $p): ?>
<li>
<strong><?=h($p['username'])?></strong> — <?=h($p['requested_track'])?> — Exam: <?=h($p['requested_exam_date'])?>
<form method="post" style="display:inline">
<input type="hidden" name="action" value="approve">
<input type="hidden" name="user_id" value="<?=h($p['user_id'])?>">
<button class="btn" type="submit">Approve</button>
</form>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</section>
<?php $content = ob_get_clean(); render_layout('Admin', $content); ?>
