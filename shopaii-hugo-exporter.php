<?php
/**
 * Plugin Name: Shopaii WordPress to Hugo Exporter
 * Plugin URI: https://hugocms.net/wordpress-to-hugo-exporter
 * Description: 将 WordPress 文章、页面和产品导出为 Hugo 兼容的 Markdown 文件
 * Version: 1.0.0
 * Author: Chris
 * Author URI: https://hugocms.net
 * License: GPL-2.0+
 */

// 如果直接访问插件文件，则退出
if (!defined('ABSPATH')) {
    exit;
}

class Wp_To_Hugo_Exporter {
    /**
     * 插件实例
     *
     * @var Wp_To_Hugo_Exporter
     */
    private static $instance;

    /**
     * 插件版本
     *
     * @var string
     */
    private $version = '1.0.0';

    /**
     * 导出目录
     *
     * @var string
     */
    private $export_dir;

    /**
     * 导出计数
     *
     * @var int
     */
    private $export_count = 0;

    /**
     * 错误计数
     *
     * @var int
     */
    private $error_count = 0;

    /**
     * 内容类型配置
     *
     * @var array
     */
    private $type_config = array(
        'post' => array('layout' => 'post', 'dir' => 'posts'),
        'page' => array('layout' => 'page', 'dir' => 'pages'),
        'product' => array('layout' => 'product', 'dir' => 'products')
    );

    /**
     * 获取插件实例（单例模式）
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
     * 构造函数
     */
    private function __construct() {
        // 设置导出目录
        $this->export_dir = trailingslashit(WP_CONTENT_DIR) . 'md';

        // 添加管理菜单
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // 处理导出请求
        add_action('admin_post_wp_to_hugo_export', array($this, 'handle_export'));
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_menu_page(
            'Hugo 导出工具',
            'Hugo 导出',
            'manage_options',
            'wp-to-hugo-exporter',
            array($this, 'render_admin_page'),
            'dashicons-media-text',
            30
        );
    }

    /**
     * 渲染管理页面
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>Hugo 导出工具</h1>
            
            <?php if (isset($_GET['exported'])) : ?>
                <div class="updated notice notice-success is-dismissible">
                    <p>导出完成！共处理 <?php echo intval($_GET['total']); ?> 个项目，成功导出 <?php echo intval($_GET['exported']); ?> 个，失败 <?php echo intval($_GET['errors']); ?> 个。</p>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2 class="title">导出设置</h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="wp_to_hugo_export">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">导出内容类型</th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><span>导出内容类型</span></legend>
                                    <label><input type="radio" name="post_type" value="any" checked> 所有类型</label><br>
                                    <label><input type="radio" name="post_type" value="post"> 文章</label><br>
                                    <label><input type="radio" name="post_type" value="page"> 页面</label><br>
                                    <label><input type="radio" name="post_type" value="product"> 产品</label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('开始导出', 'primary', 'submit_export'); ?>
                </form>
            </div>
            
            <div class="card">
                <h2 class="title">导出目录信息</h2>
                <p>Markdown 文件将导出到以下目录：</p>
                <code><?php echo esc_html($this->export_dir); ?></code>
                <?php if (is_dir($this->export_dir)) : ?>
                    <p class="description">目录已存在</p>
                <?php else : ?>
                    <p class="description notice notice-warning inline">目录不存在，将在导出时创建</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * 处理导出请求
     */
    public function handle_export() {
        // 检查权限
        if (!current_user_can('manage_options')) {
            wp_die('你没有执行此操作的权限。');
        }

        // 检查导出类型
        $post_type = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : 'any';
        
        // 确保导出目录存在
        $this->ensure_export_directory_exists();

        // 开始导出
        $total = $this->export_content($post_type);

        // 重定向回管理页面并显示结果
        wp_redirect(
            add_query_arg(
                array(
                    'page' => 'wp-to-hugo-exporter',
                    'total' => $total,
                    'exported' => $this->export_count,
                    'errors' => $this->error_count
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * 确保导出目录存在
     */
    private function ensure_export_directory_exists() {
        // 创建主导出目录
        if (!is_dir($this->export_dir)) {
            mkdir($this->export_dir, 0755, true);
        }
        
        // 为每种内容类型创建子目录
        foreach ($this->type_config as $config) {
            $type_dir = trailingslashit($this->export_dir) . 'content/' . $config['dir'];
            if (!is_dir($type_dir)) {
                mkdir($type_dir, 0755, true);
            }
        }
    }

    /**
     * 导出指定类型的内容
     *
     * @param string $post_type 内容类型
     * @return int 处理的项目总数
     */
    private function export_content($post_type) {
        global $wpdb;
        
        // 构建查询条件
        $where_clause = '';
        if ($post_type != 'any') {
            $where_clause = $wpdb->prepare("AND post_type = %s", $post_type);
        } else {
            // 只查询post, page, product类型
            $where_clause = "AND post_type IN ('post', 'page', 'product')";
        }
        
        // 查询文章
        $query = "
            SELECT * FROM {$wpdb->posts} 
            WHERE post_status = 'publish' {$where_clause}
            ORDER BY post_date DESC
        ";
        $posts = $wpdb->get_results($query);
        
        $total = count($posts);
        
        // 导出每篇文章
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
     * 导出单个文章
     *
     * @param object $post 文章对象
     */
    private function export_post($post) {
        $post_id = $post->ID;
        $post_type = $post->post_type;
        
        // 跳过未知类型
        if (!isset($this->type_config[$post_type])) {
            return;
        }
        
        $config = $this->type_config[$post_type];
        $layout = $config['layout'];
        $type_dir = $config['dir'];
        
        // 构建导出目录
        $export_dir = trailingslashit($this->export_dir) . 'content/' . $type_dir;
        
        // 获取文章基本信息
        $title = $post->post_title;
        $slug = $post->post_name;
        $date = $post->post_date;
        $content = $post->post_content;
        $permalink = trailingslashit(home_url()) . $slug . '/';
        
        // 格式化日期
        $date_obj = date_create($date);
        $date_str = date_format($date_obj, 'Y-m-d');
        
        // 生成文件名
        $filename = $date_str . '-' . $slug . '.md';
        $file_path = trailingslashit($export_dir) . $filename;
        
        // 获取分类
        $categories = $this->get_categories($post_id);
        
        // 获取标签
        $tags = $this->get_tags($post_id);
        
        // 获取特色图片
        $featured_image = $this->get_featured_image($post_id);
        
        // 构建Markdown文件内容
        $md_content = "---\n";
        $md_content .= "layout: {$layout}\n";
        $md_content .= "title: \"{$this->escape_yaml_string($title)}\"\n";
        $md_content .= "slug: \"{$slug}\"\n";
        $md_content .= "permalink: \"{$permalink}\"\n";
        $md_content .= "date: {$date}\n";
        
        // 分类
        if (!empty($categories)) {
            $md_content .= "categories:\n";
            foreach ($categories as $category) {
                $md_content .= "- {$this->escape_yaml_string($category)}\n";
            }
        } else {
            $md_content .= "categories: []\n";
        }
        
        // 特色图片
        $md_content .= "featureImage: {$featured_image}\n";
        $md_content .= "image: {$featured_image}\n";
        
        // 标签
        if (!empty($tags)) {
            $md_content .= "tags: [" . implode(', ', array_map(array($this, 'escape_yaml_string_tag'), $tags)) . "]\n";
        } else {
            $md_content .= "tags: []\n";
        }
        
        // 如果是产品类型，添加额外的产品字段
        if ($post_type == 'product') {
            $md_content .= $this->get_product_metadata($post_id);
        }
        
        $md_content .= "---\n\n";
        
        // 处理内容
        $content = $this->process_content($content);
        $md_content .= $content;
        
        // 写入文件
        file_put_contents($file_path, $md_content);
        
        $this->export_count++;
    }

    /**
     * 获取文章分类
     *
     * @param int $post_id 文章ID
     * @return array 分类名称数组
     */
    private function get_categories($post_id) {
        $categories = get_the_category($post_id);
        return wp_list_pluck($categories, 'name');
    }

    /**
     * 获取文章标签
     *
     * @param int $post_id 文章ID
     * @return array 标签名称数组
     */
    private function get_tags($post_id) {
        $tags = get_the_tags($post_id);
        return $tags ? wp_list_pluck($tags, 'name') : array();
    }

    /**
     * 获取特色图片URL
     *
     * @param int $post_id 文章ID
     * @return string 特色图片URL
     */
    private function get_featured_image($post_id) {
        $image_id = get_post_thumbnail_id($post_id);
        if ($image_id) {
            return wp_get_attachment_url($image_id);
        }
        return '';
    }

    /**
     * 获取产品元数据
     *
     * @param int $post_id 文章ID
     * @return string 元数据YAML字符串
     */
    private function get_product_metadata($post_id) {
        $metadata = '';
        
        // 获取SKU
        $sku = get_post_meta($post_id, '_sku', true);
        $metadata .= "sku: \"{$this->escape_yaml_string($sku)}\"\n";
        
        // 获取产品分类
        $product_cats = wp_get_post_terms($post_id, 'product_cat', array('fields' => 'names'));
        $metadata .= "product_categories:\n";
        foreach ($product_cats as $cat) {
            $metadata .= "- {$this->escape_yaml_string($cat)}\n";
        }
        
        // 获取产品标签
        $product_tags = wp_get_post_terms($post_id, 'product_tag', array('fields' => 'names'));
        $metadata .= "product_tags:\n";
        foreach ($product_tags as $tag) {
            $metadata .= "- {$this->escape_yaml_string($tag)}\n";
        }
        
        // 获取产品URL
        $product_url = get_post_meta($post_id, '_product_url', true);
        if ($product_url) {
            $metadata .= "buy_link: {$this->escape_yaml_string($product_url)}\n";
        } else {
            // 否则使用原来的_buy_link
            $buy_link = get_post_meta($post_id, '_buy_link', true);
            if ($buy_link) {
                $metadata .= "buy_link: {$this->escape_yaml_string($buy_link)}\n";
            }
        }
        
        // 获取产品图片
        $gallery_ids = get_post_meta($post_id, '_product_image_gallery', true);
        $gallery_ids = explode(',', $gallery_ids);
        
        $metadata .= "images:\n";
        
        // 先添加特色图片
        $featured_image = $this->get_featured_image($post_id);
        if ($featured_image) {
            $metadata .= "- {$featured_image}\n";
        }
        
        // 再添加产品图库图片
        foreach ($gallery_ids as $img_id) {
            if (!empty($img_id)) {
                $img_url = wp_get_attachment_url($img_id);
                if ($img_url) {
                    $metadata .= "- {$img_url}\n";
                }
            }
        }
        
        // 获取产品简短描述
        $short_description = get_post_meta($post_id, '_short_description', true);
        if ($short_description) {
            $escaped_description = $this->escape_yaml_string($short_description);
            $metadata .= "description: >\n  {$escaped_description}\n";
        }
        
        return $metadata;
    }

    /**
     * 处理文章内容
     *
     * @param string $content 文章内容
     * @return string 处理后的内容
     */
    private function process_content($content) {
        // 这里可以添加更多内容处理逻辑
        // 例如将WordPress的短代码转换为Markdown等
        
        // 简单地转义HTML实体
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        
        return $content;
    }

    /**
     * 转义YAML字符串
     *
     * @param string $string 要转义的字符串
     * @return string 转义后的字符串
     */
    private function escape_yaml_string($string) {
        if (empty($string)) {
            return '';
        }
        
        // 替换双引号
        $string = str_replace('"', '\\"', $string);
        
        // 处理多行字符串
        $string = preg_replace('/\n/', '\\n  ', $string);
        
        return $string;
    }

    /**
     * 转义YAML标签字符串
     *
     * @param string $string 要转义的字符串
     * @return string 转义后的字符串
     */
    private function escape_yaml_string_tag($string) {
        return '"' . $this->escape_yaml_string($string) . '"';
    }
}

// 初始化插件
function wp_to_hugo_exporter_init() {
    Wp_To_Hugo_Exporter::get_instance();
}
add_action('plugins_loaded', 'wp_to_hugo_exporter_init');    
