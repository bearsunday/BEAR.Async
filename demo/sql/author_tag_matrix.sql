SELECT a.name as author_name, t.name as tag_name, COUNT(pt.post_id) as usage_count
FROM authors a
JOIN posts p ON p.author_id = a.id
JOIN post_tags pt ON pt.post_id = p.id
JOIN tags t ON pt.tag_id = t.id
GROUP BY a.name, t.name
ORDER BY usage_count DESC
LIMIT :limit
