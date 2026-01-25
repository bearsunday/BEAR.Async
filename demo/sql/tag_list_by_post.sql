SELECT t.id, t.name
FROM tags t
INNER JOIN post_tags pt ON t.id = pt.tag_id
WHERE pt.post_id = :post_id
ORDER BY t.id
