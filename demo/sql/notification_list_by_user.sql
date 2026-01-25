SELECT id, message, read_at, created_at
FROM notifications
WHERE user_id = :user_id
ORDER BY created_at DESC
