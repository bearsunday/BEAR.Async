SELECT a.id, a.name, COUNT(p.id) as post_count
FROM authors a
LEFT JOIN posts p ON p.author_id = a.id
GROUP BY a.id, a.name
ORDER BY post_count DESC
LIMIT :limit
