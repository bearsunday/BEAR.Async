/* trending_tags - Most used tags with stats */
SELECT
    t.id,
    t.name,
    (SELECT COUNT(*) FROM post_tags pt WHERE pt.tag_id = t.id) as total_posts,
    (SELECT COALESCE(SUM(p.view_count), 0)
     FROM post_tags pt
     JOIN posts p ON pt.post_id = p.id
     WHERE pt.tag_id = t.id) as total_views,
    (SELECT COUNT(*)
     FROM post_tags pt
     JOIN posts p ON pt.post_id = p.id
     JOIN comments c ON c.post_id = p.id
     WHERE pt.tag_id = t.id) as total_comments
FROM tags t
ORDER BY total_posts DESC
LIMIT :limit
