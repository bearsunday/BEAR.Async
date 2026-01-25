SELECT DATE(c.created_at) as day, COUNT(*) as comment_count
FROM comments c
GROUP BY DATE(c.created_at)
ORDER BY day DESC
LIMIT :limit
