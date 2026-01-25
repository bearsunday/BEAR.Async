SELECT t.id, t.name, COUNT(pt.post_id) AS count
FROM tags t
LEFT JOIN post_tags pt ON t.id = pt.tag_id
GROUP BY t.id, t.name
ORDER BY count DESC
