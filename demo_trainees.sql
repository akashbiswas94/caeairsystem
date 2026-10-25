-- Demo trainee accounts for CAE presentation.
-- Passwords match the current plaintext demo login flow.

INSERT INTO users (first_name, last_name, email, password, role)
SELECT 'Tanmay', 'Singh', 'tanmay.trainee@test.com', '123456', 'trainee'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'tanmay.trainee@test.com');

INSERT INTO users (first_name, last_name, email, password, role)
SELECT 'Swapnil', 'Bonde', 'swapnil.trainee@test.com', '123456', 'trainee'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'swapnil.trainee@test.com');

INSERT INTO users (first_name, last_name, email, password, role)
SELECT 'Francisco', '', 'francisco.trainee@test.com', '123456', 'trainee'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'francisco.trainee@test.com');
