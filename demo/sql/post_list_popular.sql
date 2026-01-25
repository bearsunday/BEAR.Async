SELECT p.id, p.title, a.name AS author_name, p.view_count, p.created_at
FROM posts p
INNER JOIN authors a ON p.author_id = a.id
ORDER BY p.view_count DESC
LIMIT :limit
