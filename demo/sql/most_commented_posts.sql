SELECT p.id, p.title, a.name as author_name, COUNT(c.id) as comment_count
FROM posts p
JOIN authors a ON p.author_id = a.id
LEFT JOIN comments c ON c.post_id = p.id
GROUP BY p.id, p.title, a.name
ORDER BY comment_count DESC
LIMIT :limit
