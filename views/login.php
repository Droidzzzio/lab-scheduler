<?php
render_layout('Login', function () { ?>
  <h1>Login</h1>
  <form method="post" action="?route=login">
    <label>Username</label>
    <input name="username" autocomplete="username" required>

    <label>Password</label>
    <input type="password" name="password" autocomplete="current-password" required>

    <div class="actions">
      <button class="btn" type="submit" name="action" value="login">Sign in</button>
      <a class="btn secondary" href="?route=forgot">Forgot password?</a>
    </div>
  </form>
<?php }, $flash ?? null, 'login');
