-- BEAR.Async Demo Seed Data

-- Authors (3 authors for crawl demo)
INSERT INTO authors (id, name, email, bio) VALUES
(1, 'Alice Johnson', 'alice@example.com', 'Senior developer and tech blogger'),
(2, 'Bob Smith', 'bob@example.com', 'Open source enthusiast'),
(3, 'Carol Davis', 'carol@example.com', 'Full-stack engineer');

-- Posts (2-3 posts per author)
INSERT INTO posts (id, author_id, title, body, view_count) VALUES
(1, 1, 'Getting Started with PHP 8.5', 'PHP 8.5 brings exciting new features...', 1250),
(2, 1, 'Understanding Async Programming', 'Async programming is essential...', 890),
(3, 2, 'Docker Best Practices', 'Container orchestration tips...', 2100),
(4, 2, 'CI/CD Pipeline Setup', 'Automating your deployment...', 1560),
(5, 3, 'RESTful API Design', 'Building scalable APIs...', 3200),
(6, 3, 'Database Optimization', 'Query performance tuning...', 1890);

-- Comments (2 comments per post)
INSERT INTO comments (id, post_id, author_name, body) VALUES
(1, 1, 'Dave', 'Great introduction!'),
(2, 1, 'Eve', 'Very helpful, thanks!'),
(3, 2, 'Frank', 'Looking forward to more'),
(4, 2, 'Grace', 'Clear explanation'),
(5, 3, 'Henry', 'Exactly what I needed'),
(6, 3, 'Ivy', 'Bookmarked for later'),
(7, 4, 'Jack', 'Saved me hours of work'),
(8, 4, 'Kate', 'Perfect timing for my project'),
(9, 5, 'Leo', 'Best practices indeed'),
(10, 5, 'Mary', 'Will apply these patterns'),
(11, 6, 'Nick', 'Performance improved!'),
(12, 6, 'Olivia', 'Great tips');

-- Tags
INSERT INTO tags (id, name) VALUES
(1, 'php'),
(2, 'async'),
(3, 'docker'),
(4, 'ci-cd'),
(5, 'api'),
(6, 'database'),
(7, 'performance'),
(8, 'tutorial');

-- Post-Tags associations
INSERT INTO post_tags (post_id, tag_id) VALUES
(1, 1), (1, 8),
(2, 1), (2, 2), (2, 7),
(3, 3), (3, 8),
(4, 3), (4, 4),
(5, 5), (5, 8),
(6, 6), (6, 7);

-- Users (for embed demo dashboard)
INSERT INTO users (id, name, email, avatar_url) VALUES
(1, 'John Doe', 'john@example.com', 'https://example.com/avatars/john.png'),
(2, 'Jane Roe', 'jane@example.com', 'https://example.com/avatars/jane.png');

-- Notifications
INSERT INTO notifications (id, user_id, message, read_at) VALUES
(1, 1, 'New comment on your post', NULL),
(2, 1, 'Your post was featured', NULL),
(3, 1, 'Welcome to the platform!', '2024-01-01 10:00:00'),
(4, 2, 'Someone followed you', NULL),
(5, 2, 'New reply to your comment', NULL);

-- Categories (for embed demo)
INSERT INTO categories (id, name, post_count) VALUES
(1, 'Technology', 45),
(2, 'Programming', 78),
(3, 'DevOps', 32),
(4, 'Database', 21),
(5, 'Tutorial', 56);

-- Activity logs
INSERT INTO activity_logs (id, user_id, action, target) VALUES
(1, 1, 'created_post', 'Getting Started with PHP 8.5'),
(2, 1, 'commented', 'Docker Best Practices'),
(3, 1, 'liked', 'RESTful API Design'),
(4, 2, 'created_post', 'Introduction to Redis'),
(5, 2, 'followed', 'Alice Johnson');
