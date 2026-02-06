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

// Handle actions.
$message = '';
if ( isset( $_GET['action'] ) ) {
    $action = $_GET['action'];

    if ( 'run' === $action ) {
        // Redirect to cron.php and capture result.
        $cron_url = dirname( $_SERVER['SCRIPT_NAME'] ) . '/cron.php?key=' . urlencode( QWE_CRON_SECRET );
        $result = @file_get_contents( 'http' . ( isset( $_SERVER['HTTPS'] ) ? 's' : '' ) . '://' . $_SERVER['HTTP_HOST'] . $cron_url );
        $data = json_decode( $result, true );
        if ( $data && isset( $data['published'] ) ) {
            $message = "Run complete: {$data['published']}/{$data['planned']} articles published.";
        } else {
            $message = 'Run triggered. Check log for details.';
        }
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
}

// Get stats.
$stats = QWE_DB::get_stats();
$pending = QWE_DB::count_pending_keywords();

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
        @media (max-width: 768px) { .grid { grid-template-columns: repeat(2, 1fr); } .actions { flex-direction: column; } }
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
                <tr><th>Category</th><th>Pending</th><th>Progress</th></tr>
                <?php
                $total_per_cat = 50; // approximate seed count
                foreach ( $categories as $slug => $name ) :
                    $count = isset( $pending[ $slug ] ) ? $pending[ $slug ] : 0;
                    $pct = $total_per_cat > 0 ? min( 100, round( ( $total_per_cat - $count ) / $total_per_cat * 100 ) ) : 0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars( $name ); ?></td>
                    <td><?php echo $count; ?></td>
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

        <!-- Pending Trending -->
        <?php if ( QWE_TRENDING_ENABLED && ! empty( $pending_trending ) ) : ?>
        <div class="section">
            <h2>Pending Trending Topics (Top 20)</h2>
            <table>
                <tr><th>Title</th><th>Source</th><th>Score</th><th>Category</th><th>Fetched</th></tr>
                <?php foreach ( $pending_trending as $t ) : ?>
                <tr>
                    <td><?php echo htmlspecialchars( mb_strimwidth( $t['title'], 0, 70, '...' ) ); ?></td>
                    <td><?php
                        if ( 'reddit' === $t['source'] ) {
                            echo 'r/' . htmlspecialchars( $t['subreddit'] );
                        } elseif ( 'hackernews' === $t['source'] ) {
                            echo 'Hacker News';
                        } else {
                            echo htmlspecialchars( $t['subreddit'] ); // RSS feed name stored in subreddit field.
                        }
                    ?></td>
                    <td><?php echo $t['score']; ?></td>
                    <td><?php echo htmlspecialchars( $t['category'] ); ?></td>
                    <td><?php echo $t['fetched_at']; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>

        <!-- Log -->
        <div class="section">
            <h2>Recent Log (Last 50 lines)</h2>
            <div class="log"><?php echo $log_content ? htmlspecialchars( $log_content ) : 'No log entries yet.'; ?></div>
        </div>
    </div>
</body>
</html>
