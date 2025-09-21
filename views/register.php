<?php
render_layout('Register (Student)', function () { ?>
  <h1>Register (Student)</h1>
  <form method="post" action="?route=register">
    <label>Username</label>
    <input name="username" required>

    <label>Email</label>
    <input type="email" name="email" required>

    <label>Password</label>
    <input type="password" name="password" required>

    <label>Track</label>
    <select name="track" required>
      <option value="datacenter">CCIE Data Center</option>
      <option value="security">Security</option>
    </select>

    <label>Exam Date</label>
    <input type="date" name="exam_date" required>

    <div class="actions">
      <button class="btn" type="submit" name="action" value="register">Create account</button>
    </div>
  </form>
<?php }, $flash ?? null, 'register');
