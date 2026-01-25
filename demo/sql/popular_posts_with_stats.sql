/* popular_posts_with_stats - Top posts with engagement metrics */
SELECT
    ranked.id,
    ranked.title,
    ranked.view_count,
    ranked.author_name,
    ranked.comment_count,
    ranked.tag_count,
    ranked.tags,
    ranked.days_old,
    ranked.views_per_day,
    ranked.author_total_posts,
    ranked.author_total_views
FROM (
    SELECT
        p.id,
        p.title,
        p.view_count,
        a.name as author_name,
        (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) as comment_count,
        (SELECT COUNT(*) FROM post_tags pt WHERE pt.post_id = p.id) as tag_count,
        (SELECT GROUP_CONCAT(t.name SEPARATOR ', ')
         FROM post_tags pt2
         JOIN tags t ON pt2.tag_id = t.id
         WHERE pt2.post_id = p.id) as tags,
        DATEDIFF(NOW(), p.created_at) as days_old,
        (p.view_count / GREATEST(DATEDIFF(NOW(), p.created_at), 1)) as views_per_day,
        (SELECT COUNT(*) FROM posts p2 WHERE p2.author_id = a.id) as author_total_posts,
        (SELECT SUM(p3.view_count) FROM posts p3 WHERE p3.author_id = a.id) as author_total_views
    FROM posts p
    JOIN authors a ON p.author_id = a.id
) ranked
ORDER BY ranked.view_count DESC
LIMIT :limit
