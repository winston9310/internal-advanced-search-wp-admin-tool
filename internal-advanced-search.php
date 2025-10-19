<?php
/**
 * Plugin Name: Internal Advanced Search (Admin Tool)
 * Description: Internal search tool for editors/SEO in wp-admin with keyword→tag/category/post rules and sort by relevance.
 * Version: 1.0.1
 * Author: Winston
 * Author URI: https://winstondev.site/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

/**
 * ===== BASIC CONFIG / FALLBACK RULES =====
 * If you use ACF Pro (recommended), create an Options Page with a repeater "rules".
 * If ACF is not active, fallback rules will be used.
 */
function dis_get_editor_rules() {
    if (function_exists('get_field')) {
        $rules = get_field('rules', 'option');
        if (is_array($rules)) return $rules;
    }
    // Fallback rules without ACF
    return [
        [
            'trigger_keywords'   => 'saas, b2b, b2c',
            'mapped_tags'        => [], // Term IDs (post_tag)
            'mapped_categories'  => [], // Term IDs (category)
            'mapped_posts'       => [], // Post IDs
            'boost'              => 100,
            'mode'               => 'boost', // or 'pin_first'
        ],
    ];
}

/**
 * Normalize query string and tokenize
 */
function dis_normalize_query($q) {
    $q = mb_strtolower(trim((string)$q), 'UTF-8');
    $stop = ['the','a','an','and','or','de','la','lo','las','los','un','una','y','o','para','por','con','en'];
    $parts = preg_split('/[\s,;+]+/u', $q);
    $parts = array_filter($parts, function($w) use ($stop){ return $w !== '' && !in_array($w, $stop, true); });
    return array_values($parts);
}

/**
 * Match rules against query string
 */
function dis_match_rules_for_query($s) {
    $tokens = dis_normalize_query($s);
    if (empty($tokens)) return [];

    $matched = [];
    foreach (dis_get_editor_rules() as $rule) {
        $triggers_raw = isset($rule['trigger_keywords']) ? $rule['trigger_keywords'] : '';
        $triggers = array_filter(array_map('trim', explode(',', mb_strtolower($triggers_raw, 'UTF-8'))));
        if (empty($triggers)) continue;

        $hit = false;
        foreach ($triggers as $tk) {
            if ($tk === '') continue;
            foreach ($tokens as $qtok) {
                if ($qtok === $tk) { $hit = true; break 2; }
                if (mb_strpos($qtok, $tk) !== false || mb_strpos($tk, $qtok) !== false) { $hit = true; break 2; }
            }
            if (!$hit && mb_stripos($s, $tk) !== false) { $hit = true; break; }
        }
        if ($hit) $matched[] = $rule;
    }

    usort($matched, function($a,$b){ return intval($b['boost']) <=> intval($a['boost']); });
    return $matched;
}

/**
 * ===== ADMIN UI =====
 */
add_action('admin_menu', function(){
    add_menu_page(
        'Internal Search',                 // Page title
        'Internal Search',                 // Menu title
        'edit_posts',                      // Capability
        'digest-internal-search',          // Menu slug
        'dis_render_admin_page',           // Callback
        'dashicons-search',                // Icon
        58                                 // Position
    );
});

/**
 * Admin page renderer
 */
function dis_render_admin_page() {
    if (!current_user_can('edit_posts')) wp_die('No access');

    // === Params ===
    $q         = isset($_GET['q'])         ? sanitize_text_field($_GET['q']) : '';
    $ptype     = isset($_GET['ptype'])     ? sanitize_text_field($_GET['ptype']) : 'any';
    $status    = isset($_GET['status'])    ? sanitize_text_field($_GET['status']) : 'publish';
    $cat       = isset($_GET['cat'])       ? intval($_GET['cat']) : 0;
    $tag       = isset($_GET['tag'])       ? intval($_GET['tag']) : 0;
    $author    = isset($_GET['author'])    ? intval($_GET['author']) : 0;
    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
    $date_to   = isset($_GET['date_to'])   ? sanitize_text_field($_GET['date_to']) : '';
    $per_page  = isset($_GET['pp'])        ? max(1, intval($_GET['pp'])) : 20;
    $paged     = isset($_GET['paged'])     ? max(1, intval($_GET['paged'])) : 1;

    // Check if any filter is active
    $has_filters = (
        $q !== '' ||
        $ptype !== 'any' ||
        $status !== 'publish' ||
        $cat > 0 ||
        $tag > 0 ||
        $author > 0 ||
        $date_from !== '' ||
        $date_to !== ''
    );

    $results = [];
    $found   = 0;
    $mode    = 'boost';  

    // === Form ===
    ?>
    <div class="wrap">
      <h1>Internal Search (Editors & SEO)</h1>
      <form method="get" style="margin:1rem 0">
        <input type="hidden" name="page" value="digest-internal-search"/>
        <p>
          <input type="text" name="q" value="<?php echo esc_attr($q); ?>" placeholder="Search keywords (optional)..." style="min-width:360px"/>
          <select name="ptype">
            <option value="any" <?php selected($ptype,'any'); ?>>Any Type</option>
            <option value="post" <?php selected($ptype,'post'); ?>>Posts</option>
            <option value="page" <?php selected($ptype,'page'); ?>>Pages</option>
          </select>
          <select name="status">
            <option value="publish" <?php selected($status,'publish'); ?>>Published</option>
            <option value="draft" <?php selected($status,'draft'); ?>>Draft</option>
            <option value="any" <?php selected($status,'any'); ?>>Any Status</option>
          </select>
          <?php
          wp_dropdown_categories([
              'show_option_all' => 'Any Category',
              'taxonomy'        => 'category',
              'name'            => 'cat',
              'orderby'         => 'name',
              'selected'        => $cat,
              'hide_empty'      => false
          ]);
          wp_dropdown_categories([
              'show_option_all' => 'Any Tag',
              'taxonomy'        => 'post_tag',
              'name'            => 'tag',
              'orderby'         => 'name',
              'selected'        => $tag,
              'hide_empty'      => false
          ]);
          wp_dropdown_users([
              'who'      => 'authors',
              'name'     => 'author',
              'show'     => 'display_name',
              'selected' => $author,
              'show_option_all' => 'Any Author'
          ]);
          ?>
        </p>
        <p>
          Date from: <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>"/>
          Date to: <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>"/>
          Per page: <input type="number" name="pp" min="1" value="<?php echo esc_attr($per_page); ?>" style="width:80px"/>
          <button class="button button-primary">Search</button>
          <?php if ($q !== ''): ?>
            <a class="button" href="<?php echo esc_url( home_url('/?s=' . urlencode($q)) ); ?>" target="_blank">Open query on site</a>
          <?php endif; ?>
        </p>
      </form>
    <?php

    // No filters = no query (avoid dumping all posts)
    if (!$has_filters) {
        echo '<p>Tip: You can search by category, tag, author or date even without typing keywords.</p></div>';
        return;
    }

    // === Build query args (omit 's' if no keywords) ===
    $args = [
        'post_type'        => ($ptype === 'any' ? ['post','page'] : $ptype),
        'posts_per_page'   => $per_page,
        'paged'            => $paged,
        'post_status'      => $status === 'any' ? ['publish','draft','pending','future','private'] : $status,
        'suppress_filters' => true,
        'fields'           => 'all',
    ];
    if ($q !== '') {
        $args['s'] = $q;
    }

    $tax_query = ['relation' => 'AND'];
    if ($cat) $tax_query[] = ['taxonomy'=>'category','field'=>'term_id','terms'=>[$cat],'operator'=>'IN'];
    if ($tag) $tax_query[] = ['taxonomy'=>'post_tag','field'=>'term_id','terms'=>[$tag],'operator'=>'IN'];
    if (count($tax_query) > 1) $args['tax_query'] = $tax_query;

    if ($author) $args['author'] = $author;

    if ($date_from || $date_to) {
        $date_query = ['inclusive' => true];
        if ($date_from) $date_query['after']  = $date_from;
        if ($date_to)   $date_query['before'] = $date_to;
        $args['date_query'] = [$date_query];
    }

    // Apply rules (only if keywords present)
    $pin_ids = [];
    if ($q !== '') {
        $rules = dis_match_rules_for_query($q);
        if (!empty($rules)) {
            $tax_rule = ['relation' => 'OR'];
            foreach ($rules as $r) {
                $mode = isset($r['mode']) ? $r['mode'] : 'boost';

                $tags   = isset($r['mapped_tags']) ? (array)$r['mapped_tags'] : [];
                $cats   = isset($r['mapped_categories']) ? (array)$r['mapped_categories'] : [];
                $posts  = isset($r['mapped_posts']) ? (array)$r['mapped_posts'] : [];

                $tag_ids  = array_map(function($t){ return is_object($t) ? $t->term_id : (int)$t; }, $tags);
                $cat_ids  = array_map(function($t){ return is_object($t) ? $t->term_id : (int)$t; }, $cats);
                $post_ids = array_map('intval', $posts);

                if (!empty($tag_ids))  $tax_rule[] = ['taxonomy'=>'post_tag','field'=>'term_id','terms'=>$tag_ids,'operator'=>'IN'];
                if (!empty($cat_ids))  $tax_rule[] = ['taxonomy'=>'category','field'=>'term_id','terms'=>$cat_ids,'operator'=>'IN'];
                if (!empty($post_ids)) $pin_ids = array_merge($pin_ids, $post_ids);
            }

            if (count($tax_rule) > 1) {
                $args['tax_query'] = isset($args['tax_query'])
                    ? ['relation'=>'AND', $args['tax_query'], $tax_rule]
                    : $tax_rule;
            }
        }
    }

    // === Run query ===
    $qobj    = new WP_Query($args);
    $results = $qobj->posts;
    $found   = intval($qobj->found_posts);

    // === Sort results ===
    if (!empty($results)) {
        if ($q !== '') {
            // With keywords: score (title=3, content=1) + pin_ids + date
            $safe_q  = mb_strtolower($q, 'UTF-8');
            $pin_ids = array_values(array_unique($pin_ids));
            usort($results, function($a,$b) use ($safe_q,$pin_ids){
                $score = function($p) use ($safe_q){
                    $t = mb_strtolower($p->post_title, 'UTF-8');
                    $c = mb_strtolower(wp_strip_all_tags($p->post_content), 'UTF-8');
                    $s = 0;
                    if (mb_strpos($t, $safe_q) !== false) $s += 3;
                    if (mb_strpos($c, $safe_q) !== false) $s += 1;
                    return $s;
                };
                $a_pin = in_array($a->ID, $pin_ids, true) ? 1 : 0;
                $b_pin = in_array($b->ID, $pin_ids, true) ? 1 : 0;
                if ($a_pin !== $b_pin) return $b_pin - $a_pin;
                $as = $score($a); $bs = $score($b);
                if ($as !== $bs) return $bs - $as;
                return strcmp(get_post_field('post_date', $b->ID), get_post_field('post_date', $a->ID));
            });
        } else {
            // Without keywords: sort by date DESC
            usort($results, function($a,$b){
                return strcmp(get_post_field('post_date', $b->ID), get_post_field('post_date', $a->ID));
            });
        }
    }

    // === Render table ===
    ?>
      <h2 style="margin-top:1.5rem">Results (<?php echo intval($found); ?> found)</h2>
      <table class="widefat striped">
        <thead>
          <tr>
            <th>Title</th>
            <th>Type</th>
            <th>Status</th>
            <th>Author</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($results)): ?>
          <tr><td colspan="6">No results</td></tr>
        <?php else:
            foreach ($results as $p):
                $edit = get_edit_post_link($p->ID, '');
                $view = get_permalink($p->ID);
        ?>
          <tr>
            <td><strong><?php echo esc_html(get_the_title($p)); ?></strong></td>
            <td><?php echo esc_html(get_post_type($p)); ?></td>
            <td><?php echo esc_html(get_post_status($p)); ?></td>
            <td><?php echo esc_html(get_the_author_meta('display_name', $p->post_author)); ?></td>
            <td><?php echo esc_html(get_post_field('post_date', $p->ID)); ?></td>
            <td>
              <?php if ($edit): ?><a class="button" href="<?php echo esc_url($edit); ?>">Edit</a><?php endif; ?>
              <?php if ($view): ?><a class="button" target="_blank" href="<?php echo esc_url($view); ?>">View</a><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    <?php

    // Pagination
    if ($found > $per_page) {
        $total_pages = ceil($found / $per_page);
        echo '<p style="margin-top:10px;">Page: ';
        for ($i=1; $i <= $total_pages; $i++) {
            $url = add_query_arg(array_merge($_GET, ['paged'=>$i]));
            if ($i === $paged) {
                echo '<strong>'.$i.'</strong> ';
            } else {
                echo '<a href="'.esc_url($url).'">'.$i.'</a> ';
            }
        }
        echo '</p>';
    }

    echo '</div>';
}

/**
 * ===== (Optional) Register the ACF Options Page by code =====
 * Uncomment if you want a "Search Rules" menu (requires ACF Pro).
 */
// add_action('acf/init', function() {
//     if (function_exists('acf_add_options_page')) {
//         acf_add_options_page([
//             'page_title' => 'Search Rules',
//             'menu_title' => 'Search Rules',
//             'menu_slug'  => 'dis-search-rules',
//             'capability' => 'manage_options',
//             'redirect'   => false,
//             'position'   => 59,
//             'icon_url'   => 'dashicons-filter',
//         ]);
//     }
// });
