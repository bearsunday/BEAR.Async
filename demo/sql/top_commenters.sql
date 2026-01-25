/* top_commenters - Most active commenters */
SELECT
    c.author_name as commenter_name,
    COUNT(*) as comment_count,
    COUNT(DISTINCT c.post_id) as unique_posts_commented,
    COUNT(DISTINCT p.author_id) as unique_authors_engaged,
    MIN(c.created_at) as first_comment,
    MAX(c.created_at) as last_comment,
    SUM(p.view_count) as total_views_on_commented_posts
FROM comments c
JOIN posts p ON c.post_id = p.id
GROUP BY c.author_name
ORDER BY comment_count DESC
LIMIT :limit
