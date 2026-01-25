#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate extra-large dataset for production-like benchmarking
 *
 * Creates:
 * - 10,000 authors
 * - 100,000 posts
 * - 500,000 comments
 * - 200 tags
 * - 300,000 post_tags
 * - 1,000 users
 * - 20,000 notifications
 * - 50,000 activity_logs
 */

require dirname(__DIR__) . '/bootstrap.php';

$pdo = createPdo();

echo "=== Seeding Extra-Large Dataset ===\n\n";

// Disable foreign key checks
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

// Truncate all tables
$tables = ['comments', 'post_tags', 'posts', 'tags', 'notifications', 'activity_logs', 'users', 'categories', 'authors'];
foreach ($tables as $table) {
    $pdo->exec("TRUNCATE TABLE {$table}");
}
echo "Tables truncated.\n";

// Re-enable foreign key checks
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$batchSize = 1000;

// Authors: 10,000
$total = 10000;
echo "\nAuthors (0/{$total})";
$stmt = $pdo->prepare('INSERT INTO authors (id, name, email, bio, created_at) VALUES (?, ?, ?, ?, ?)');
for ($i = 1; $i <= $total; $i++) {
    $stmt->execute([
        $i,
        "Author {$i}",
        "author{$i}@example.com",
        "Bio for author {$i}. Experienced writer and contributor.",
        date('Y-m-d H:i:s', strtotime("-" . rand(0, 730) . " days")),
    ]);
    if ($i % $batchSize === 0) {
        echo "\rAuthors ({$i}/{$total})";
    }
}
echo "\rAuthors ({$total}/{$total}) done.\n";

// Tags: 200
$total = 200;
echo "Tags (0/{$total})";
$stmt = $pdo->prepare('INSERT INTO tags (id, name) VALUES (?, ?)');
for ($i = 1; $i <= $total; $i++) {
    $stmt->execute([$i, "tag-{$i}"]);
}
echo "\rTags ({$total}/{$total}) done.\n";

// Posts: 100,000
$total = 100000;
echo "Posts (0/{$total})";
$stmt = $pdo->prepare('INSERT INTO posts (id, author_id, title, body, view_count, created_at) VALUES (?, ?, ?, ?, ?, ?)');
for ($i = 1; $i <= $total; $i++) {
    $stmt->execute([
        $i,
        (($i - 1) % 10000) + 1,
        "Post Title {$i} - Lorem ipsum dolor sit amet",
        "This is the body of post {$i}. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
        rand(0, 50000),
        date('Y-m-d H:i:s', strtotime("-" . rand(0, 730) . " days")),
    ]);
    if ($i % $batchSize === 0) {
        echo "\rPosts ({$i}/{$total})";
    }
}
echo "\rPosts ({$total}/{$total}) done.\n";

// Comments: 500,000
$total = 500000;
echo "Comments (0/{$total})";
$stmt = $pdo->prepare('INSERT INTO comments (id, post_id, author_name, body, created_at) VALUES (?, ?, ?, ?, ?)');
for ($i = 1; $i <= $total; $i++) {
    $stmt->execute([
        $i,
        (($i - 1) % 100000) + 1,
        "Commenter " . rand(1, 5000),
        "Comment {$i}: Great post! This is a thoughtful response with some additional insights.",
        date('Y-m-d H:i:s', strtotime("-" . rand(0, 365) . " days")),
    ]);
    if ($i % ($batchSize * 10) === 0) {
        echo "\rComments ({$i}/{$total})";
    }
}
echo "\rComments ({$total}/{$total}) done.\n";

// Post-Tags: 3 tags per post = 300,000
$total = 100000;
echo "Post-Tags (0/{$total} posts)";
$stmt = $pdo->prepare('INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES (?, ?), (?, ?), (?, ?)');
for ($i = 1; $i <= $total; $i++) {
    $tag1 = (($i + 0) % 200) + 1;
    $tag2 = (($i + 17) % 200) + 1;
    $tag3 = (($i + 73) % 200) + 1;
    $stmt->execute([$i, $tag1, $i, $tag2, $i, $tag3]);
    if ($i % $batchSize === 0) {
        echo "\rPost-Tags ({$i}/{$total} posts)";
    }
}
echo "\rPost-Tags ({$total}/{$total} posts) done.\n";

// Users: 1,000
$total = 1000;
echo "Users (0/{$total})";
$stmt = $pdo->prepare('INSERT INTO users (id, name, email, avatar_url) VALUES (?, ?, ?, ?)');
for ($i = 1; $i <= $total; $i++) {
    $stmt->execute([
        $i,
        "User {$i}",
        "user{$i}@example.com",
        "https://example.com/avatars/user{$i}.png",
    ]);
}
echo "\rUsers ({$total}/{$total}) done.\n";

// Notifications: 20,000
$total = 20000;
echo "Notifications (0/{$total})";
$stmt = $pdo->prepare('INSERT INTO notifications (id, user_id, message, read_at, created_at) VALUES (?, ?, ?, ?, ?)');
for ($i = 1; $i <= $total; $i++) {
    $stmt->execute([
        $i,
        (($i - 1) % 1000) + 1,
        "Notification {$i}: You have a new follower or comment",
        rand(0, 1) ? date('Y-m-d H:i:s') : null,
        date('Y-m-d H:i:s', strtotime("-" . rand(0, 90) . " days")),
    ]);
    if ($i % $batchSize === 0) {
        echo "\rNotifications ({$i}/{$total})";
    }
}
echo "\rNotifications ({$total}/{$total}) done.\n";

// Activity logs: 50,000
$total = 50000;
$actions = ['post', 'comment', 'like', 'follow'];
echo "Activity logs (0/{$total})";
$stmt = $pdo->prepare('INSERT INTO activity_logs (id, user_id, action, target, created_at) VALUES (?, ?, ?, ?, ?)');
for ($i = 1; $i <= $total; $i++) {
    $stmt->execute([
        $i,
        (($i - 1) % 1000) + 1,
        $actions[array_rand($actions)],
        "target_" . rand(1, 10000),
        date('Y-m-d H:i:s', strtotime("-" . rand(0, 180) . " days")),
    ]);
    if ($i % $batchSize === 0) {
        echo "\rActivity logs ({$i}/{$total})";
    }
}
echo "\rActivity logs ({$total}/{$total}) done.\n";

// Categories: 50
$total = 50;
echo "Categories (0/{$total})";
$stmt = $pdo->prepare('INSERT INTO categories (id, name, post_count) VALUES (?, ?, ?)');
for ($i = 1; $i <= $total; $i++) {
    $stmt->execute([$i, "Category {$i}", rand(0, 2000)]);
}
echo "\rCategories ({$total}/{$total}) done.\n";

// Show summary
echo "\n=== Summary ===\n";
$counts = [
    'authors' => $pdo->query('SELECT COUNT(*) FROM authors')->fetchColumn(),
    'posts' => $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
    'comments' => $pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn(),
    'tags' => $pdo->query('SELECT COUNT(*) FROM tags')->fetchColumn(),
    'post_tags' => $pdo->query('SELECT COUNT(*) FROM post_tags')->fetchColumn(),
    'users' => $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'notifications' => $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn(),
    'activity_logs' => $pdo->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn(),
    'categories' => $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
];

foreach ($counts as $table => $count) {
    printf("%-15s %s\n", $table, number_format((int) $count));
}
echo "\nTotal rows: " . number_format(array_sum($counts)) . "\n";
