<?php /* ======================== /views/register.php ================== */ ?>
<?php
ob_start(); ?>
<section class="card">
<h2>Register (Student)</h2>
<?php if(!empty($flash)): ?><p class="kicker"><?=h($flash)?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="action" value="register">
<label>Username<br><input name="username" required></label><br>
<label>Email<br><input type="email" name="email" required></label><br>
<label>Password<br><input type="password" name="password" required></label><br>
<label>Confirm Password<br><input type="password" name="password2" required></label><br>
<label>Track<br>
<select name="track" required>
<option value="datacenter">CCIE Data Center</option>
<option value="security">CCIE Security</option>
</select>
</label><br>
<label>Exam Date<br><input type="date" name="exam_date" required min="<?=h((new DateTime('now', tz_ist()))->format('Y-m-d'))?>"></label><br><br>
<button class="btn" type="submit">Create account</button>
</form>
<p class="kicker">After registration your account is <strong>pending</strong>. Admin must approve it. You will then be able to log in and you will see only your track’s dashboard.</p>
</section>
<?php $content = ob_get_clean(); render_layout('Register', $content); ?>
