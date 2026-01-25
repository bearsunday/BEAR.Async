SELECT p.id, p.title, a.name as author_name, p.created_at
FROM posts p
JOIN authors a ON p.author_id = a.id
LEFT JOIN comments c ON c.post_id = p.id
WHERE c.id IS NULL
ORDER BY p.created_at DESC
LIMIT :limit
