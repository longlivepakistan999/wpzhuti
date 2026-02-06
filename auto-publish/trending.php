<?php
/**
 * Trending Topic Fetcher
 *
 * Fetches hot AI topics from Reddit AI-specific subreddits.
 * All subreddits are AI-focused so no filtering is needed.
 *
 * @package QWE_Auto_Publish
 */

require_once __DIR__ . '/db.php';

class QWE_Trending {

    /**
     * Fetch hot posts from configured Reddit AI subreddits.
     *
     * @return int Number of new trending topics added.
     */
    public static function fetch_all() {
        $subreddits = unserialize( QWE_REDDIT_SUBREDDITS );
        $total_added = 0;

        foreach ( $subreddits as $sub ) {
            $added = self::fetch_subreddit( $sub );
            $total_added += $added;
            // Be polite to Reddit: sleep between requests.
            usleep( 500000 ); // 0.5 seconds
        }

        // Clean old pending topics (older than 7 days).
        QWE_DB::clean_old_trending();

        return $total_added;
    }

    /**
     * Fetch hot posts from a single subreddit.
     *
     * Uses Reddit's public JSON API (no auth required).
     *
     * @param string $subreddit Subreddit name (without r/).
     * @return int Number of new topics added.
     */
    public static function fetch_subreddit( $subreddit ) {
        $limit = QWE_TRENDING_PER_SUB;
        $url = "https://www.reddit.com/r/{$subreddit}/hot.json?limit={$limit}&raw_json=1";

        $context = stream_context_create( array(
            'http' => array(
                'method'  => 'GET',
                'header'  => "User-Agent: QWE-AI-Academy-Bot/1.0\r\n",
                'timeout' => 10,
            ),
        ) );

        $response = @file_get_contents( $url, false, $context );

        if ( false === $response ) {
            self::log( "Failed to fetch r/{$subreddit}" );
            return 0;
        }

        $data = json_decode( $response, true );

        if ( ! isset( $data['data']['children'] ) ) {
            self::log( "Invalid response from r/{$subreddit}" );
            return 0;
        }

        $added = 0;
        foreach ( $data['data']['children'] as $child ) {
            $post = $child['data'];

            // Skip pinned/stickied posts.
            if ( ! empty( $post['stickied'] ) ) {
                continue;
            }

            // Skip posts with very low engagement.
            if ( $post['score'] < 10 ) {
                continue;
            }

            $title = trim( $post['title'] );

            // Skip very short titles.
            if ( strlen( $title ) < 15 ) {
                continue;
            }

            // Map subreddit to likely category.
            $category = self::map_subreddit_to_category( $subreddit );

            $result = QWE_DB::add_trending(
                $title,
                'reddit',
                $subreddit,
                $post['score'],
                $category
            );

            if ( $result ) {
                $added++;
            }
        }

        self::log( "r/{$subreddit}: {$added} new topics added" );
        return $added;
    }

    /**
     * Map a subreddit to the most likely tutorial category slug.
     *
     * @param string $subreddit
     * @return string Category slug.
     */
    private static function map_subreddit_to_category( $subreddit ) {
        $map = array(
            'ChatGPT'          => 'chatgpt-llms',
            'ClaudeAI'         => 'chatgpt-llms',
            'LocalLLaMA'       => 'chatgpt-llms',
            'artificial'       => 'chatgpt-llms',
            'singularity'      => 'chatgpt-llms',
            'midjourney'       => 'ai-art-design',
            'StableDiffusion'  => 'ai-art-design',
        );

        return isset( $map[ $subreddit ] ) ? $map[ $subreddit ] : 'chatgpt-llms';
    }

    /**
     * Simple log.
     */
    private static function log( $message ) {
        $time = date( 'Y-m-d H:i:s' );
        $log = "[{$time}] TRENDING: {$message}\n";
        file_put_contents( __DIR__ . '/data/auto_publish.log', $log, FILE_APPEND );
    }
}
