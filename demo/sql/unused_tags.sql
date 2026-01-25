SELECT t.id, t.name
FROM tags t
LEFT JOIN post_tags pt ON pt.tag_id = t.id
WHERE pt.post_id IS NULL
LIMIT :limit
