SELECT
    CAST((SELECT COUNT(*) FROM authors) AS SIGNED) AS total_authors,
    CAST((SELECT COUNT(*) FROM posts) AS SIGNED) AS total_posts,
    CAST((SELECT COUNT(*) FROM comments) AS SIGNED) AS total_comments,
    CAST((SELECT COALESCE(SUM(view_count), 0) FROM posts) AS SIGNED) AS total_views
