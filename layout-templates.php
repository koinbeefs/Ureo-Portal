<?php
/**
 * DRD Layout Templates
 * Tarlac Agricultural University - Department of Research and Development
 * 
 * Standard page structure templates for consistent layouts
 * Last Updated: February 2, 2026
 */

// ===================================
// TEMPLATE 1: STANDARD PAGE LAYOUT
// ===================================
function render_standard_page($data) {
    /**
     * Standard page with header, sidebar, main content, and footer
     * 
     * $data = [
     *     'page_title' => 'Page Title',
     *     'content' => 'HTML content here',
     *     'breadcrumbs' => [['text' => 'Home', 'url' => '/'], ['text' => 'Current']]
     * ]
     */
    ob_start();
    ?>
    <?php include 'header.php'; ?>
    <?php include 'sidebar.php'; ?>
    
    <main class="main-content" id="mainContent">
        <div class="container">
            <!-- Breadcrumbs -->
            <?php if (!empty($data['breadcrumbs'])): ?>
            <nav class="breadcrumbs">
                <?php foreach ($data['breadcrumbs'] as $index => $crumb): ?>
                    <?php if ($index > 0): ?> / <?php endif; ?>
                    <?php if (!empty($crumb['url'])): ?>
                        <a href="<?php echo htmlspecialchars($crumb['url']); ?>"><?php echo htmlspecialchars($crumb['text']); ?></a>
                    <?php else: ?>
                        <span><?php echo htmlspecialchars($crumb['text']); ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>
            
            <!-- Page Header -->
            <div class="page-hero">
                <h1 class="page-title"><?php echo htmlspecialchars($data['page_title'] ?? 'Page Title'); ?></h1>
                <?php if (!empty($data['subtitle'])): ?>
                    <p class="page-subtitle"><?php echo htmlspecialchars($data['subtitle']); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Main Content -->
            <div class="page-card">
                <?php echo $data['content'] ?? ''; ?>
            </div>
        </div>
    </main>
    
    <?php include 'footer.php'; ?>
    
    <script src="/ureo/js/script.js"></script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// ===================================
// TEMPLATE 2: TWO-COLUMN LAYOUT
// ===================================
function render_two_column_page($data) {
    /**
     * Two-column layout with sidebar content
     * 
     * $data = [
     *     'page_title' => 'Title',
     *     'main_content' => 'Main HTML',
     *     'sidebar_content' => 'Sidebar HTML'
     * ]
     */
    ob_start();
    ?>
    <?php include 'header.php'; ?>
    <?php include 'sidebar.php'; ?>
    
    <main class="main-content" id="mainContent">
        <div class="container">
            <div class="page-hero">
                <h1 class="page-title"><?php echo htmlspecialchars($data['page_title'] ?? 'Page Title'); ?></h1>
            </div>
            
            <div class="page-grid">
                <!-- Main Column -->
                <div class="page-card">
                    <?php echo $data['main_content'] ?? ''; ?>
                </div>
                
                <!-- Sidebar Column -->
                <aside class="page-card">
                    <?php echo $data['sidebar_content'] ?? ''; ?>
                </aside>
            </div>
        </div>
    </main>
    
    <?php include 'footer.php'; ?>
    
    <script src="/ureo/js/script.js"></script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// ===================================
// TEMPLATE 3: FULL-WIDTH PAGE
// ===================================
function render_full_width_page($data) {
    /**
     * Full-width page without sidebar (e.g., dashboard, admin pages)
     * 
     * $data = [
     *     'page_title' => 'Title',
     *     'content' => 'HTML content'
     * ]
     */
    ob_start();
    ?>
    <?php include 'header.php'; ?>
    
    <main class="main-content" id="mainContent">
        <div class="container">
            <div class="page-hero">
                <h1 class="page-title"><?php echo htmlspecialchars($data['page_title'] ?? 'Page Title'); ?></h1>
            </div>
            
            <div class="page-card">
                <?php echo $data['content'] ?? ''; ?>
            </div>
        </div>
    </main>
    
    <?php include 'footer.php'; ?>
    
    <script src="/ureo/js/script.js"></script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// ===================================
// TEMPLATE 4: GRID LISTING PAGE
// ===================================
function render_grid_listing_page($data) {
    /**
     * Grid layout for listing items (e.g., news, publications)
     * 
     * $data = [
     *     'page_title' => 'Title',
     *     'items' => [
     *         ['title' => 'Item 1', 'image' => 'url', 'link' => 'url', 'excerpt' => 'text'],
     *         ...
     *     ]
     * ]
     */
    ob_start();
    ?>
    <?php include 'header.php'; ?>
    <?php include 'sidebar.php'; ?>
    
    <main class="main-content" id="mainContent">
        <div class="container">
            <div class="page-hero">
                <h1 class="page-title"><?php echo htmlspecialchars($data['page_title'] ?? 'Listing'); ?></h1>
            </div>
            
            <!-- Search/Filter Form -->
            <?php if (!empty($data['show_search'])): ?>
            <form class="search-form" method="GET">
                <input type="text" name="q" placeholder="Search..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                <button type="submit" class="btn btn-primary">Search</button>
            </form>
            <?php endif; ?>
            
            <!-- Grid Layout -->
            <div class="list-grid">
                <?php if (!empty($data['items'])): ?>
                    <?php foreach ($data['items'] as $item): ?>
                    <div class="list-item">
                        <?php if (!empty($item['image'])): ?>
                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" style="width: 100%; height: 200px; object-fit: cover;">
                        <?php endif; ?>
                        
                        <div class="list-item-body">
                            <h3 class="list-item-title">
                                <a href="<?php echo htmlspecialchars($item['link'] ?? '#'); ?>"><?php echo htmlspecialchars($item['title']); ?></a>
                            </h3>
                            
                            <?php if (!empty($item['excerpt'])): ?>
                            <p><?php echo htmlspecialchars($item['excerpt']); ?></p>
                            <?php endif; ?>
                            
                            <a href="<?php echo htmlspecialchars($item['link'] ?? '#'); ?>" class="btn btn-sm btn-primary">Read More</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No items found.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <?php include 'footer.php'; ?>
    
    <script src="/ureo/js/script.js"></script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// ===================================
// COMPONENT: CARD GRID
// ===================================
function render_card_grid($cards) {
    /**
     * Renders a grid of cards (e.g., statistics, features)
     * 
     * $cards = [
     *     ['icon' => '📊', 'title' => 'Title', 'number' => '123', 'description' => 'Text'],
     *     ...
     * ]
     */
    ?>
    <div class="essential-cards">
        <?php foreach ($cards as $card): ?>
        <div class="card card--stat">
            <div class="card-icon--stat"><?php echo $card['icon'] ?? ''; ?></div>
            <h3 class="card-title"><?php echo htmlspecialchars($card['title']); ?></h3>
            <?php if (!empty($card['number'])): ?>
            <div class="card-number"><?php echo htmlspecialchars($card['number']); ?></div>
            <?php endif; ?>
            <?php if (!empty($card['description'])): ?>
            <p class="card-description"><?php echo htmlspecialchars($card['description']); ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
}

// ===================================
// COMPONENT: NEWS SLIDER
// ===================================
function render_news_slider($news_items, $title = 'Latest News') {
    /**
     * Renders a news slider component
     * 
     * $news_items = [
     *     ['title' => 'Title', 'image' => 'url', 'link' => 'url', 'excerpt' => 'text', 'date' => 'date'],
     *     ...
     * ]
     */
    ?>
    <section class="section section--plain">
        <div class="container">
            <div class="section-head">
                <div>
                    <h2 class="section-title section-title--left"><?php echo htmlspecialchars($title); ?></h2>
                </div>
                <a class="btn btn-secondary" href="/ureo/header_comp/news.php">View All</a>
            </div>
            
            <div class="news-slider drd-slider">
                <div class="news-container">
                    <?php foreach ($news_items as $news): ?>
                    <div class="news-item">
                        <div class="news-card">
                            <?php if (!empty($news['image'])): ?>
                            <a href="<?php echo htmlspecialchars($news['link']); ?>" class="news-card-media">
                                <img src="<?php echo htmlspecialchars($news['image']); ?>" alt="<?php echo htmlspecialchars($news['title']); ?>">
                            </a>
                            <?php endif; ?>
                            
                            <div class="news-card-body">
                                <div class="news-card-meta">
                                    <span class="meta-pill">📰 News</span>
                                    <span class="meta-text"><?php echo htmlspecialchars($news['date']); ?></span>
                                </div>
                                
                                <h3 class="news-card-title">
                                    <a href="<?php echo htmlspecialchars($news['link']); ?>"><?php echo htmlspecialchars($news['title']); ?></a>
                                </h3>
                                
                                <p class="news-card-excerpt"><?php echo htmlspecialchars($news['excerpt']); ?></p>
                                
                                <div class="news-card-actions">
                                    <a href="<?php echo htmlspecialchars($news['link']); ?>" class="btn btn-sm btn-primary">Read More</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
    <?php
}

// ===================================
// COMPONENT: HERO SECTION
// ===================================
function render_hero_section($data) {
    /**
     * Renders a hero section with background image
     * 
     * $data = [
     *     'title' => 'Hero Title',
     *     'subtitle' => 'Hero Subtitle',
     *     'button_text' => 'Learn More',
     *     'button_url' => '/link',
     *     'bg_image' => '/path/to/image.jpg'
     * ]
     */
    ?>
    <section class="hero-section" style="background-image: url('<?php echo htmlspecialchars($data['bg_image'] ?? ''); ?>'); background-size: cover; background-position: center; padding: 100px 0; position: relative;">
        <div style="position: absolute; inset: 0; background: linear-gradient(to right, rgba(0, 100, 0, 0.8), rgba(0, 100, 0, 0.4));"></div>
        
        <div class="container" style="position: relative; z-index: 1; text-align: center; color: white;">
            <h1 style="font-size: 48px; font-weight: 700; margin-bottom: 20px;"><?php echo htmlspecialchars($data['title']); ?></h1>
            
            <?php if (!empty($data['subtitle'])): ?>
            <p style="font-size: 20px; margin-bottom: 30px; opacity: 0.95;"><?php echo htmlspecialchars($data['subtitle']); ?></p>
            <?php endif; ?>
            
            <?php if (!empty($data['button_text']) && !empty($data['button_url'])): ?>
            <a href="<?php echo htmlspecialchars($data['button_url']); ?>" class="btn btn-lg" style="background: white; color: #006400;">
                <?php echo htmlspecialchars($data['button_text']); ?>
            </a>
            <?php endif; ?>
        </div>
    </section>
    <?php
}

// ===================================
// COMPONENT: SECTION WITH HEADER
// ===================================
function render_section_with_header($title, $subtitle = '', $content = '', $view_all_url = '') {
    /**
     * Standard section with title, subtitle, and optional "View All" button
     */
    ?>
    <section class="section section--plain">
        <div class="container">
            <div class="section-head">
                <div>
                    <h2 class="section-title section-title--left"><?php echo htmlspecialchars($title); ?></h2>
                    <?php if (!empty($subtitle)): ?>
                    <p class="section-subtitle section-subtitle--left"><?php echo htmlspecialchars($subtitle); ?></p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($view_all_url)): ?>
                <a class="btn btn-secondary" href="<?php echo htmlspecialchars($view_all_url); ?>">View All</a>
                <?php endif; ?>
            </div>
            
            <?php echo $content; ?>
        </div>
    </section>
    <?php
}

// ===================================
// USAGE EXAMPLES (COMMENTED OUT)
// ===================================

/*
// Example 1: Standard Page
$page_data = [
    'page_title' => 'About DRD',
    'subtitle' => 'Learn more about our department',
    'breadcrumbs' => [
        ['text' => 'Home', 'url' => '/ureo/'],
        ['text' => 'About']
    ],
    'content' => '<p>This is the main content...</p>'
];
echo render_standard_page($page_data);

// Example 2: Grid Listing
$listing_data = [
    'page_title' => 'Research Publications',
    'show_search' => true,
    'items' => [
        [
            'title' => 'Publication 1',
            'image' => '/path/to/image.jpg',
            'link' => '/publication/1',
            'excerpt' => 'Short description...'
        ]
    ]
];
echo render_grid_listing_page($listing_data);

// Example 3: Card Grid Component
$cards = [
    ['icon' => '📊', 'title' => 'Active Projects', 'number' => '45', 'description' => 'Ongoing research projects'],
    ['icon' => '📚', 'title' => 'Publications', 'number' => '120', 'description' => 'Research papers published']
];
render_card_grid($cards);
*/
