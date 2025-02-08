<?php
/**
 * Plugin Name: Bear Style Like Button
 * Description: 在文章页底部增加一个居中的点赞按钮和「支持」链接，提供 AJAX 点赞功能和后台「支持」链接设置。
 * Version: 1.0
 * Author: anotherdayu.com
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// 注册前端样式和脚本
function bslb_enqueue_scripts() {
    if (is_single() && 'post' === get_post_type()) {
        wp_enqueue_style('bslb-style', plugin_dir_url(__FILE__).'css/reaction.css');
        wp_enqueue_script('bslb-script', plugin_dir_url(__FILE__).'js/reaction.js', array('jquery'), null, true);
        // 向js传递文章ID和ajax URL
        wp_localize_script('bslb-script', 'bslb_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'post_id'  => get_the_ID(),
        ));
    }
}
add_action('wp_enqueue_scripts', 'bslb_enqueue_scripts');

// 为文章内容追加点赞和支持区域
function bslb_append_reaction($content) {
    if (is_single() && 'post' === get_post_type()) {
        // 获取当前文章的点赞数，默认为 0
        $likes = get_post_meta(get_the_ID(), 'bear_style_like_count', true);
        $likes = $likes ? intval($likes) : 0;
        // 获取「支持」链接配置，默认地址为 example.com
        $support_url = esc_url(get_option('bslb_support_url', 'https://example.com/support'));
        ob_start();
        ?>
        <div class="bslb-reaction">
            <button class="bslb-like-button" data-postid="<?php the_ID(); ?>">
                <span class="bslb-like-icon">❤️</span>
                <span class="bslb-like-count"><?php echo $likes; ?></span>
            </button>
            <a href="<?php echo $support_url; ?>" class="bslb-support-link">支持</a>
        </div>
        <?php
        $reaction_html = ob_get_clean();
        $content .= $reaction_html;
    }
    return $content;
}
add_filter('the_content', 'bslb_append_reaction');

// 处理 AJAX 点赞请求
function bslb_handle_like() {
    // 安全校验，可加入非空验证或 nonce 检查
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) {
        wp_send_json_error('无效的文章ID');
    }
    // 限制同一IP只能点赞一次
    $ip = $_SERVER['REMOTE_ADDR'];
    $ips = get_post_meta($post_id, 'bear_style_like_ips', true);
    if (!empty($ips) && is_array($ips)) {
        $ips_array = $ips;
    } else {
        $ips_array = array();
    }
    if (in_array($ip, $ips_array)) {
        wp_send_json_error('您已经点赞过了');
    } else {
        $ips_array[] = $ip;
        update_post_meta($post_id, 'bear_style_like_ips', $ips_array);
    }
    $likes = get_post_meta($post_id, 'bear_style_like_count', true);
    $likes = $likes ? intval($likes) : 0;
    // 点赞数加 1
    $likes++;
    update_post_meta($post_id, 'bear_style_like_count', $likes);
    wp_send_json_success(array('likes' => $likes));
}
add_action('wp_ajax_bslb_like', 'bslb_handle_like');
add_action('wp_ajax_nopriv_bslb_like', 'bslb_handle_like');

// 添加后台设置菜单
function bslb_add_admin_menu() {
    add_options_page('Bear Style Like Button 设置', 'Like Button 设置', 'manage_options', 'bslb_settings', 'bslb_options_page');
}
add_action('admin_menu', 'bslb_add_admin_menu');

function bslb_settings_init() {
    register_setting('bslb_settings_group', 'bslb_support_url');

    add_settings_section(
        'bslb_settings_section',
        '插件设置',
        'bslb_settings_section_callback',
        'bslb_settings'
    );

    add_settings_field(
        'bslb_support_url_field',
        '支持链接地址',
        'bslb_support_url_render',
        'bslb_settings',
        'bslb_settings_section'
    );
}
add_action('admin_init', 'bslb_settings_init');

function bslb_support_url_render() {
    $support_url = get_option('bslb_support_url', 'https://example.com/support');
    echo '<input type="text" name="bslb_support_url" value="' . esc_attr($support_url) . '" size="50">';
}

function bslb_settings_section_callback() {
    echo '设置点击「支持」时跳转的页面地址：';
}

function bslb_options_page() {
    ?>
    <div class="wrap">
        <h1>Bear Style Like Button 设置</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('bslb_settings_group');
            do_settings_sections('bslb_settings');
            submit_button();
            ?>
        </form>
        <hr />
        <h2>导出点赞数据</h2>
        <p><a href="<?php echo esc_url(admin_url('options-general.php?page=bslb_settings&bslb_export=1')); ?>" class="button button-secondary">导出点赞数据 (CSV)</a></p>
    </div>
    <?php
}

// 在插件页面添加“设置”链接
function bslb_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=bslb_settings') . '">设置</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'bslb_plugin_action_links');

// 添加导出点赞数据功能
function bslb_export_likes_data() {
    if (! current_user_can('manage_options')) {
        return;
    }
    if (isset($_GET['bslb_export']) && $_GET['bslb_export'] === '1') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=like_counts.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Post ID', 'Post Title', 'Like Count'));
        $posts = get_posts(array(
            'post_type' => 'post',
            'numberposts' => -1,
            'meta_key' => 'bear_style_like_count'
        ));
        foreach ($posts as $post) {
            $like = get_post_meta($post->ID, 'bear_style_like_count', true);
            if (empty($like)) {
                $like = '0';
            }
            fputcsv($output, array($post->ID, $post->post_title, $like));
        }
        fclose($output);
        exit;
    }
}
add_action('admin_init', 'bslb_export_likes_data');

?>
