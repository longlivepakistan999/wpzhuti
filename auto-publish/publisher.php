<?php
/**
 * WordPress Publisher
 *
 * Publishes generated articles to WordPress using wp_insert_post().
 * Automatically assigns tutorial_category taxonomy and meta fields.
 * Stores keyword source info in post meta for tracking.
 *
 * @package QWE_Auto_Publish
 */

require_once __DIR__ . '/db.php';

class QWE_Publisher {

    /**
     * Ensure wp_kses_post allows image-related tags and attributes.
     * Called via wp_kses_allowed_html filter during publishing.
     *
     * @param array  $tags    Allowed tags.
     * @param string $context Context ('post', 'data', etc.).
     * @return array Modified allowed tags.
     */
    public static function allow_image_tags( $tags, $context ) {
        if ( 'post' !== $context ) {
            return $tags;
        }

        // Ensure <figure> allows class.
        if ( ! isset( $tags['figure'] ) ) {
            $tags['figure'] = array();
        }
        $tags['figure']['class'] = true;
        $tags['figure']['style'] = true;

        // Ensure <figcaption> is allowed.
        if ( ! isset( $tags['figcaption'] ) ) {
            $tags['figcaption'] = array();
        }
        $tags['figcaption']['class'] = true;

        // Ensure <img> allows srcset, sizes, loading.
        if ( ! isset( $tags['img'] ) ) {
            $tags['img'] = array();
        }
        $tags['img']['srcset']  = true;
        $tags['img']['sizes']   = true;
        $tags['img']['loading'] = true;
        $tags['img']['src']     = true;
        $tags['img']['alt']     = true;
        $tags['img']['class']   = true;
        $tags['img']['width']   = true;
        $tags['img']['height']  = true;

        // Ensure <a> allows target and rel.
        if ( ! isset( $tags['a'] ) ) {
            $tags['a'] = array();
        }
        $tags['a']['target'] = true;
        $tags['a']['rel']    = true;

        return $tags;
    }

    /**
     * Publish an article to WordPress.
     *
     * @param array $article Article data from QWE_Generator.
     * @return int|false      Post ID on success, false on failure.
     */
    public static function publish( $article ) {

        // Ensure WordPress functions are available.
        if ( ! function_exists( 'wp_insert_post' ) ) {
            self::log( 'WordPress not loaded' );
            return false;
        }

        // Sanitize slug first so duplicate check matches what WP actually stores.
        $slug = sanitize_title( $article['slug'] );

        // Check for duplicate slug.
        $existing = get_page_by_path( $slug, OBJECT, 'tutorial' );
        if ( $existing ) {
            self::log( "Duplicate slug: {$slug}, skipping" );
            return false;
        }

        // Get or create the tutorial_category term.
        $term_id = self::get_or_create_category( $article['category'] );

        // Process image placeholders BEFORE wp_kses_post (which strips HTML comments).
        $content      = $article['content'];
        $image_result = array(
            'content'           => $content,
            'featured_image_id' => 0,
            'attachment_ids'    => array(),
        );

        if ( defined( 'QWE_IMAGES_ENABLED' ) && QWE_IMAGES_ENABLED ) {
            $image_result = self::process_images( $content );
            $content      = $image_result['content'];
        }

        // Add filter to allow image-related HTML tags/attributes.
        add_filter( 'wp_kses_allowed_html', array( __CLASS__, 'allow_image_tags' ), 10, 2 );

        // Prepare post data.
        $post_data = array(
            'post_title'   => sanitize_text_field( $article['title'] ),
            'post_name'    => $slug,
            'post_content' => wp_kses_post( $content ),
            'post_excerpt' => sanitize_text_field( $article['excerpt'] ),
            'post_status'  => QWE_POST_STATUS,
            'post_type'    => 'tutorial',
            'post_author'  => QWE_AUTHOR_ID,
        );

        // Remove filter after sanitization.
        remove_filter( 'wp_kses_allowed_html', array( __CLASS__, 'allow_image_tags' ), 10 );

        // Insert the post.
        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            self::log( 'wp_insert_post error: ' . $post_id->get_error_message() );
            return false;
        }

        // Assign category.
        if ( $term_id ) {
            wp_set_object_terms( $post_id, $term_id, 'tutorial_category' );
        }

        // Assign tags (post_tag taxonomy).
        if ( ! empty( $article['tags'] ) && is_array( $article['tags'] ) ) {
            $clean_tags = array_map( 'sanitize_text_field', $article['tags'] );
            $clean_tags = array_filter( $clean_tags );
            if ( ! empty( $clean_tags ) ) {
                wp_set_post_tags( $post_id, $clean_tags );
            }
        }

        // Set difficulty level.
        update_post_meta( $post_id, '_qwe_difficulty', sanitize_text_field( $article['difficulty'] ) );

        // Don't auto-feature.
        update_post_meta( $post_id, '_qwe_featured', '0' );

        // Attach uploaded images to this post and set featured image.
        if ( ! empty( $image_result['attachment_ids'] ) ) {
            foreach ( $image_result['attachment_ids'] as $att_id ) {
                wp_update_post( array(
                    'ID'          => $att_id,
                    'post_parent' => $post_id,
                ) );
            }
        }
        if ( ! empty( $image_result['featured_image_id'] ) ) {
            set_post_thumbnail( $post_id, $image_result['featured_image_id'] );
            self::log( "Featured image set: attachment #{$image_result['featured_image_id']}" );
        }

        // Store keyword tracking meta.
        update_post_meta( $post_id, '_qwe_keyword_type', sanitize_text_field( $article['keyword_type'] ) );
        update_post_meta( $post_id, '_qwe_source_keyword', sanitize_text_field( $article['keyword'] ) );
        update_post_meta( $post_id, '_qwe_auto_generated', '1' );

        self::log( "Published: [{$post_id}] {$article['title']} ({$article['keyword_type']}: {$article['keyword']})" );

        return $post_id;
    }

    /**
     * Get the term_id for a category slug, create if it doesn't exist.
     *
     * @param string $slug Category slug from config.
     * @return int|false    Term ID or false.
     */
    private static function get_or_create_category( $slug ) {
        $categories = unserialize( QWE_CATEGORIES );
        $name = isset( $categories[ $slug ] ) ? $categories[ $slug ] : $slug;

        $term = get_term_by( 'slug', $slug, 'tutorial_category' );

        if ( $term ) {
            return $term->term_id;
        }

        // Create the term.
        $result = wp_insert_term( $name, 'tutorial_category', array(
            'slug' => $slug,
        ) );

        if ( is_wp_error( $result ) ) {
            self::log( "Failed to create category: {$slug} - " . $result->get_error_message() );
            return false;
        }

        return $result['term_id'];
    }

    // ==========================================================
    // Image Processing
    // ==========================================================

    /**
     * Process image placeholders in raw article content.
     *
     * Finds all <!-- QWE_IMAGE: {...} --> placeholders, fetches matching
     * stock photos from Pexels, uploads them to the WordPress media
     * library (unattached — caller re-attaches after post insert), and
     * replaces the placeholders with <figure> elements.
     *
     * Must be called BEFORE wp_kses_post() because wp_kses strips HTML comments.
     *
     * @param string $content Raw article HTML content with placeholders.
     * @return array {
     *     @type string $content           Processed content with <figure> tags.
     *     @type int    $featured_image_id Attachment ID for featured image (0 if none).
     *     @type array  $attachment_ids    All uploaded attachment IDs.
     * }
     */
    private static function process_images( $content ) {
        $result = array(
            'content'           => $content,
            'featured_image_id' => 0,
            'attachment_ids'    => array(),
        );

        $pexels_key         = defined( 'QWE_PEXELS_API_KEY' ) ? QWE_PEXELS_API_KEY : '';
        $screenshots_enabled = defined( 'QWE_SCREENSHOTS_ENABLED' ) && QWE_SCREENSHOTS_ENABLED;

        // If neither image source is configured, strip placeholders and bail.
        if ( empty( $pexels_key ) && ! $screenshots_enabled ) {
            self::log( 'No image sources configured — skipping image insertion' );
            $result['content'] = preg_replace( '/<!-- QWE_IMAGE: \{[^}]+\} -->/', '', $content );
            return $result;
        }

        // Find all image placeholders.
        $pattern = '/<!-- QWE_IMAGE: (\{[^}]+\}) -->/';
        if ( ! preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
            self::log( 'No image placeholders found in content' );
            return $result;
        }

        $max_images      = defined( 'QWE_IMAGES_PER_ARTICLE' ) ? (int) QWE_IMAGES_PER_ARTICLE : 3;
        $orientation     = defined( 'QWE_IMAGE_ORIENTATION' ) ? QWE_IMAGE_ORIENTATION : 'landscape';
        $size_key        = defined( 'QWE_IMAGE_SIZE' ) ? QWE_IMAGE_SIZE : 'large';
        $images_inserted = 0;

        // Load required WordPress functions for media handling.
        if ( ! function_exists( 'media_handle_sideload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        foreach ( $matches as $match ) {
            if ( $images_inserted >= $max_images ) {
                $content = str_replace( $match[0], '', $content );
                continue;
            }

            $image_data = json_decode( $match[1], true );
            if ( ! $image_data ) {
                self::log( 'Invalid image placeholder JSON: ' . $match[1] );
                $content = str_replace( $match[0], '', $content );
                continue;
            }

            $type = ! empty( $image_data['type'] ) ? $image_data['type'] : 'stock';
            $alt  = ! empty( $image_data['alt'] ) ? $image_data['alt'] : '';

            // Route to the appropriate image source.
            $attachment_id = 0;
            $credit_html   = '';

            if ( 'screenshot' === $type && $screenshots_enabled && ! empty( $image_data['url'] ) ) {
                // --- Screenshot path ---
                $target_url = $image_data['url'];
                $alt        = $alt ?: $target_url;

                $screenshot_url = self::get_screenshot_url( $target_url );
                if ( $screenshot_url ) {
                    $attachment_id = self::upload_image_to_media( $screenshot_url, $alt, 0 );
                    if ( $attachment_id ) {
                        $credit_html = '<figcaption>Screenshot: <a href="' . esc_url( $target_url ) . '" target="_blank" rel="noopener">'
                            . esc_html( self::get_domain( $target_url ) ) . '</a></figcaption>';
                        self::log( "Screenshot captured: {$target_url} → attachment #{$attachment_id}" );
                    } else {
                        self::log( "Screenshot upload failed for: {$target_url}" );
                    }
                } else {
                    self::log( "Screenshot URL generation failed for: {$target_url}" );
                }

                // Fallback to stock photo if screenshot failed and we have a Pexels key.
                if ( ! $attachment_id && ! empty( $pexels_key ) ) {
                    $fallback_query = ! empty( $image_data['query'] ) ? $image_data['query'] : self::get_domain( $target_url ) . ' website';
                    self::log( "Screenshot fallback to stock for: {$fallback_query}" );
                    $type = 'stock';
                    $image_data['query'] = $fallback_query;
                }
            }

            if ( 'stock' === $type && ! empty( $pexels_key ) ) {
                // --- Stock photo path ---
                $query = ! empty( $image_data['query'] ) ? $image_data['query'] : '';
                if ( empty( $query ) ) {
                    $content = str_replace( $match[0], '', $content );
                    continue;
                }
                $alt = $alt ?: $query;

                $photo = self::search_pexels( $pexels_key, $query, $orientation );
                if ( $photo ) {
                    $image_url = self::get_pexels_image_url( $photo, $size_key );
                    if ( $image_url ) {
                        $attachment_id = self::upload_image_to_media( $image_url, $alt, 0, $photo );
                        if ( $attachment_id ) {
                            $photographer = ! empty( $photo['photographer'] ) ? $photo['photographer'] : '';
                            if ( $photographer ) {
                                $photo_url   = ! empty( $photo['photographer_url'] ) ? $photo['photographer_url'] : '';
                                $credit_html = '<figcaption>Photo by ';
                                if ( $photo_url ) {
                                    $credit_html .= '<a href="' . esc_url( $photo_url ) . '" target="_blank" rel="noopener">';
                                    $credit_html .= esc_html( $photographer ) . '</a>';
                                } else {
                                    $credit_html .= esc_html( $photographer );
                                }
                                $credit_html .= ' / <a href="https://www.pexels.com" target="_blank" rel="noopener">Pexels</a>';
                                $credit_html .= '</figcaption>';
                            }
                            self::log( "Stock image inserted: \"{$query}\" → attachment #{$attachment_id}" );
                        }
                    }
                }

                if ( ! $attachment_id ) {
                    self::log( "Pexels: no usable result for \"{$query}\" — removing placeholder" );
                }
            }

            // If no image was obtained from either source, remove placeholder.
            if ( ! $attachment_id ) {
                $content = str_replace( $match[0], '', $content );
                continue;
            }

            $result['attachment_ids'][] = $attachment_id;

            // Build the <figure> HTML replacement.
            $img_src    = wp_get_attachment_url( $attachment_id );
            $img_srcset = wp_get_attachment_image_srcset( $attachment_id, 'large' );
            $img_sizes  = wp_get_attachment_image_sizes( $attachment_id, 'large' );

            $figure_html = '<figure class="article-image">';
            $figure_html .= '<img src="' . esc_url( $img_src ) . '"';
            if ( $img_srcset ) {
                $figure_html .= ' srcset="' . esc_attr( $img_srcset ) . '"';
            }
            if ( $img_sizes ) {
                $figure_html .= ' sizes="' . esc_attr( $img_sizes ) . '"';
            }
            $figure_html .= ' alt="' . esc_attr( $alt ) . '"';
            $figure_html .= ' loading="lazy" />';
            $figure_html .= $credit_html;
            $figure_html .= '</figure>';

            $content = str_replace( $match[0], $figure_html, $content );
            $images_inserted++;

            if ( 0 === $result['featured_image_id'] ) {
                $result['featured_image_id'] = $attachment_id;
            }
        }

        $result['content'] = $content;
        self::log( "Images processed: {$images_inserted} inserted" );

        return $result;
    }

    // ==========================================================
    // Screenshot Capture
    // ==========================================================

    /**
     * Generate a screenshot URL for a target webpage.
     *
     * Supports two providers:
     * - 'thum': Free, no API key needed. Uses thum.io service.
     * - 'screenshotone': Better quality, needs API key. Free tier: 100/month.
     *
     * @param string $target_url The webpage URL to screenshot.
     * @return string|false      Screenshot image URL or false on failure.
     */
    private static function get_screenshot_url( $target_url ) {
        // Validate URL.
        if ( ! filter_var( $target_url, FILTER_VALIDATE_URL ) ) {
            self::log( "Invalid screenshot URL: {$target_url}" );
            return false;
        }

        // Block private/local URLs to prevent SSRF.
        $host = parse_url( $target_url, PHP_URL_HOST );
        if ( ! $host || self::is_private_host( $host ) ) {
            self::log( "Blocked private/local screenshot URL: {$target_url}" );
            return false;
        }

        $provider = defined( 'QWE_SCREENSHOT_PROVIDER' ) ? QWE_SCREENSHOT_PROVIDER : 'thum';
        $width    = defined( 'QWE_SCREENSHOT_WIDTH' ) ? (int) QWE_SCREENSHOT_WIDTH : 1280;
        $height   = defined( 'QWE_SCREENSHOT_HEIGHT' ) ? (int) QWE_SCREENSHOT_HEIGHT : 800;

        if ( 'screenshotone' === $provider ) {
            return self::get_screenshotone_url( $target_url, $width, $height );
        }

        // Default: thum.io (free, no key needed).
        return self::get_thum_url( $target_url, $width, $height );
    }

    /**
     * Generate screenshot URL using thum.io (free service).
     *
     * thum.io provides free website thumbnails with no API key.
     * Rate limits are generous for low-volume use.
     *
     * @param string $url    Target URL.
     * @param int    $width  Viewport width.
     * @param int    $height Crop height.
     * @return string Screenshot URL.
     */
    private static function get_thum_url( $url, $width, $height ) {
        // thum.io URL format: https://image.thum.io/get/width/W/crop/H/URL
        return 'https://image.thum.io/get/width/' . $width . '/crop/' . $height . '/' . $url;
    }

    /**
     * Generate screenshot URL using ScreenshotOne API.
     *
     * ScreenshotOne provides higher quality screenshots with more options.
     * Free tier: 100 screenshots/month.
     *
     * @param string $url    Target URL.
     * @param int    $width  Viewport width.
     * @param int    $height Viewport height.
     * @return string|false  Screenshot URL or false if not configured.
     */
    private static function get_screenshotone_url( $url, $width, $height ) {
        $api_key = defined( 'QWE_SCREENSHOTONE_API_KEY' ) ? QWE_SCREENSHOTONE_API_KEY : '';
        if ( empty( $api_key ) ) {
            self::log( 'ScreenshotOne API key not configured — falling back to thum.io' );
            return self::get_thum_url( $url, $width, $height );
        }

        return 'https://api.screenshotone.com/take?' . http_build_query( array(
            'access_key'      => $api_key,
            'url'             => $url,
            'viewport_width'  => $width,
            'viewport_height' => $height,
            'format'          => 'jpg',
            'image_quality'   => 80,
            'block_ads'       => true,
            'block_cookie_banners' => true,
            'delay'           => 2,
        ) );
    }

    /**
     * Check if a hostname resolves to a private/local IP address.
     * Prevents SSRF attacks through screenshot APIs.
     *
     * @param string $host Hostname to check.
     * @return bool True if private/local.
     */
    private static function is_private_host( $host ) {
        // Block obvious local hostnames.
        $blocked = array( 'localhost', '127.0.0.1', '0.0.0.0', '::1', '[::1]' );
        if ( in_array( strtolower( $host ), $blocked, true ) ) {
            return true;
        }

        // Resolve hostname and check IP ranges.
        $ip = gethostbyname( $host );
        if ( $ip === $host ) {
            return true; // DNS resolution failed.
        }

        // Check private/reserved IP ranges.
        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Extract the domain name from a URL for display purposes.
     *
     * @param string $url Full URL.
     * @return string Domain name (e.g., "openai.com").
     */
    private static function get_domain( $url ) {
        $host = parse_url( $url, PHP_URL_HOST );
        if ( ! $host ) {
            return $url;
        }
        // Strip 'www.' prefix.
        return preg_replace( '/^www\./', '', $host );
    }

    // ==========================================================
    // Pexels Stock Photos
    // ==========================================================

    /**
     * Search the Pexels API for a photo matching the query.
     *
     * Returns the first result that best matches. Uses a random offset
     * (page 1-3) to avoid always picking the same popular photo.
     *
     * @param string $api_key     Pexels API key.
     * @param string $query       Search terms.
     * @param string $orientation 'landscape', 'portrait', or 'square'.
     * @return array|false        Photo data array or false.
     */
    private static function search_pexels( $api_key, $query, $orientation = 'landscape' ) {
        $page = rand( 1, 3 );
        $url  = 'https://api.pexels.com/v1/search?' . http_build_query( array(
            'query'       => $query,
            'orientation' => $orientation,
            'per_page'    => 5,
            'page'        => $page,
        ) );

        $ch = curl_init( $url );
        curl_setopt_array( $ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array(
                'Authorization: ' . $api_key,
            ),
            CURLOPT_TIMEOUT        => 15,
        ) );

        $response  = curl_exec( $ch );
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $error     = curl_error( $ch );
        curl_close( $ch );

        if ( $error ) {
            self::log( "Pexels cURL error: {$error}" );
            return false;
        }

        if ( 200 !== $http_code ) {
            self::log( "Pexels API HTTP {$http_code}: " . substr( $response, 0, 300 ) );
            return false;
        }

        $data = json_decode( $response, true );

        if ( empty( $data['photos'] ) ) {
            return false;
        }

        // Pick a random photo from the results for variety.
        $photos = $data['photos'];
        return $photos[ array_rand( $photos ) ];
    }

    /**
     * Get the image download URL at the configured size from Pexels photo data.
     *
     * @param array  $photo    Pexels photo data.
     * @param string $size_key Size key: 'original', 'large2x', 'large', 'medium', 'small'.
     * @return string|false    Image URL or false.
     */
    private static function get_pexels_image_url( $photo, $size_key = 'large' ) {
        if ( empty( $photo['src'] ) ) {
            return false;
        }

        $src = $photo['src'];

        // Try requested size, fall back through sizes.
        $fallback_order = array( 'large', 'medium', 'large2x', 'original', 'small' );

        if ( ! empty( $src[ $size_key ] ) ) {
            return $src[ $size_key ];
        }

        foreach ( $fallback_order as $key ) {
            if ( ! empty( $src[ $key ] ) ) {
                return $src[ $key ];
            }
        }

        return false;
    }

    /**
     * Download an image from URL and upload it to the WordPress media library.
     *
     * @param string $url           Image URL.
     * @param string $alt_text      Alt text for the image.
     * @param int    $post_id       Parent post ID.
     * @param array  $photo         Pexels photo data (for metadata).
     * @return int|false            Attachment ID or false.
     */
    private static function upload_image_to_media( $url, $alt_text, $post_id, $photo = array() ) {
        // Download to a temp file.
        $tmp = download_url( $url, 30 );

        if ( is_wp_error( $tmp ) ) {
            self::log( 'Image download failed: ' . $tmp->get_error_message() );
            return false;
        }

        // Build a filename from the alt text.
        $filename = sanitize_file_name( substr( sanitize_title( $alt_text ), 0, 60 ) ) . '.jpg';

        $file_array = array(
            'name'     => $filename,
            'tmp_name' => $tmp,
        );

        // Upload to media library.
        $attachment_id = media_handle_sideload( $file_array, $post_id, $alt_text );

        if ( is_wp_error( $attachment_id ) ) {
            self::log( 'Media sideload failed: ' . $attachment_id->get_error_message() );
            @unlink( $tmp );
            return false;
        }

        // Set alt text.
        update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );

        // Store Pexels attribution in attachment meta.
        if ( ! empty( $photo['photographer'] ) ) {
            update_post_meta( $attachment_id, '_qwe_photo_credit', sanitize_text_field( $photo['photographer'] ) );
        }
        if ( ! empty( $photo['photographer_url'] ) ) {
            update_post_meta( $attachment_id, '_qwe_photo_credit_url', esc_url_raw( $photo['photographer_url'] ) );
        }
        if ( ! empty( $photo['url'] ) ) {
            update_post_meta( $attachment_id, '_qwe_pexels_url', esc_url_raw( $photo['url'] ) );
        }

        return $attachment_id;
    }

    /**
     * Simple log.
     */
    private static function log( $message ) {
        $time = date( 'Y-m-d H:i:s' );
        $log = "[{$time}] PUBLISHER: {$message}\n";
        file_put_contents( __DIR__ . '/data/auto_publish.log', $log, FILE_APPEND );
    }
}
