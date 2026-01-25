SELECT t.id, t.name, COUNT(c.id) as comment_count
FROM tags t
LEFT JOIN post_tags pt ON pt.tag_id = t.id
LEFT JOIN posts p ON pt.post_id = p.id
LEFT JOIN comments c ON c.post_id = p.id
GROUP BY t.id, t.name
ORDER BY comment_count DESC
LIMIT :limit
