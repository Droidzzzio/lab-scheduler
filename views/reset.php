<?php render_layout('Reset password', function(){ $t = h($_GET['token'] ?? ''); ?>
  <h1>Reset password</h1>
  <form method="post" action="?route=reset&token=<?= $t ?>">
    <label>New password</label>
    <input type="password" name="password" required>
    <div class="actions">
      <button class="btn" type="submit" name="action" value="do_reset">Update password</button>
    </div>
  </form>
<?php }, $flash ?? null, 'login'); ?>

