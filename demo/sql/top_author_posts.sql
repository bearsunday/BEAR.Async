SELECT p.id, p.title, p.view_count, a.name as author_name
FROM posts p
JOIN authors a ON p.author_id = a.id
WHERE a.id = (SELECT author_id FROM posts GROUP BY author_id ORDER BY COUNT(*) DESC LIMIT 1)
ORDER BY p.view_count DESC
LIMIT :limit
