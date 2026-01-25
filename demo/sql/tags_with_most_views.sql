SELECT t.id, t.name, COALESCE(SUM(p.view_count), 0) as total_views
FROM tags t
LEFT JOIN post_tags pt ON pt.tag_id = t.id
LEFT JOIN posts p ON pt.post_id = p.id
GROUP BY t.id, t.name
ORDER BY total_views DESC
LIMIT :limit
