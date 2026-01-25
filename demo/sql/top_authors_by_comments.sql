SELECT a.id, a.name, COUNT(c.id) as comment_count
FROM authors a
LEFT JOIN posts p ON p.author_id = a.id
LEFT JOIN comments c ON c.post_id = p.id
GROUP BY a.id, a.name
ORDER BY comment_count DESC
LIMIT :limit
