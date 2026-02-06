<?php
/**
 * Auto-Publish Web Admin Panel
 *
 * Access: /auto-publish/admin.php?key=YOUR_SECRET
 *
 * Features:
 *   - Dashboard stats overview
 *   - Recent articles list (longtail / trending marked)
 *   - Pending keywords count per category
 *   - Manual trigger: run, fetch trending, view log
 *   - Trending toggle
 *
 * @package QWE_Auto_Publish
 */

require_once __DIR__ . '/config.php';

// Auth check.
if ( ! isset( $_GET['key'] ) || $_GET['key'] !== QWE_CRON_SECRET ) {
    http_response_code( 403 );
    die( 'Forbidden. Append ?key=YOUR_SECRET to the URL.' );
}

require_once __DIR__ . '/db.php';
QWE_DB::init_tables();

$secret = htmlspecialchars( $_GET['key'], ENT_QUOTES, 'UTF-8' );
$base_url = '?key=' . $secret;
$categories = unserialize( QWE_CATEGORIES );

// Determine current view/tab early (needed for redirect context).
$valid_views = array( 'dashboard', 'keywords', 'trending', 'log' );
$view = isset( $_GET['view'] ) && in_array( $_GET['view'], $valid_views, true ) ? $_GET['view'] : 'dashboard';

// Handle actions.
$message = '';
if ( isset( $_GET['action'] ) ) {
    $action = $_GET['action'];

    if ( 'run' === $action ) {
        // Run generation inline (avoids HTTP timeout issues).
        require_once __DIR__ . '/trending.php';
        require_once __DIR__ . '/generator.php';
        require_once __DIR__ . '/publisher.php';

        // Set longer execution time for article generation.
        @set_time_limit( 300 );

        // Step 1: Fetch trending.
        $trending_added = 0;
        if ( QWE_TRENDING_ENABLED ) {
            $trending_added = QWE_Trending::fetch_all();
        }

        // Step 2: Calculate ratio.
        $total_articles = QWE_ARTICLES_PER_RUN;
        $trending_count = 0;
        $longtail_count = $total_articles;
        if ( QWE_TRENDING_ENABLED ) {
            $trending_count = max( 1, round( $total_articles * QWE_TRENDING_RATIO / 100 ) );
            $longtail_count = $total_articles - $trending_count;
        }

        // Step 3: Generate articles.
        $published = 0;
        for ( $i = 0; $i < $longtail_count; $i++ ) {
            $kw = QWE_DB::get_next_keyword();
            if ( ! $kw ) break;
            $article = QWE_Generator::generate( $kw['keyword'], 'longtail', $kw['category'], $kw['difficulty'] );
            if ( $article ) {
                $post_id = QWE_Publisher::publish( $article );
                if ( $post_id ) {
                    QWE_DB::mark_keyword_used( $kw['id'], $post_id );
                    QWE_DB::log_article( $post_id, $article['title'], $kw['keyword'], 'longtail', $article['category'], $article['difficulty'], $kw['id'] );
                    $published++;
                }
            }
            if ( $i < $longtail_count - 1 ) sleep( 3 );
        }
        for ( $i = 0; $i < $trending_count; $i++ ) {
            $tr = QWE_DB::get_next_trending();
            if ( ! $tr ) {
                $kw = QWE_DB::get_next_keyword();
                if ( ! $kw ) break;
                $article = QWE_Generator::generate( $kw['keyword'], 'longtail', $kw['category'], $kw['difficulty'] );
                if ( $article ) {
                    $post_id = QWE_Publisher::publish( $article );
                    if ( $post_id ) {
                        QWE_DB::mark_keyword_used( $kw['id'], $post_id );
                        QWE_DB::log_article( $post_id, $article['title'], $kw['keyword'], 'longtail', $article['category'], $article['difficulty'], $kw['id'] );
                        $published++;
                    }
                }
            } else {
                $article = QWE_Generator::generate( $tr['title'], 'trending', $tr['category'], 'beginner' );
                if ( $article ) {
                    $post_id = QWE_Publisher::publish( $article );
                    if ( $post_id ) {
                        QWE_DB::mark_trending_used( $tr['id'], $post_id, $article['title'] );
                        QWE_DB::log_article( $post_id, $article['title'], $tr['title'], 'trending', $article['category'], $article['difficulty'], $tr['id'] );
                        $published++;
                    }
                }
            }
            if ( $i < $trending_count - 1 ) sleep( 3 );
        }

        $message = "Run complete: {$published}/{$total_articles} articles published. Trending fetched: {$trending_added} topics.";
    }

    if ( 'fetch' === $action ) {
        require_once __DIR__ . '/trending.php';
        $added = QWE_Trending::fetch_all();
        $message = "Fetched trending topics: {$added} new topics added.";
    }

    if ( 'clear-log' === $action ) {
        $log_file = __DIR__ . '/data/auto_publish.log';
        if ( file_exists( $log_file ) ) {
            file_put_contents( $log_file, '' );
        }
        $message = 'Log cleared.';
    }

    // Add keyword(s).
    if ( 'add-keyword' === $action && isset( $_POST['keywords'] ) && isset( $_POST['kw_category'] ) && isset( $_POST['kw_difficulty'] ) ) {
        $raw = trim( $_POST['keywords'] );
        $cat = sanitize_slug( $_POST['kw_category'] );
        $diff = sanitize_slug( $_POST['kw_difficulty'] );
        $valid_cats = array_keys( $categories );
        $valid_diffs = array( 'beginner', 'intermediate', 'advanced' );

        if ( $raw && in_array( $cat, $valid_cats, true ) && in_array( $diff, $valid_diffs, true ) ) {
            $lines = array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', $raw ) ) );
            $added_kw = 0;
            foreach ( $lines as $line ) {
                if ( strlen( $line ) >= 5 ) {
                    $result = QWE_DB::add_keyword( $line, $cat, $diff );
                    if ( $result ) {
                        $added_kw++;
                    }
                }
            }
            $message = "Added {$added_kw} keyword(s) to '{$cat}' ({$diff}).";
        } else {
            $message = 'Invalid input. Please check category, difficulty, and keywords.';
        }
    }

    // Delete keyword.
    if ( 'delete-keyword' === $action && isset( $_GET['kw_id'] ) ) {
        $kw_id = (int) $_GET['kw_id'];
        if ( QWE_DB::delete_keyword( $kw_id ) ) {
            $message = "Keyword #{$kw_id} deleted.";
        } else {
            $message = "Could not delete keyword #{$kw_id} (may already be used).";
        }
    }

    // Delete trending topic.
    if ( 'delete-trending' === $action && isset( $_GET['tr_id'] ) ) {
        $tr_id = (int) $_GET['tr_id'];
        if ( QWE_DB::delete_trending( $tr_id ) ) {
            $message = "Trending topic #{$tr_id} deleted.";
        } else {
            $message = "Could not delete trending #{$tr_id} (may already be used).";
        }
    }
}

/**
 * Simple slug sanitizer (no WP dependency needed here).
 */
function sanitize_slug( $input ) {
    return preg_replace( '/[^a-z0-9\-]/', '', strtolower( trim( $input ) ) );
}

// Get stats.
$stats = QWE_DB::get_stats();
$pending = QWE_DB::count_pending_keywords();

// Keyword management data (only load when needed).
$kw_list = array();
$kw_total = 0;
if ( 'keywords' === $view ) {
    $valid_kw_statuses = array( 'all', 'pending', 'used' );
    $kw_filter_status = isset( $_GET['kw_status'] ) && in_array( $_GET['kw_status'], $valid_kw_statuses, true ) ? $_GET['kw_status'] : 'pending';
    $valid_kw_cats = array_merge( array( 'all' ), array_keys( $categories ) );
    $kw_filter_cat = isset( $_GET['kw_cat'] ) && in_array( $_GET['kw_cat'], $valid_kw_cats, true ) ? $_GET['kw_cat'] : 'all';
    $kw_page          = max( 1, isset( $_GET['kw_page'] ) ? (int) $_GET['kw_page'] : 1 );
    $kw_per_page      = 50;
    $kw_offset        = ( $kw_page - 1 ) * $kw_per_page;

    $kw_list  = QWE_DB::get_keywords( $kw_filter_status, $kw_filter_cat, $kw_per_page, $kw_offset );
    $kw_total = QWE_DB::count_keywords( $kw_filter_status, $kw_filter_cat );
    $kw_pages = max( 1, ceil( $kw_total / $kw_per_page ) );
}

// Get recent articles.
$pdo = QWE_DB::connect();
$recent = $pdo->query(
    "SELECT * FROM articles ORDER BY created_at DESC LIMIT 30"
)->fetchAll( PDO::FETCH_ASSOC );

// Get pending trending.
$pending_trending = $pdo->query(
    "SELECT * FROM trending WHERE status = 'pending' ORDER BY score DESC LIMIT 20"
)->fetchAll( PDO::FETCH_ASSOC );

// Get log tail.
$log_content = '';
$log_file = __DIR__ . '/data/auto_publish.log';
if ( file_exists( $log_file ) ) {
    $lines = file( $log_file );
    $log_content = implode( '', array_slice( $lines, -50 ) );
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QWE Auto-Publish Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f0f2f5; color: #1a1a2e; line-height: 1.6; }
        .header { background: linear-gradient(135deg, #4F46E5, #06B6D4); color: white; padding: 20px 30px; }
        .header h1 { font-size: 22px; font-weight: 700; }
        .header p { opacity: 0.8; font-size: 13px; margin-top: 4px; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .message { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .stat-card .number { font-size: 32px; font-weight: 800; color: #4F46E5; }
        .stat-card .label { font-size: 13px; color: #64748b; margin-top: 4px; }
        .actions { display: flex; gap: 10px; margin-bottom: 24px; flex-wrap: wrap; }
        .btn { display: inline-block; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-primary { background: #4F46E5; color: white; }
        .btn-primary:hover { background: #4338CA; }
        .btn-secondary { background: white; color: #4F46E5; border: 1px solid #4F46E5; }
        .btn-secondary:hover { background: #EEF2FF; }
        .btn-danger { background: #EF4444; color: white; }
        .btn-danger:hover { background: #DC2626; }
        .btn-success { background: #10B981; color: white; }
        .btn-success:hover { background: #059669; }
        .section { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 24px; }
        .section h2 { font-size: 16px; font-weight: 700; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #e5e7eb; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; padding: 8px 12px; background: #f8fafc; font-weight: 600; color: #64748b; border-bottom: 1px solid #e5e7eb; }
        td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; }
        tr:hover td { background: #f8fafc; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-longtail { background: #DBEAFE; color: #1D4ED8; }
        .badge-trending { background: #FEE2E2; color: #DC2626; }
        .badge-beginner { background: #D1FAE5; color: #065F46; }
        .badge-intermediate { background: #FEF3C7; color: #92400E; }
        .badge-advanced { background: #FCE7F3; color: #9D174D; }
        .badge-enabled { background: #D1FAE5; color: #065F46; }
        .badge-disabled { background: #F3F4F6; color: #6B7280; }
        .log { background: #1e293b; color: #e2e8f0; padding: 16px; border-radius: 8px; font-family: 'JetBrains Mono', monospace; font-size: 12px; line-height: 1.8; max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
        .config-table td:first-child { font-weight: 600; width: 200px; }
        .progress-bar { height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; margin-top: 6px; }
        .progress-bar__fill { height: 100%; background: linear-gradient(90deg, #4F46E5, #06B6D4); border-radius: 3px; }
        .tabs { display: flex; gap: 0; border-bottom: 2px solid #e5e7eb; margin-bottom: 24px; }
        .tab { padding: 10px 20px; text-decoration: none; color: #64748b; font-weight: 600; font-size: 14px; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s; }
        .tab:hover { color: #4F46E5; }
        .tab.active { color: #4F46E5; border-bottom-color: #4F46E5; }
        .form-row { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 12px; flex-wrap: wrap; }
        .form-row label { font-weight: 600; font-size: 13px; color: #374151; min-width: 80px; padding-top: 8px; }
        .form-row select, .form-row input[type="text"] { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; }
        .form-row textarea { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; width: 100%; min-height: 80px; font-family: inherit; resize: vertical; }
        .btn-xs { padding: 4px 10px; font-size: 12px; border-radius: 4px; }
        .pagination { display: flex; gap: 6px; align-items: center; margin-top: 16px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 6px 12px; border-radius: 6px; font-size: 13px; text-decoration: none; }
        .pagination a { background: white; color: #4F46E5; border: 1px solid #d1d5db; }
        .pagination a:hover { background: #EEF2FF; }
        .pagination .current { background: #4F46E5; color: white; border: 1px solid #4F46E5; }
        .filter-bar { display: flex; gap: 10px; margin-bottom: 16px; align-items: center; flex-wrap: wrap; }
        .filter-bar select { padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; }
        .filter-bar .btn { padding: 6px 14px; }
        .info-text { color: #64748b; font-size: 13px; }
        @media (max-width: 768px) { .grid { grid-template-columns: repeat(2, 1fr); } .actions { flex-direction: column; } .form-row { flex-direction: column; } .form-row label { min-width: auto; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>QWE Auto-Publish</h1>
        <p>AI Tutorial Auto-Generation Dashboard</p>
    </div>

    <div class="container">
        <?php if ( $message ) : ?>
            <div class="message"><?php echo htmlspecialchars( $message ); ?></div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="tabs">
            <a href="<?php echo $base_url; ?>" class="tab <?php echo 'dashboard' === $view ? 'active' : ''; ?>">Dashboard</a>
            <a href="<?php echo $base_url; ?>&view=keywords" class="tab <?php echo 'keywords' === $view ? 'active' : ''; ?>">Keywords (<?php echo $stats['pending_keywords']; ?>)</a>
            <a href="<?php echo $base_url; ?>&view=trending" class="tab <?php echo 'trending' === $view ? 'active' : ''; ?>">Trending</a>
            <a href="<?php echo $base_url; ?>&view=log" class="tab <?php echo 'log' === $view ? 'active' : ''; ?>">Log</a>
        </div>

        <?php if ( 'dashboard' === $view ) : ?>
        <!-- ===================== DASHBOARD VIEW ===================== -->

        <!-- Stats -->
        <div class="grid">
            <div class="stat-card">
                <div class="number"><?php echo $stats['total_articles']; ?></div>
                <div class="label">Total Articles</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['today_articles']; ?></div>
                <div class="label">Published Today</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color:#1D4ED8"><?php echo $stats['longtail_articles']; ?></div>
                <div class="label">Long-tail Articles</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color:#DC2626"><?php echo $stats['trending_articles']; ?></div>
                <div class="label">Trending Articles</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color:#10B981"><?php echo $stats['pending_keywords']; ?></div>
                <div class="label">Pending Keywords</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color:#F59E0B"><?php echo $stats['pending_trending']; ?></div>
                <div class="label">Pending Trending</div>
            </div>
        </div>

        <!-- Actions -->
        <div class="actions">
            <a href="<?php echo $base_url; ?>&action=run" class="btn btn-primary" onclick="return confirm('Run auto-publish now? This will generate and publish articles.')">Generate & Publish Now</a>
            <a href="<?php echo $base_url; ?>&action=fetch" class="btn btn-success">Fetch Trending Topics</a>
            <a href="<?php echo $base_url; ?>" class="btn btn-secondary">Refresh</a>
            <a href="<?php echo $base_url; ?>&action=clear-log" class="btn btn-danger" onclick="return confirm('Clear the log file?')">Clear Log</a>
        </div>

        <!-- Config -->
        <div class="section">
            <h2>Configuration</h2>
            <table class="config-table">
                <tr><td>AI Provider</td><td><?php echo QWE_AI_PROVIDER; ?></td></tr>
                <tr><td>API Key</td><td><?php echo QWE_CLAUDE_API_KEY ? '****' . substr( QWE_CLAUDE_API_KEY, -6 ) : '<span style="color:red">NOT SET</span>'; ?></td></tr>
                <tr><td>Articles Per Run</td><td><?php echo QWE_ARTICLES_PER_RUN; ?></td></tr>
                <tr><td>Post Status</td><td><?php echo QWE_POST_STATUS; ?></td></tr>
                <tr><td>Trending</td><td><span class="badge <?php echo QWE_TRENDING_ENABLED ? 'badge-enabled' : 'badge-disabled'; ?>"><?php echo QWE_TRENDING_ENABLED ? 'ENABLED' : 'DISABLED'; ?></span></td></tr>
                <tr><td>Trending Ratio</td><td><?php echo QWE_TRENDING_RATIO; ?>% trending / <?php echo 100 - QWE_TRENDING_RATIO; ?>% long-tail</td></tr>
                <tr><td>Trending Sources</td><td>Reddit (7 AI subs) + Hacker News (AI filtered) + RSS Feeds (6 AI blogs)</td></tr>
            </table>
        </div>

        <!-- Pending Keywords Per Category -->
        <div class="section">
            <h2>Pending Keywords by Category</h2>
            <table>
                <tr><th>Category</th><th>Pending / Total</th><th>Used %</th></tr>
                <?php
                // Get total keywords (pending + used) per category for accurate progress.
                $all_per_cat = $pdo->query(
                    "SELECT category, COUNT(*) as count FROM keywords GROUP BY category"
                )->fetchAll( PDO::FETCH_KEY_PAIR );
                foreach ( $categories as $slug => $name ) :
                    $count = isset( $pending[ $slug ] ) ? $pending[ $slug ] : 0;
                    $total_in_cat = isset( $all_per_cat[ $slug ] ) ? $all_per_cat[ $slug ] : 0;
                    $used = $total_in_cat - $count;
                    $pct = $total_in_cat > 0 ? max( 0, min( 100, round( $used / $total_in_cat * 100 ) ) ) : 0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars( $name ); ?></td>
                    <td><?php echo $count; ?> / <?php echo $total_in_cat; ?></td>
                    <td style="width:200px">
                        <div class="progress-bar"><div class="progress-bar__fill" style="width:<?php echo $pct; ?>%"></div></div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- Recent Articles -->
        <div class="section">
            <h2>Recent Articles (Last 30)</h2>
            <?php if ( empty( $recent ) ) : ?>
                <p style="color:#64748b">No articles published yet.</p>
            <?php else : ?>
            <table>
                <tr><th>ID</th><th>Title</th><th>Type</th><th>Category</th><th>Difficulty</th><th>Date</th></tr>
                <?php foreach ( $recent as $article ) : ?>
                <tr>
                    <td><?php echo $article['post_id']; ?></td>
                    <td><?php echo htmlspecialchars( mb_strimwidth( $article['title'], 0, 60, '...' ) ); ?></td>
                    <td><span class="badge badge-<?php echo $article['keyword_type']; ?>"><?php echo $article['keyword_type']; ?></span></td>
                    <td><?php echo htmlspecialchars( $article['category'] ); ?></td>
                    <td><span class="badge badge-<?php echo $article['difficulty']; ?>"><?php echo $article['difficulty']; ?></span></td>
                    <td><?php echo $article['created_at']; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>

        <?php endif; // end dashboard view ?>


        <?php if ( 'keywords' === $view ) : ?>
        <!-- ===================== KEYWORDS VIEW ===================== -->

        <!-- Add Keywords (Batch) -->
        <div class="section">
            <h2>Add Keywords (Batch)</h2>
            <form method="POST" action="<?php echo $base_url; ?>&action=add-keyword&view=keywords">
                <div class="form-row">
                    <label>Category</label>
                    <select name="kw_category">
                        <?php foreach ( $categories as $slug => $name ) : ?>
                        <option value="<?php echo $slug; ?>"><?php echo htmlspecialchars( $name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label>Difficulty</label>
                    <select name="kw_difficulty">
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                    </select>
                </div>
                <div class="form-row">
                    <label>Keywords</label>
                    <textarea name="keywords" placeholder="One keyword per line, e.g.:&#10;how to use ChatGPT for resume writing&#10;best AI tools for small business&#10;midjourney vs stable diffusion comparison"></textarea>
                </div>
                <div class="form-row">
                    <label></label>
                    <button type="submit" class="btn btn-primary">Add Keywords</button>
                    <span class="info-text" style="padding-top:8px">One keyword per line. Duplicates are automatically skipped.</span>
                </div>
            </form>
        </div>

        <!-- Keyword List -->
        <div class="section">
            <h2>All Keywords (<?php echo $kw_total; ?> total)</h2>

            <!-- Filters -->
            <form class="filter-bar" method="GET">
                <input type="hidden" name="key" value="<?php echo $secret; ?>">
                <input type="hidden" name="view" value="keywords">
                <select name="kw_status">
                    <option value="all" <?php echo 'all' === $kw_filter_status ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo 'pending' === $kw_filter_status ? 'selected' : ''; ?>>Pending</option>
                    <option value="used" <?php echo 'used' === $kw_filter_status ? 'selected' : ''; ?>>Used</option>
                </select>
                <select name="kw_cat">
                    <option value="all" <?php echo 'all' === $kw_filter_cat ? 'selected' : ''; ?>>All Categories</option>
                    <?php foreach ( $categories as $slug => $name ) : ?>
                    <option value="<?php echo $slug; ?>" <?php echo $slug === $kw_filter_cat ? 'selected' : ''; ?>><?php echo htmlspecialchars( $name ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-secondary">Filter</button>
            </form>

            <?php if ( empty( $kw_list ) ) : ?>
                <p class="info-text">No keywords found with current filters.</p>
            <?php else : ?>
            <table>
                <tr><th>ID</th><th>Keyword</th><th>Category</th><th>Difficulty</th><th>Status</th><th>Post ID</th><th>Action</th></tr>
                <?php foreach ( $kw_list as $kw ) : ?>
                <tr>
                    <td><?php echo $kw['id']; ?></td>
                    <td><?php echo htmlspecialchars( $kw['keyword'] ); ?></td>
                    <td><?php echo htmlspecialchars( isset( $categories[ $kw['category'] ] ) ? $categories[ $kw['category'] ] : $kw['category'] ); ?></td>
                    <td><span class="badge badge-<?php echo $kw['difficulty']; ?>"><?php echo $kw['difficulty']; ?></span></td>
                    <td>
                        <?php if ( 'used' === $kw['status'] ) : ?>
                            <span class="badge badge-trending">used</span>
                        <?php else : ?>
                            <span class="badge badge-enabled">pending</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $kw['post_id'] ? $kw['post_id'] : '-'; ?></td>
                    <td>
                        <?php if ( 'pending' === $kw['status'] ) : ?>
                        <a href="<?php echo $base_url; ?>&action=delete-keyword&kw_id=<?php echo $kw['id']; ?>&view=keywords&kw_status=<?php echo $kw_filter_status; ?>&kw_cat=<?php echo $kw_filter_cat; ?>&kw_page=<?php echo $kw_page; ?>" class="btn btn-danger btn-xs" onclick="return confirm('Delete this keyword?')">Delete</a>
                        <?php else : ?>
                        <span class="info-text">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>

            <!-- Pagination -->
            <?php if ( $kw_pages > 1 ) : ?>
            <div class="pagination">
                <?php for ( $p = 1; $p <= $kw_pages; $p++ ) : ?>
                    <?php if ( $p === $kw_page ) : ?>
                        <span class="current"><?php echo $p; ?></span>
                    <?php else : ?>
                        <a href="<?php echo $base_url; ?>&view=keywords&kw_status=<?php echo $kw_filter_status; ?>&kw_cat=<?php echo $kw_filter_cat; ?>&kw_page=<?php echo $p; ?>"><?php echo $p; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <span class="info-text">(<?php echo $kw_total; ?> keywords)</span>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php endif; // end keywords view ?>


        <?php if ( 'trending' === $view ) : ?>
        <!-- ===================== TRENDING VIEW ===================== -->

        <div class="actions">
            <a href="<?php echo $base_url; ?>&action=fetch&view=trending" class="btn btn-success">Fetch Trending Topics Now</a>
        </div>

        <div class="section">
            <h2>Pending Trending Topics (<?php echo $stats['pending_trending']; ?>)</h2>
            <p class="info-text" style="margin-bottom:12px">Topics are auto-deleted after 7 days if unused. Used topics are kept permanently as records.</p>
            <?php if ( empty( $pending_trending ) ) : ?>
                <p class="info-text">No pending trending topics.</p>
            <?php else : ?>
            <table>
                <tr><th>ID</th><th>Title</th><th>Source</th><th>Score</th><th>Category</th><th>Fetched</th><th>Action</th></tr>
                <?php foreach ( $pending_trending as $t ) : ?>
                <tr>
                    <td><?php echo $t['id']; ?></td>
                    <td><?php echo htmlspecialchars( mb_strimwidth( $t['title'], 0, 70, '...' ) ); ?></td>
                    <td><?php
                        if ( 'reddit' === $t['source'] ) {
                            echo 'r/' . htmlspecialchars( $t['subreddit'] );
                        } elseif ( 'hackernews' === $t['source'] ) {
                            echo 'Hacker News';
                        } else {
                            echo htmlspecialchars( $t['subreddit'] );
                        }
                    ?></td>
                    <td><?php echo $t['score']; ?></td>
                    <td><?php echo htmlspecialchars( $t['category'] ); ?></td>
                    <td><?php echo $t['fetched_at']; ?></td>
                    <td>
                        <a href="<?php echo $base_url; ?>&action=delete-trending&tr_id=<?php echo $t['id']; ?>&view=trending" class="btn btn-danger btn-xs" onclick="return confirm('Delete this trending topic?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>

        <?php endif; // end trending view ?>


        <?php if ( 'log' === $view ) : ?>
        <!-- ===================== LOG VIEW ===================== -->

        <div class="actions">
            <a href="<?php echo $base_url; ?>&action=clear-log&view=log" class="btn btn-danger" onclick="return confirm('Clear the log file?')">Clear Log</a>
            <a href="<?php echo $base_url; ?>&view=log" class="btn btn-secondary">Refresh</a>
        </div>

        <div class="section">
            <h2>Recent Log (Last 50 lines)</h2>
            <div class="log"><?php echo $log_content ? htmlspecialchars( $log_content ) : 'No log entries yet.'; ?></div>
        </div>

        <?php endif; // end log view ?>

    </div>
</body>
</html>
