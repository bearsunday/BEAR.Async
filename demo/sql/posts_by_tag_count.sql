SELECT p.id, p.title, COUNT(pt.tag_id) as tag_count
FROM posts p
LEFT JOIN post_tags pt ON pt.post_id = p.id
GROUP BY p.id, p.title
ORDER BY tag_count DESC
LIMIT :limit
