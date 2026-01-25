SELECT DATE(p.created_at) as day, COUNT(*) as post_count
FROM posts p
GROUP BY DATE(p.created_at)
ORDER BY day DESC
LIMIT :limit
