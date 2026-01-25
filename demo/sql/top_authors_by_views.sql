SELECT a.id, a.name, COALESCE(SUM(p.view_count), 0) as total_views
FROM authors a
LEFT JOIN posts p ON p.author_id = a.id
GROUP BY a.id, a.name
ORDER BY total_views DESC
LIMIT :limit
