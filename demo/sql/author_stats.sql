/* author_stats - Top authors by engagement (full scan) */
SELECT
    a.id,
    a.name,
    (SELECT COUNT(*) FROM posts p WHERE p.author_id = a.id) as total_posts,
    (SELECT COALESCE(SUM(p.view_count), 0) FROM posts p WHERE p.author_id = a.id) as total_views,
    (SELECT COUNT(*) FROM comments c
     JOIN posts p ON c.post_id = p.id
     WHERE p.author_id = a.id) as total_comments_received,
    (SELECT COALESCE(AVG(p.view_count), 0) FROM posts p WHERE p.author_id = a.id) as avg_views_per_post,
    (SELECT COUNT(DISTINCT pt.tag_id) FROM post_tags pt
     JOIN posts p ON pt.post_id = p.id
     WHERE p.author_id = a.id) as unique_tags_used,
    (SELECT MAX(p.created_at) FROM posts p WHERE p.author_id = a.id) as last_post_date,
    (SELECT GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ')
     FROM post_tags pt
     JOIN posts p ON pt.post_id = p.id
     JOIN tags t ON pt.tag_id = t.id
     WHERE p.author_id = a.id
     LIMIT 5) as top_tags
FROM authors a
ORDER BY total_views DESC
LIMIT :limit
