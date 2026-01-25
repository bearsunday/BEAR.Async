#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Small dataset for fast benchmarking
 * 100 authors, 1,000 posts, 5,000 comments
 */

require dirname(__DIR__) . '/bootstrap.php';

$pdo = createPdo();

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['comments', 'post_tags', 'posts', 'tags', 'notifications', 'activity_logs', 'users', 'categories', 'authors'] as $t) {
    $pdo->exec("TRUNCATE TABLE {$t}");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

// Authors: 100
$values = [];
for ($i = 1; $i <= 100; $i++) {
    $values[] = "({$i}, 'Author {$i}', 'author{$i}@example.com')";
}
$pdo->exec('INSERT INTO authors (id, name, email) VALUES ' . implode(',', $values));
echo "Authors: 100\n";

// Tags: 20
$values = [];
for ($i = 1; $i <= 20; $i++) {
    $values[] = "({$i}, 'tag-{$i}')";
}
$pdo->exec('INSERT INTO tags (id, name) VALUES ' . implode(',', $values));
echo "Tags: 20\n";

// Posts: 1,000 (batch of 100)
for ($batch = 0; $batch < 10; $batch++) {
    $values = [];
    for ($j = 1; $j <= 100; $j++) {
        $i = $batch * 100 + $j;
        $aid = (($i - 1) % 100) + 1;
        $views = rand(0, 5000);
        $values[] = "({$i}, {$aid}, 'Post {$i}', 'Body of post {$i}', {$views})";
    }
    $pdo->exec('INSERT INTO posts (id, author_id, title, body, view_count) VALUES ' . implode(',', $values));
}
echo "Posts: 1,000\n";

// Comments: 5,000 (batch of 500)
for ($batch = 0; $batch < 10; $batch++) {
    $values = [];
    for ($j = 1; $j <= 500; $j++) {
        $i = $batch * 500 + $j;
        $pid = (($i - 1) % 1000) + 1;
        $values[] = "({$i}, {$pid}, 'Commenter', 'Comment {$i}')";
    }
    $pdo->exec('INSERT INTO comments (id, post_id, author_name, body) VALUES ' . implode(',', $values));
}
echo "Comments: 5,000\n";

// Post-Tags: 2 per post
$values = [];
for ($i = 1; $i <= 1000; $i++) {
    $t1 = ($i % 20) + 1;
    $t2 = (($i + 7) % 20) + 1;
    $values[] = "({$i}, {$t1})";
    $values[] = "({$i}, {$t2})";
}
$pdo->exec('INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES ' . implode(',', $values));
echo "Post-Tags: 2,000\n";

// Users: 10
$values = [];
for ($i = 1; $i <= 10; $i++) {
    $values[] = "({$i}, 'User {$i}', 'user{$i}@example.com', 'https://example.com/avatar{$i}.png')";
}
$pdo->exec('INSERT INTO users (id, name, email, avatar_url) VALUES ' . implode(',', $values));
echo "Users: 10\n";

echo "\nDone.\n";
