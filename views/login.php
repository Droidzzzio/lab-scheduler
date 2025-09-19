<?php /* ======================== /views/login.php ===================== */ ?>
<?php
ob_start(); ?>
<section class="card">
<h2>Login</h2>
<?php if(!empty($flash)): ?><p class="kicker"><?=h($flash)?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="action" value="login">
<label>Username<br><input name="username" required></label><br>
<label>Password<br><input type="password" name="password" required></label><br><br>
<button class="btn" type="submit">Login</button>
</form>
<p class="kicker">If your account is <strong>pending</strong>, you cannot log in until approved by admin.</p>
</section>
<?php $content = ob_get_clean(); render_layout('Login', $content); ?>
