-- AAR admin-role helper
-- Run these statements one at a time in phpMyAdmin.

-- 1) See which role values your current users have.
SELECT id, first_name, last_name, email, role
FROM users
ORDER BY id;

-- 2) Inspect the role column before changing a user.
SHOW COLUMNS FROM users LIKE 'role';

-- 3) Promote an EXISTING account after replacing the email below.
-- If your role column supports 'admin', use:
-- UPDATE users SET role = 'admin' WHERE email = 'your-admin-email@example.com';

-- If your schema uses 'instructor' instead of 'admin', use:
-- UPDATE users SET role = 'instructor' WHERE email = 'your-admin-email@example.com';

-- The PHP admin pages treat every authenticated non-trainee role as an admin/instructor view.
