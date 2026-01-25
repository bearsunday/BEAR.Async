/* recent_engagement - Recent comments with full context */
SELECT
    c.id,
    c.body as comment_body,
    c.author_name as commenter,
    c.created_at,
    p.id as post_id,
    p.title as post_title,
    a.name as post_author,
    p.view_count,
    (SELECT COUNT(*) FROM comments c2 WHERE c2.post_id = p.id) as total_comments_on_post,
    (SELECT COUNT(*) FROM posts p2 WHERE p2.author_id = a.id) as author_total_posts,
    (SELECT COALESCE(SUM(p3.view_count), 0) FROM posts p3 WHERE p3.author_id = a.id) as author_total_views,
    (SELECT COUNT(*) FROM comments c3 WHERE c3.author_name = c.author_name) as commenter_total_comments,
    (SELECT GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ')
     FROM post_tags pt
     JOIN tags t ON pt.tag_id = t.id
     WHERE pt.post_id = p.id) as post_tags,
    (SELECT COUNT(DISTINCT c4.post_id) FROM comments c4 WHERE c4.author_name = c.author_name) as commenter_unique_posts
FROM comments c
JOIN posts p ON c.post_id = p.id
JOIN authors a ON p.author_id = a.id
ORDER BY c.created_at DESC
LIMIT :limit
