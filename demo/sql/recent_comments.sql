SELECT c.id, c.author_name, c.body, p.title as post_title, c.created_at
FROM comments c
JOIN posts p ON c.post_id = p.id
ORDER BY c.created_at DESC
LIMIT :limit
