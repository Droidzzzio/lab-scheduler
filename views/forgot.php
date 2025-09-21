<?php render_layout('Forgot password', function(){ ?>
  <h1>Forgot password</h1>
  <form method="post" action="?route=forgot">
    <label>Username or Email</label>
    <input name="login" required>
    <div class="actions">
      <button class="btn" type="submit" name="action" value="forgot">Send reset link</button>
    </div>
  </form>
<?php }, $flash ?? null, 'login'); ?>
