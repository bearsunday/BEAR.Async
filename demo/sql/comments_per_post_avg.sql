SELECT p.id, p.title, COUNT(c.id) as comment_count, 
       (SELECT AVG(cnt) FROM (SELECT COUNT(*) as cnt FROM comments GROUP BY post_id) sub) as avg_comments
FROM posts p
LEFT JOIN comments c ON c.post_id = p.id
GROUP BY p.id, p.title
ORDER BY comment_count DESC
LIMIT :limit
