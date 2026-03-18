USE cloud_signage;

-- Default admin login:
-- username: admin
-- password: ChangeMe123!
-- Change this password immediately after first login by updating the hash in the database.
INSERT INTO admins (username, password_hash, created_at)
VALUES (
    'admin',
    '$2y$10$j8e0JIrOBo7O3GDdDWvHm.C5KrZjmFvCBx2inve0cBwvP/1RYziay',
    UTC_TIMESTAMP()
)
ON DUPLICATE KEY UPDATE username = VALUES(username);
