<?php

/**
 * @package     Micro Warehouse Shipping
 * @author      Eniture-Technology
 */
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists("En_Micro_Warehouse_Product_Detail")) {

    class En_Micro_Warehouse_Product_Detail
    {

        /**
         * Micro Warehouse Shipping Load Multiple Dropships Hooks
         */
        public function __construct()
        {

            add_action('woocommerce_process_product_meta', [$this, 'en_micro_warehouse_freight_product_fields_save']);
            add_action('woocommerce_save_product_variation', [$this, 'en_micro_warehouse_freight_save_variable_fields'], 10, 2);
            /**
             * Dropship Filter
             */
            add_action('woocommerce_product_options_shipping', [$this, 'en_micro_warehouse_freight_dropship']);
            add_action('woocommerce_product_after_variable_attributes', [$this, 'en_micro_warehouse_freight_dropship'], 10, 3);

        }

        /**
         * Drop Ship For Shipping Section In Product Detail Page
         * @param $loop
         * @param $en_variation_data
         * @param $variation
         * @global $wpdb
         */
        function en_micro_warehouse_freight_dropship($loop, $en_variation_data = array(), $variation = array())
        {
            global $wpdb;
            $en_dropship_list = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "warehouse WHERE location = 'dropship'");
            if (!empty($en_dropship_list)) {
                (isset($variation->ID) && $variation->ID > 0) ? $variation_id = $variation->ID : $variation_id = get_the_ID();
                $en_enable_dropship = get_post_meta($variation_id, '_enable_dropship', true);
                woocommerce_wp_checkbox(
                    array(
                        'id' => '_enable_dropship[' . $variation_id . ']',
                        'label' => __('Enable drop ship location', 'woocommerce'),
                        'value' => $en_enable_dropship,
                        'class' => '_enable_dropship checkbox',
                    )
                );
                $en_triggering_class = "block";
                $attributes = array(
                    'id' => '_dropship_location[' . $variation_id . ']',
                    'class' => 'dropdown-check-list',
                );
                $get_loc = maybe_unserialize(get_post_meta($variation_id, '_dropship_location', true));
                $en_values_arr = array();
                foreach ($en_dropship_list as $list) {
                    (isset($list->nickname) && $list->nickname == '') ? $nickname = '' : $nickname = $list->nickname . ' - ';
                    (isset($list->country) && $list->country == '') ? $country = '' : $country = '(' . $list->country . ')';
                    $location = $nickname . $list->zip . ', ' . $list->city . ', ' . $list->state . ' ' . $country;
                    $finalValue['option_id'] = $list->id;
                    $finalValue['option_value'] = $list->id;
                    $finalValue['option_label'] = $location;
                    $en_values_arr[] = $finalValue;
                }
                $en_a_fields[] = array(
                    'attributes' => $attributes,
                    'label' => 'Drop ship location',
                    'value' => $en_values_arr,
                    'name' => '_dropship_location[' . $variation_id . '][]',
                    'type' => 'checkbox',
                    'selected_value' => $get_loc,
                    'variant_id' => $variation_id
                );
                $this->en_micro_warehouse_dropship_filter($en_a_fields, $get_loc, $variation_id, $en_triggering_class);
            }
        }

        /**
         * Drop Ship Filter
         * @param $en_a_fields
         * @param $get_loc
         * @param $variation_id
         */
        function en_micro_warehouse_dropship_filter($en_a_fields, $get_loc, $variation_id, $en_triggering_class)
        {
            $en_fields_html = '';
            foreach ($en_a_fields as $key => $en_s_field) {
                $en_fields_html = $this->en_micro_warehouse_freight_dropship_html($en_s_field, $en_fields_html, $get_loc, $variation_id, $en_triggering_class);
            }
            echo $en_fields_html;
        }

        /**
         * Drop Ship Dropdown Select
         * @param $en_s_field
         * @param $en_fields_html
         * @param $variant_id
         * @return string
         */
        function en_micro_warehouse_freight_dropship_html($en_s_field, $en_fields_html, $get_loc, $variant_id, $en_triggering_class)
        {
            $en_fields_html .= '<div id="en-dropship-list-' . esc_attr($variant_id) . '" class="en-dropship-list dropdown-check-list _dropship_location _dropship_location_' . esc_attr($variant_id) . '" style="display:' . esc_attr($en_triggering_class) . '" tabindex="100">';
            $en_fields_html .= '<label for="_dropship_location">' . esc_attr($en_s_field['label']) . '</label>';
            $en_fields_html .= '  <span class="anchor">' . esc_attr($en_s_field['label']) . '</span>';
            if ($en_s_field['type'] == 'checkbox') {
                $en_fields_html .= '<ul class="items">';
                if ($en_s_field['value']) {
                    foreach ($en_s_field['value'] as $option) {
                        $selected_option = $this->en_micro_warehouse_freight_product_ds_selected_option($en_s_field['selected_value'], $option['option_value']);
                        $en_fields_html .= '<li>';
                        $en_fields_html .= '<input type="checkbox" name="' . $en_s_field['name'] . '"  value="' . esc_attr($option['option_value']) . '" ' . esc_attr($selected_option) . '"/>';
                        $en_fields_html .= '&nbsp' . esc_html($option['option_label']) . '</li>';
                    }
                }

                $en_fields_html .= '</ul>';
            }

            $en_fields_html .= '</div>';
            $en_fields_html .= '
                <script type="text/javascript">
                    jQuery("#en-dropship-list-' . esc_js($variant_id) . ' .anchor").on("click", function() {
                        jQuery("#en-dropship-list-' . esc_js($variant_id) . ' ul.items").toggle();
                    })
                </script>';

            return $en_fields_html;
        }

        /**
         * Save Product Custom Shipping Options
         * @param $post_id
         */
        function en_micro_warehouse_freight_product_fields_save($post_id)
        {
            $woocommerce_checkbox = (isset($_POST['_enable_dropship'][$post_id])) ? sanitize_text_field($_POST['_enable_dropship'][$post_id]) : "";
            $dropship_location = (isset($_POST['_dropship_location'][$post_id]) && is_array($_POST['_dropship_location'][$post_id])) ? array_map('sanitize_text_field', $_POST['_dropship_location'][$post_id]) : [];
            $dropship_location_val = array_map('intval', $dropship_location);
            update_post_meta($post_id, '_enable_dropship', esc_attr($woocommerce_checkbox));
            update_post_meta($post_id, '_dropship_location', maybe_serialize($dropship_location_val));
        }

        /**
         * Micro Warehouse Shipping Show Selected Dropship
         * @param $get_loc
         * @param $option_val
         */
        function en_micro_warehouse_freight_product_ds_selected_option($get_loc, $option_val)
        {
            $selected = '';
            if (is_array($get_loc)) {
                if (in_array($option_val, $get_loc)) {
                    $selected = 'checked="checked"';
                }
            } else {
                $selected = selected($get_loc, $option_val, false);
            }
            return $selected;
        }


        /**
         * Save The Variation For Products
         * @param $post_id
         */
        function en_micro_warehouse_freight_save_variable_fields($post_id)
        {
            if (isset($post_id) && $post_id > 0) :

                $enable_ds = (isset($_POST['_enable_dropship'][$post_id]) ? sanitize_text_field($_POST['_enable_dropship'][$post_id]) : "");
                $ds_locaton = isset($_POST['_dropship_location'][$post_id]) ? array_map('sanitize_text_field', $_POST['_dropship_location'][$post_id]) : [];
                $dropship_location_val = array_map('intval', $ds_locaton);
                update_post_meta($post_id, '_enable_dropship', esc_attr($enable_ds));
                if (isset($ds_locaton)) {
                    update_post_meta($post_id, '_dropship_location', maybe_serialize($dropship_location_val));
                }

            endif;
        }
    }

    new En_Micro_Warehouse_Product_Detail();
}