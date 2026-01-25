SELECT id, name, email, created_at
FROM authors
ORDER BY created_at DESC
LIMIT :limit
