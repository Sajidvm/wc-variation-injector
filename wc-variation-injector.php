<?php
/**
 * Plugin Name: WC Multi-Fabric Master Tool
 * Description: Safely adds/removes fabric variations, force-cleans attribute values, and tracks totals.
 * Author: Muhammed Vellato
 * Version: 7.0
 */

if (!defined('ABSPATH'))
    exit;

// 1. Admin Menu Setup
add_action('admin_menu', function () {
    add_management_page('Variation Injector', 'Variation Injector', 'manage_options', 'vc-injector', 'vc_injector_page');
});

function vc_injector_page()
{
    $total_variations = wp_count_posts('product_variation')->publish;
    ?>
    <div class="wrap">
        <h1>Variation Injector & Deep Cleanup</h1>

        <div
            style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; margin-bottom: 20px; border-left: 4px solid #0073aa;">
            <h2 style="margin-top:0;">Store Statistics</h2>
            <p style="font-size: 1.2em;">Total Variations in Database:
                <strong><?php echo number_format($total_variations); ?></strong>
            </p>
        </div>

        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
            <h2>Add New Fabric Variations</h2>
            <p>Generates 8 new variations for every product using this slug.</p>
            <form method="post">
                <input type="text" name="new_fabric_slug" placeholder="e.g., silk-satin" class="regular-text" required>
                <input type="submit" name="start_injection" class="button button-primary" value="Generate 8 New Variations">
            </form>
        </div>

        <br />

        <div style="background: #fff; padding: 20px; border: 1px solid #d63638; border-left: 4px solid #d63638;">
            <h2 style="color:#d63638;">Deep Cleanup Zone</h2>
            <p>Deletes variations AND force-removes the fabric from the product "Value(s)" list.</p>
            <form method="post"
                onsubmit="return confirm('Deep Clean: Are you sure you want to remove this fabric and all its variations?');">
                <input type="text" name="delete_fabric_slug" placeholder="e.g., linen" class="regular-text" required>
                <input type="submit" name="start_cleanup" class="button button-link-delete" style="color:#d63638;"
                    value="Run Deep Cleanup">
            </form>
        </div>
    </div>
    <?php
    if (isset($_POST['start_injection'])) {
        $fabric = sanitize_text_field($_POST['new_fabric_slug']);
        vc_queue_multi_fabric($fabric);
        echo "<div class='updated'><p>Batch started for <b>$fabric</b>.</p></div>";
    }

    if (isset($_POST['start_cleanup'])) {
        $fabric = sanitize_text_field($_POST['delete_fabric_slug']);
        vc_queue_fabric_cleanup($fabric);
        echo "<div class='error'><p>Deep Cleanup started for <b>$fabric</b>. Watch <b>Tools > Scheduled Actions</b>.</p></div>";
    }
}

// 2. Queueing Logic (Action Scheduler)
function vc_queue_multi_fabric($fabric_slug)
{
    $ids = get_posts(array('post_type' => 'product', 'numberposts' => -1, 'post_status' => 'publish', 'fields' => 'ids', 'tax_query' => array(array('taxonomy' => 'product_type', 'field' => 'slug', 'terms' => 'variable'))));
    foreach ($ids as $id) {
        as_enqueue_async_action('vc_process_add', array('product_id' => $id, 'fabric_slug' => $fabric_slug));
    }
}

function vc_queue_fabric_cleanup($fabric_slug)
{
    $ids = get_posts(array('post_type' => 'product', 'numberposts' => -1, 'post_status' => 'publish', 'fields' => 'ids', 'tax_query' => array(array('taxonomy' => 'product_type', 'field' => 'slug', 'terms' => 'variable'))));
    foreach ($ids as $id) {
        as_enqueue_async_action('vc_process_cleanup', array('product_id' => $id, 'fabric_slug' => $fabric_slug));
    }
}

// 3. The Injection Logic
add_action('vc_process_add', 'vc_run_add_logic', 10, 2);
function vc_run_add_logic($product_id, $fabric_slug)
{
    $product = wc_get_product($product_id);
    if (!$product)
        return;

    // A. Inject Fabric Term to Parent
    $term = get_term_by('slug', $fabric_slug, 'pa_fabric');
    if ($term) {
        wp_set_object_terms($product_id, $term->term_id, 'pa_fabric', true);
        $attributes = $product->get_attributes();
        if (isset($attributes['pa_fabric'])) {
            $fabric_attr = $attributes['pa_fabric'];
            $options = $fabric_attr->get_options();
            if (!in_array($term->term_id, $options)) {
                $options[] = $term->term_id;
                $fabric_attr->set_options($options);
                $attributes['pa_fabric'] = $fabric_attr;
                $product->set_attributes($attributes);
                $product->save();
            }
        }
    }

    // B. Create Variations
    $lengths = array('1-yard', '2-yards', '3-5-yards', '4-yards', '5-yards', '10-yards', '16-yards', '25-yards');
    foreach ($lengths as $length_slug) {
        $data_store = WC_Data_Store::load('product');
        $exists = $data_store->find_matching_product_variation($product, array('attribute_pa_fabric' => $fabric_slug, 'attribute_pa_length' => $length_slug));
        if (!$exists) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            $variation->set_attributes(array('pa_fabric' => $fabric_slug, 'pa_length' => $length_slug));
            $variation->set_status('publish');
            $variation->set_regular_price('0');
            $variation->save();
        }
    }
    wc_delete_product_transients($product_id);
}

// 4. The Deep Cleanup Logic (Fixes the "Value(s)" issue)
add_action('vc_process_cleanup', 'vc_run_deep_cleanup', 10, 2);
function vc_run_deep_cleanup($product_id, $fabric_slug)
{
    $product = wc_get_product($product_id);
    if (!$product)
        return;

    // STEP A: Delete the variations
    $variations = $product->get_children();
    foreach ($variations as $v_id) {
        if (get_post_meta($v_id, 'attribute_pa_fabric', true) === $fabric_slug) {
            wp_delete_post($v_id, true);
        }
    }

    // STEP B: Unlink the fabric from the Parent Attribute "Value(s)" box
    $attributes = $product->get_attributes();
    if (isset($attributes['pa_fabric'])) {
        $fabric_attr = $attributes['pa_fabric'];
        $options = $fabric_attr->get_options();
        $term = get_term_by('slug', $fabric_slug, 'pa_fabric');

        if ($term && in_array($term->term_id, $options)) {
            $new_options = array_diff($options, array($term->term_id));
            $fabric_attr->set_options($new_options);
            $attributes['pa_fabric'] = $fabric_attr;
            $product->set_attributes($attributes);
            $product->save(); // Force refresh of the attribute list
        }
    }

    // STEP C: Clear Taxonomy and UI caches
    wp_remove_object_terms($product_id, $fabric_slug, 'pa_fabric');
    wc_delete_product_transients($product_id);
}

// 5. Performance Threshold
add_filter('woocommerce_ajax_variation_threshold', function () {
    return 1000;
}, 10);