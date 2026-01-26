<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Query;

use Ray\MediaQuery\Annotation\DbQuery;

interface AnalyticsQueryInterface
{
    // Author analytics

    /** @return array<array<mixed>> */
    #[DbQuery('top_authors_by_posts')]
    public function topAuthorsByPosts(int $limit = 20): array;

    /** @return array<array<mixed>> */
    #[DbQuery('top_authors_by_views')]
    public function topAuthorsByViews(int $limit = 20): array;

    /** @return array<array<mixed>> */
    #[DbQuery('top_authors_by_comments')]
    public function topAuthorsByComments(int $limit = 20): array;

    /** @return array<array<mixed>> */
    #[DbQuery('newest_authors')]
    public function newestAuthors(int $limit = 20): array;

    /** @return array<array<mixed>> */
    #[DbQuery('author_stats')]
    public function authorStats(int $limit = 20): array;

    // Post analytics

    /** @return array<array<mixed>> */
    #[DbQuery('popular_posts_with_stats')]
    public function popularPosts(int $limit = 20): array;

    /** @return array<array<mixed>> */
    #[DbQuery('recent_posts')]
    public function recentPosts(int $limit = 20): array;

    /** @return array<array<mixed>> */
    #[DbQuery('most_commented_posts')]
    public function mostCommentedPosts(int $limit = 20): array;

    /** @return array<array<mixed>> */
    #[DbQuery('posts_by_tag_count')]
    public function postsByTagCount(int $limit = 20): array;

    /** @return array<array<mixed>> */
    #[DbQuery('posts_with_no_comments')]
    public function postsWithNoComments(int $limit = 20): array;

    // Comment analytics

    /** @return array<array<mixed>> */
    #[DbQuery('top_commenters')]
    public function topCommenters(int $limit = 20): array;

    /** @return array<array<mixed>> */
    #[DbQuery('recent_comments')]
    public function recentComments(int $limit = 20): array;

    /** @return array<array<mixed>> */
    #[DbQuery('comments_per_post_avg')]
    public function commentsPerPostAvg(int $limit = 20): array;

    /** @return array<array<mixed>> */
    #[DbQuery('active_commenters')]
    public function activeCommenters(int $limit = 20): array;

    /** @return array<array<mixed>> */
    #[DbQuery('recent_engagement')]
    public function recentEngagement(int $limit = 20): array;

    // Tag analytics

    /** @return array<array<mixed>> */
    #[DbQuery('trending_tags')]
    public function trendingTags(int $limit = 20): array;

    /** @return array<array<mixed>> */
    #[DbQuery('tag_cloud')]
    public function tagCloud(int $limit = 20): array;

    /** @return array<array<mixed>> */
    #[DbQuery('tags_with_most_views')]
    public function tagsWithMostViews(int $limit = 20): array;

    /** @return array<array<mixed>> */
    #[DbQuery('tags_by_comment_count')]
    public function tagsByCommentCount(int $limit = 20): array;

    /** @return array<array<mixed>> */
    #[DbQuery('unused_tags')]
    public function unusedTags(int $limit = 20): array;

    // Cross-entity analytics

    /** @return array<array<mixed>> */
    #[DbQuery('author_tag_matrix')]
    public function authorTagMatrix(int $limit = 20): array;

    /** @return array<array<mixed>> */
    #[DbQuery('engagement_by_day')]
    public function engagementByDay(int $limit = 20): array;

    /** @return array<array<mixed>> */
    #[DbQuery('content_growth')]
    public function contentGrowth(int $limit = 20): array;

    /** @return array<array<mixed>> */
    #[DbQuery('top_author_posts')]
    public function topAuthorPosts(int $limit = 20): array;

    /** @return array<array<mixed>> */
    #[DbQuery('viral_posts')]
    public function viralPosts(int $limit = 20): array;
}
