<?php
/**
 * Plugin Name: Shopaii WordPress to Hugo Exporter
 * Plugin URI: https://hugocms.net/wordpress-to-hugo-exporter
 * Description: Export WordPress posts, pages, products and categories to Hugo-compatible Markdown files
 * Version: 1.1.6
 * Author: Chris
 * Author URI: https://hugocms.net
 * License: GPL-2.0+
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Wp_To_Hugo_Exporter {
    /**
     * Plugin instance
     *
     * @var Wp_To_Hugo_Exporter
     */
    private static $instance;

    /**
     * Plugin version
     *
     * @var string
     */
    private $version = '1.1.6';

    /**
     * Export directory option name
     *
     * @var string
     */
    private $export_dir_option = 'wp_to_hugo_export_dir';

    /**
     * Export categories option name
     *
     * @var string
     */
    private $export_categories_option = 'wp_to_hugo_export_categories';
    
    /**
     * Overwrite files option name
     *
     * @var string
     */
    private $overwrite_files_option = 'wp_to_hugo_overwrite_files';
    
    /**
     * Max export count option name
     *
     * @var string
     */
    private $max_export_count_option = 'wp_to_hugo_max_export_count';

    /**
     * Export directory options
     *
     * @var array
     */
    private $export_dir_options = array(
        'default' => '/wp-content/hugo/_default_project/content/',
        'english' => '/wp-content/hugo/_default_project/content/en/'
    );

    /**
     * Export count
     *
     * @var int
     */
    private $export_count = 0;

    /**
     * Error count
     *
     * @var int
     */
    private $error_count = 0;
    
    /**
     * Skip count
     *
     * @var int
     */
    private $skip_count = 0;

    /**
     * Content type configuration
     * Note: Changed post directory from 'posts' to 'blog'
     *
     * @var array
     */
    private $type_config = array(
        'post' => array('layout' => 'post', 'dir' => 'blog'),
        'page' => array('layout' => 'page', 'dir' => 'pages'),
        'product' => array('layout' => 'product', 'dir' => 'products')
    );

    /**
     * Taxonomy configuration
     *
     * @var array
     */
    private $taxonomy_config = array(
        'category' => array('dir' => 'categories', 'layout' => 'category'),
        'product_cat' => array('dir' => 'product_categories', 'layout' => 'product_category')
    );

    /**
     * Get plugin instance (singleton pattern)
     *
     * @return Wp_To_Hugo_Exporter
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Handle export requests
        add_action('admin_post_wp_to_hugo_export', array($this, 'handle_export'));
        
        // Register settings (for storage only, no separate save functionality)
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Add top-level menu directly as the main functional menu
        add_menu_page(
            'HUGOCMS exporter',  // Page title
            'HUGOCMS exporter',  // Menu name
            'manage_options',    // Required capability
            'wp-to-hugo-exporter', // Menu slug
            array($this, 'render_admin_page'), // Page rendering callback
            'dashicons-media-text', // Menu icon
            30                   // Menu position
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('wp_to_hugo_options', $this->export_dir_option, 'sanitize_text_field');
        register_setting('wp_to_hugo_options', $this->export_categories_option, 'sanitize_text_field');
        register_setting('wp_to_hugo_options', $this->overwrite_files_option, 'sanitize_text_field');
        register_setting('wp_to_hugo_options', $this->max_export_count_option, 'intval');
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>Hugo Export Tool</h1>
            
            <?php if (isset($_GET['exported'])) : ?>
                <div class="updated notice notice-success is-dismissible">
                    <p>Export completed! Total items processed: <?php echo intval($_GET['total']); ?>, successfully exported: <?php echo intval($_GET['exported']); ?>, skipped: <?php echo intval($_GET['skipped']); ?>, failed: <?php echo intval($_GET['errors']); ?>.</p>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2 class="title">Perform Export</h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="wp_to_hugo_export">
                    
                    <h3>Export Settings</h3>
                    <table class="form-table">
                        <!-- Export directory setting -->
                        <tr>
                            <th scope="row">Export Directory</th>
                            <td>
                                <?php 
                                $selected_dir = get_option($this->export_dir_option, 'default');
                                foreach ($this->export_dir_options as $key => $path) {
                                    $label = ($key === 'default') ? 'Default Directory' : 'English Directory';
                                    echo "<label><input type='radio' name='{$this->export_dir_option}' value='{$key}' " . 
                                        checked($selected_dir, $key, false) . "> {$label}: <code>{$path}</code></label><br>";
                                }
                                ?>
                            </td>
                        </tr>
                        
                        <!-- Export categories setting -->
                        <tr>
                            <th scope="row">Export Categories</th>
                            <td>
                                <?php
                                $selected_cat = get_option($this->export_categories_option, 'all');
                                $options = array(
                                    'all' => 'All categories (post categories and product categories)',
                                    'post' => 'Post categories only',
                                    'product' => 'Product categories only',
                                    'none' => 'Do not export categories'
                                );
                                
                                foreach ($options as $key => $label) {
                                    echo "<label><input type='radio' name='{$this->export_categories_option}' value='{$key}' " . 
                                        checked($selected_cat, $key, false) . "> {$label}</label><br>";
                                }
                                ?>
                            </td>
                        </tr>
                        
                        <!-- Overwrite files setting -->
                        <tr>
                            <th scope="row">Overwrite Options</th>
                            <td>
                                <?php
                                $selected_overwrite = get_option($this->overwrite_files_option, 'no');
                                echo "<label><input type='radio' name='{$this->overwrite_files_option}' value='yes' " . 
                                    checked($selected_overwrite, 'yes', false) . "> Yes - Overwrite existing files</label><br>";
                                echo "<label><input type='radio' name='{$this->overwrite_files_option}' value='no' " . 
                                    checked($selected_overwrite, 'no', false) . "> No - Skip existing files</label><br>";
                                ?>
                            </td>
                        </tr>
                        
                        <!-- Max export count setting -->
                        <tr>
                            <th scope="row">Maximum Export Count</th>
                            <td>
                                <?php
                                $max_count = get_option($this->max_export_count_option, 1000);
                                echo "<input type='number' name='{$this->max_export_count_option}' value='{$max_count}' min='1' max='10000' style='width: 100px;'>";
                                echo "<p class='description'>Set the maximum number of records to export</p>";
                                ?>
                            </td>
                        </tr>
                        
                        <!-- Content type selection -->
                        <tr>
                            <th scope="row">Content Type to Export</th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><span>Content Type to Export</span></legend>
                                    <label><input type="radio" name="post_type" value="any" checked> All types</label><br>
                                    <label><input type="radio" name="post_type" value="post"> Posts</label><br>
                                    <label><input type="radio" name="post_type" value="page"> Pages</label><br>
                                    <label><input type="radio" name="post_type" value="product"> Products</label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('Start Export', 'primary', 'submit_export'); ?>
                </form>
            </div>
            
            <div class="card">
                <h2 class="title">Export Directory Information</h2>
                <p>Markdown files will be exported to the following directory:</p>
                <code><?php echo esc_html($this->get_export_directory()); ?></code>
                <?php if (is_dir($this->get_export_directory())) : ?>
                    <p class="description">Directory exists</p>
                <?php else : ?>
                    <p class="description notice notice-warning inline">Directory does not exist, will be created during export</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get export directory
     *
     * @return string
     */
    private function get_export_directory() {
        $option = get_option($this->export_dir_option, 'default');
        return ABSPATH . ltrim($this->export_dir_options[$option], '/');
    }

    /**
     * Handle export request
     */
    public function handle_export() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to perform this action.');
        }

        // Save current settings (from form submission)
        update_option($this->export_dir_option, sanitize_text_field($_POST[$this->export_dir_option]));
        update_option($this->export_categories_option, sanitize_text_field($_POST[$this->export_categories_option]));
        update_option($this->overwrite_files_option, sanitize_text_field($_POST[$this->overwrite_files_option]));
        update_option($this->max_export_count_option, intval($_POST[$this->max_export_count_option]));
        
        // Check export type
        $post_type = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : 'any';
        
        // Get maximum export count
        $max_export_count = get_option($this->max_export_count_option, 1000);
        
        // Ensure export directory exists
        $this->ensure_export_directory_exists();

        // Export content
        $total_content = $this->export_content($post_type, $max_export_count);
        
        // Export categories
        $export_categories = get_option($this->export_categories_option, 'all');
        $total_categories = 0;
        
        if ($export_categories !== 'none') {
            $taxonomies = array();
            
            if ($export_categories === 'all' || $export_categories === 'post') {
                $taxonomies[] = 'category';
            }
            
            if ($export_categories === 'all' || $export_categories === 'product') {
                $taxonomies[] = 'product_cat';
            }
            
            // No limit on category exports
            foreach ($taxonomies as $taxonomy) {
                $total_categories += $this->export_taxonomy_terms($taxonomy);
            }
        }
        
        $total = $total_content + $total_categories;

        // Redirect back to admin page with results
        wp_redirect(
            add_query_arg(
                array(
                    'page' => 'wp-to-hugo-exporter',
                    'total' => $total,
                    'exported' => $this->export_count,
                    'skipped' => $this->skip_count,
                    'errors' => $this->error_count
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * Ensure export directory exists
     */
    private function ensure_export_directory_exists() {
        $export_dir = $this->get_export_directory();
        
        // Create main export directory
        if (!is_dir($export_dir)) {
            mkdir($export_dir, 0755, true);
        }
        
        // Create subdirectories for each content type
        foreach ($this->type_config as $config) {
            $type_dir = trailingslashit($export_dir) . $config['dir'];
            if (!is_dir($type_dir)) {
                mkdir($type_dir, 0755, true);
            }
        }
        
        // Create subdirectories for each taxonomy type
        foreach ($this->taxonomy_config as $config) {
            $taxonomy_dir = trailingslashit($export_dir) . $config['dir'];
            if (!is_dir($taxonomy_dir)) {
                mkdir($taxonomy_dir, 0755, true);
            }
        }
    }

    /**
     * Export content of specified type
     *
     * @param string $post_type Content type
     * @param int $max_count Maximum export count
     * @return int Total number of items processed
     */
    private function export_content($post_type, $max_count) {
        global $wpdb;
        
        // Build query conditions
        $where_clause = '';
        if ($post_type != 'any') {
            $where_clause = $wpdb->prepare("AND post_type = %s", $post_type);
        } else {
            // Only query post, page, product types
            $where_clause = "AND post_type IN ('post', 'page', 'product')";
        }
        
        // Add LIMIT clause if maximum count is set
        $limit_clause = '';
        if ($max_count > 0) {
            $limit_clause = $wpdb->prepare("LIMIT %d", $max_count);
        }
        
        // Query posts
        $query = "
            SELECT * FROM {$wpdb->posts} 
            WHERE post_status = 'publish' {$where_clause}
            ORDER BY post_date DESC
            {$limit_clause}
        ";
        $posts = $wpdb->get_results($query);
        
        $total = count($posts);
        
        // Export each post
        foreach ($posts as $post) {
            try {
                $this->export_post($post);
            } catch (Exception $e) {
                $this->error_count++;
            }
        }
        
        return $total;
    }

    /**
     * Export a single post
     *
     * @param object $post Post object
     */
    private function export_post($post) {
        $post_id = $post->ID;
        $post_type = $post->post_type;
        
        // Skip unknown types
        if (!isset($this->type_config[$post_type])) {
            return;
        }
        
        $config = $this->type_config[$post_type];
        $layout = $config['layout'];
        $type_dir = $config['dir'];
        
        // Build export directory
        $export_dir = trailingslashit($this->get_export_directory()) . $type_dir;
        
        // Get basic post information
        $title = $post->post_title;
        $slug = $post->post_name;
        $date = $post->post_date;
        $content = $post->post_content;
        $permalink = trailingslashit(home_url()) . $slug . '/';
        
        // Format date
        $date_obj = date_create($date);
        $date_str = date_format($date_obj, 'Y-m-d');
        
        // Generate filename
        $filename = $date_str . '-' . $slug . '.md';
        $file_path = trailingslashit($export_dir) . $filename;
        
        // Check if overwriting files
        $overwrite_files = get_option($this->overwrite_files_option, 'no') === 'yes';
        
        // If file exists and not overwriting, skip
        if (file_exists($file_path) && !$overwrite_files) {
            $this->skip_count++;
            return;
        }
        
        // Get categories
        $categories = $this->get_categories($post_id);
        
        // Get tags
        $tags = $this->get_tags($post_id);
        
        // Get featured image
        $featured_image = $this->get_featured_image($post_id);
        
        // Build Markdown file content
        $md_content = "---\n";
        $md_content .= "layout: {$layout}\n";
        $md_content .= "title: \"{$this->escape_yaml_string($title)}\"\n";
        $md_content .= "slug: \"{$slug}\"\n";
        $md_content .= "url: \"{$this->get_hugo_url($post)}\"\n";
        $md_content .= "permalink: \"{$permalink}\"\n";
        $md_content .= "date: {$date}\n";
        
        // Categories
        if (!empty($categories)) {
            $md_content .= "categories:\n";
            foreach ($categories as $category) {
                $md_content .= "- {$this->escape_yaml_string($category)}\n";
            }
        } else {
            $md_content .= "categories: []\n";
        }
        
        // Featured image
        $md_content .= "featureImage: {$featured_image}\n";
        $md_content .= "image: {$featured_image}\n";
        
        // Tags
        if (!empty($tags)) {
            $md_content .= "tags: [" . implode(', ', array_map(array($this, 'escape_yaml_string_tag'), $tags)) . "]\n";
        } else {
            $md_content .= "tags: []\n";
        }
        
        // If product type, add additional product fields
        if ($post_type == 'product') {
            $md_content .= $this->get_product_metadata($post_id);
        }
        
        $md_content .= "---\n\n";
        
        // Process content
        $content = $this->process_content($content);
        $md_content .= $content;
        
        // Write to file
        file_put_contents($file_path, $md_content);
        
        $this->export_count++;
    }

    /**
     * Get Hugo-style URL
     * Note: Changed post URL path from /posts/ to /blog/
     *
     * @param object $post Post object
     * @return string Hugo-style URL
     */
    private function get_hugo_url($post) {
        $post_type = $post->post_type;
        $slug = $post->post_name;
        
        // Handle URL formats for different content types
        switch ($post_type) {
            case 'post':
                return "/blog/{$slug}/";
            case 'page':
                return "/{$slug}/";
            case 'product':
                return "/products/{$slug}/";
            default:
                return "/{$slug}/";
        }
    }

    /**
     * Export all terms of a specific taxonomy
     *
     * @param string $taxonomy Taxonomy name
     * @return int Total number of items processed
     */
    private function export_taxonomy_terms($taxonomy) {
        if (!isset($this->taxonomy_config[$taxonomy])) {
            return 0;
        }
        
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false
        ));
        
        $total = count($terms);
        
        foreach ($terms as $term) {
            try {
                $this->export_taxonomy_term($term, $taxonomy);
            } catch (Exception $e) {
                $this->error_count++;
            }
        }
        
        return $total;
    }

    /**
     * Export a single taxonomy term
     *
     * @param object $term Term object
     * @param string $taxonomy Taxonomy name
     */
    private function export_taxonomy_term($term, $taxonomy) {
        $config = $this->taxonomy_config[$taxonomy];
        $layout = $config['layout'];
        $dir_name = $config['dir'];
        
        // Build export directory
        $export_dir = trailingslashit($this->get_export_directory()) . $dir_name;
        
        // Get basic information
        $term_id = $term->term_id;
        $name = $term->name;
        $slug = $term->slug;
        $description = $term->description;
        $parent_id = $term->parent;
        $count = $term->count;
        
        // Generate filename
        $filename = "{$slug}.md";
        $file_path = trailingslashit($export_dir) . $filename;
        
        // Check if overwriting files
        $overwrite_files = get_option($this->overwrite_files_option, 'no') === 'yes';
        
        // If file exists and not overwriting, skip
        if (file_exists($file_path) && !$overwrite_files) {
            $this->skip_count++;
            return;
        }
        
        // Get parent category name
        $parent_name = '';
        if ($parent_id > 0) {
            $parent_term = get_term($parent_id, $taxonomy);
            if (!is_wp_error($parent_term)) {
                $parent_name = $parent_term->name;
            }
        }
        
        // Build Markdown file content
        $md_content = "---\n";
        $md_content .= "layout: {$layout}\n";
        $md_content .= "title: \"{$this->escape_yaml_string($name)}\"\n";
        $md_content .= "slug: \"{$slug}\"\n";
        $md_content .= "url: \"/{$dir_name}/{$slug}/\"\n";
        $md_content .= "taxonomy: {$taxonomy}\n";
        
        if (!empty($parent_name)) {
            $md_content .= "parent: \"{$this->escape_yaml_string($parent_name)}\"\n";
        }
        
        if (!empty($description)) {
            $md_content .= "description: \"{$this->escape_yaml_string($description)}\"\n";
        }
        
        $md_content .= "count: {$count}\n";
        $md_content .= "---\n\n";
        
        // Write to file
        file_put_contents($file_path, $md_content);
        
        $this->export_count++;
    }

    /**
     * Get post categories
     *
     * @param int $post_id Post ID
     * @return array Array of category names
     */
    private function get_categories($post_id) {
        $categories = get_the_category($post_id);
        return wp_list_pluck($categories, 'name');
    }

    /**
     * Get post tags
     *
     * @param int $post_id Post ID
     * @return array Array of tag names
     */
    private function get_tags($post_id) {
        $tags = get_the_tags($post_id);
        return $tags ? wp_list_pluck($tags, 'name') : array();
    }

    /**
     * Get featured image URL
     *
     * @param int $post_id Post ID
     * @return string Featured image URL
     */
    private function get_featured_image($post_id) {
        $image_id = get_post_thumbnail_id($post_id);
        if ($image_id) {
            return wp_get_attachment_url($image_id);
        }
        return '';
    }

    /**
     * Get product metadata
     *
     * @param int $post_id Post ID
     * @return string Metadata YAML string
     */
    private function get_product_metadata($post_id) {
        $metadata = '';
        
        // Get SKU
        $sku = get_post_meta($post_id, '_sku', true);
        $metadata .= "sku: \"{$this->escape_yaml_string($sku)}\"\n";
        
        // Get product categories
        $product_cats = wp_get_post_terms($post_id, 'product_cat', array('fields' => 'names'));
        $metadata .= "product_categories:\n";
        foreach ($product_cats as $cat) {
            $metadata .= "- {$this->escape_yaml_string($cat)}\n";
        }
        
        // Get product tags
        $product_tags = wp_get_post_terms($post_id, 'product_tag', array('fields' => 'names'));
        $metadata .= "product_tags:\n";
        foreach ($product_tags as $tag) {
            $metadata .= "- {$this->escape_yaml_string($tag)}\n";
        }
        
        // Get product URL
        $product_url = get_post_meta($post_id, '_product_url', true);
        if ($product_url) {
            $metadata .= "buy_link: {$this->escape_yaml_string($product_url)}\n";
        } else {
            // Otherwise use original _buy_link
            $buy_link = get_post_meta($post_id, '_buy_link', true);
            if ($buy_link) {
                $metadata .= "buy_link: {$this->escape_yaml_string($buy_link)}\n";
            }
        }
        
        // Get product images
        $gallery_ids = get_post_meta($post_id, '_product_image_gallery', true);
        $gallery_ids = explode(',', $gallery_ids);
        
        $metadata .= "images:\n";
        
        // Add featured image first
        $featured_image = $this->get_featured_image($post_id);
        if ($featured_image) {
            $metadata .= "- {$featured_image}\n";
        }
        
        // Then add product gallery images
        foreach ($gallery_ids as $img_id) {
            if (!empty($img_id)) {
                $img_url = wp_get_attachment_url($img_id);
                if ($img_url) {
                    $metadata .= "- {$img_url}\n";
                }
            }
        }
        
        // Get product short description
        $short_description = get_post_meta($post_id, '_short_description', true);
        if ($short_description) {
            $escaped_description = $this->escape_yaml_string($short_description);
            $metadata .= "description: >\n  {$escaped_description}\n";
        }
        
        return $metadata;
    }

    /**
     * Process post content
     *
     * @param string $content Post content
     * @return string Processed content
     */
    private function process_content($content) {
        // Additional content processing logic can be added here
        // For example converting WordPress shortcodes to Markdown
        
        // Simply decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        
        return $content;
    }

    /**
     * Escape YAML string
     *
     * @param string $string String to escape
     * @return string Escaped string
     */
    private function escape_yaml_string($string) {
        if (empty($string)) {
            return '';
        }
        
        // Replace double quotes
        $string = str_replace('"', '\\"', $string);
        
        // Handle multi-line strings
        $string = preg_replace('/\n/', '\\n  ', $string);
        
        return $string;
    }

    /**
     * Escape YAML tag string
     *
     * @param string $string String to escape
     * @return string Escaped string
     */
    private function escape_yaml_string_tag($string) {
        return '"' . $this->escape_yaml_string($string) . '"';
    }
}

// Initialize plugin
function wp_to_hugo_exporter_init() {
    Wp_To_Hugo_Exporter::get_instance();
}
add_action('plugins_loaded', 'wp_to_hugo_exporter_init');
