SELECT p.id, p.title, p.view_count, COUNT(c.id) as comment_count,
       (p.view_count + COUNT(c.id) * 10) as viral_score
FROM posts p
LEFT JOIN comments c ON c.post_id = p.id
GROUP BY p.id, p.title, p.view_count
ORDER BY viral_score DESC
LIMIT :limit
