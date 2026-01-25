-- Large dataset for realistic benchmarking
-- Creates: 1000 users, 10000 posts, 50000 comments

-- Disable foreign key checks for truncation
SET FOREIGN_KEY_CHECKS = 0;

-- Clear existing data
TRUNCATE TABLE comments;
TRUNCATE TABLE post_tags;
TRUNCATE TABLE posts;
TRUNCATE TABLE tags;
TRUNCATE TABLE notifications;
TRUNCATE TABLE activity_logs;
TRUNCATE TABLE users;
TRUNCATE TABLE categories;
TRUNCATE TABLE authors;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Create 1000 authors/users
INSERT INTO authors (id, name, email)
SELECT
    n,
    CONCAT('Author ', n),
    CONCAT('author', n, '@example.com')
FROM (
    SELECT a.N + b.N * 10 + c.N * 100 + 1 as n
    FROM
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b,
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) c
) numbers
WHERE n <= 1000;

-- Create 50 tags
INSERT INTO tags (id, name)
SELECT n, CONCAT('tag-', n)
FROM (
    SELECT a.N + b.N * 10 + 1 as n
    FROM
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) b
) numbers
WHERE n <= 50;

-- Create 10000 posts (10 per author)
INSERT INTO posts (id, author_id, title, body, view_count, created_at)
SELECT
    n,
    ((n - 1) MOD 1000) + 1,
    CONCAT('Post Title ', n, ' - Lorem ipsum dolor sit amet'),
    CONCAT('This is the body of post ', n, '. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.'),
    FLOOR(RAND() * 10000),
    DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 365) DAY)
FROM (
    SELECT a.N + b.N * 10 + c.N * 100 + d.N * 1000 + 1 as n
    FROM
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b,
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) c,
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) d
) numbers
WHERE n <= 10000;

-- Create 50000 comments (5 per post)
INSERT INTO comments (id, post_id, author_name, body, created_at)
SELECT
    n,
    ((n - 1) MOD 10000) + 1,
    CONCAT('Commenter ', FLOOR(RAND() * 1000) + 1),
    CONCAT('Comment ', n, ': Great post! This is a thoughtful response with some additional insights.'),
    DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 180) DAY)
FROM (
    SELECT a.N + b.N * 10 + c.N * 100 + d.N * 1000 + e.N * 10000 + 1 as n
    FROM
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b,
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) c,
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) d,
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) e
) numbers
WHERE n <= 50000;

-- Assign 3 random tags to each post
INSERT INTO post_tags (post_id, tag_id)
SELECT DISTINCT
    post_id,
    tag_id
FROM (
    SELECT
        p.id as post_id,
        ((p.id + offset) MOD 50) + 1 as tag_id
    FROM posts p
    CROSS JOIN (SELECT 0 as offset UNION SELECT 7 UNION SELECT 23) offsets
) t;

-- Create 100 users for notifications
INSERT INTO users (id, name, email)
SELECT
    n,
    CONCAT('User ', n),
    CONCAT('user', n, '@example.com')
FROM (
    SELECT a.N + b.N * 10 + 1 as n
    FROM
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
) numbers
WHERE n <= 100;

-- Create notifications (20 per user for first 100 users)
INSERT INTO notifications (id, user_id, message, read_at, created_at)
SELECT
    n,
    ((n - 1) MOD 100) + 1,
    CONCAT('Notification ', n, ': You have a new follower or comment'),
    CASE WHEN FLOOR(RAND() * 2) = 1 THEN NOW() ELSE NULL END,
    DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 30) DAY)
FROM (
    SELECT a.N + b.N * 10 + c.N * 100 + d.N * 1000 + 1 as n
    FROM
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b,
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) c,
        (SELECT 0 AS N UNION SELECT 1) d
) numbers
WHERE n <= 2000;

-- Create activity logs (50 per user for first 100 users)
INSERT INTO activity_logs (id, user_id, action, target, created_at)
SELECT
    n,
    ((n - 1) MOD 100) + 1,
    ELT(FLOOR(RAND() * 4) + 1, 'post', 'comment', 'like', 'follow'),
    CONCAT('target_', FLOOR(RAND() * 1000)),
    DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 60) DAY)
FROM (
    SELECT a.N + b.N * 10 + c.N * 100 + d.N * 1000 + 1 as n
    FROM
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b,
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
         UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) c,
        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) d
) numbers
WHERE n <= 5000;

-- Show counts
SELECT 'Data inserted:' as status;
SELECT 'authors' as table_name, COUNT(*) as count FROM authors
UNION ALL SELECT 'posts', COUNT(*) FROM posts
UNION ALL SELECT 'comments', COUNT(*) FROM comments
UNION ALL SELECT 'tags', COUNT(*) FROM tags
UNION ALL SELECT 'post_tags', COUNT(*) FROM post_tags
UNION ALL SELECT 'users', COUNT(*) FROM users
UNION ALL SELECT 'notifications', COUNT(*) FROM notifications
UNION ALL SELECT 'activity_logs', COUNT(*) FROM activity_logs;
