SELECT author_name, COUNT(*) as comment_count, COUNT(DISTINCT post_id) as unique_posts
FROM comments
GROUP BY author_name
ORDER BY comment_count DESC
LIMIT :limit
