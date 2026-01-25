SELECT id, action, target, created_at
FROM activity_logs
WHERE user_id = :user_id
ORDER BY created_at DESC
LIMIT :limit
