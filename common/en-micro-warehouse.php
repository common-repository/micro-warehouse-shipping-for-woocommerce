<?php

if (!defined("ABSPATH")) {
    exit();
}

if (!class_exists("En_Micro_Warehouse")) {

    class En_Micro_Warehouse {

        // Micro Warehouse
        public $products = array();
        public $dropship_location_array = array();
        public $en_destination_address;
        public $origin;
        public $warehouse_products;
        public $en_licence_key;
        public $carrie_name;
        public $order;

        /*
         * Load the en_micro_warehouse filter
         */

        public function __construct() {
            add_filter('en_micro_warehouse', [$this, 'en_micro_warehouse'], 10, 11);
        }

        /*
         * Load the micro warehouse informations
         * @en_micro_warehouse
         * @param $en_package,$products,$dropship_location_array etc
         */

        public function en_micro_warehouse($en_package, $products, $dropship_location_array, $en_destination_address, $origin, $small_plugin_exist, $items, $items_shipment, $warehouse_products, $en_licence_key, $carrie_name) {
            $weight_threshold = get_option('en_weight_threshold_lfq');
            $weight_threshold = isset($weight_threshold) && $weight_threshold > 0 ? $weight_threshold : 150;
            $this->products = $products;
            $this->dropship_location_array = $dropship_location_array;
            $this->en_destination_address = $en_destination_address;
            $this->origin = $origin;
            $this->warehouse_products = $warehouse_products;
            $this->en_licence_key = $en_licence_key;
            $this->carrie_name = $carrie_name;
            $filterd_dropship = (!empty($this->dropship_location_array)) ? $this->matching_comb($this->dropship_location_array, $this->products) : array();
            // Eniture debug mood
            do_action("eniture_debug_mood", "My Combinatios ($this->carrie_name)", $filterd_dropship);
            if (is_array($filterd_dropship) && (!empty($filterd_dropship))) {
                $en_min_key = min(array_keys($filterd_dropship));
                $filterd_dropship = (isset($filterd_dropship[$en_min_key])) ? $filterd_dropship[$en_min_key] : array();
            }
            $dist_frm_comb = $this->get_distance_combination($filterd_dropship, $this->origin, $this->en_destination_address);
            // Eniture debug mood
            do_action("eniture_debug_mood", "Get Distance Combinatios response ($this->carrie_name)", $dist_frm_comb);
            if (!(isset($dist_frm_comb['severity']) && $dist_frm_comb['severity'] == "ERROR")) {
                $min_distnace_address = array();
                $combinations_response = array();
                $warehouse_zip = "";
                $min_warehouse_miles = "";
                if (isset($dist_frm_comb['min_distnace_address'], $dist_frm_comb['combinations'])) {
                    $combinations = (isset($dist_frm_comb['combinations'])) ? $dist_frm_comb['combinations'] : array();
                    $min_distnace_response = (isset($dist_frm_comb['min_distnace_address'])) ? $dist_frm_comb['min_distnace_address'] : array();

                    $get_same_comb = array_intersect_key($combinations, $min_distnace_response);
                    $min_distnace_address = (is_array($get_same_comb) && (!empty($get_same_comb))) ? reset($get_same_comb) : array();
                }
                if ($min_warehouse = (isset($dist_frm_comb['min_warehouse']['warehouse'])) ? $dist_frm_comb['min_warehouse']['warehouse'] : array()) {
                    $min_warehouse_miles = (isset($dist_frm_comb['min_warehouse']['min_dist'])) ? $this->ft_to_mi_conversion($dist_frm_comb['min_warehouse']['min_dist']) : "";
                    $min_warehouse = (is_array($min_warehouse) && (!empty($min_warehouse))) ? reset($min_warehouse) : array();
                    $warehouse_zip = (isset($min_warehouse['zip'])) ? $min_warehouse['zip'] : "";
                    (!isset($min_distnace_address[$warehouse_zip])) ? $min_distnace_address[$warehouse_zip] = $this->warehouse_products : $min_distnace_address[$warehouse_zip] = array_merge($min_distnace_address[$warehouse_zip], $this->warehouse_products);
                    $dropship_data = $this->address_array(json_decode(json_encode($min_warehouse)));
                    $this->origin[$warehouse_zip] = $dropship_data;
                }
                $en_package = array();
                $exceedWeight = get_option('en_plugins_return_LTL_quotes');
                foreach ($min_distnace_address as $selected_zip => $products) {
                    if ($miles = (isset($products['distance']['miles'])) ? $this->ft_to_mi_conversion($products['distance']['miles']) : "") {
                        unset($products['distance']);
                    }
                    if (is_array($products) && !empty($products)) {
                        $search_origin = (isset($this->origin[$selected_zip])) ? $this->origin[$selected_zip] : array();
                        $en_package[$selected_zip]['origin'] = $search_origin;
                        (!isset($en_package[$selected_zip]['items'])) ? $en_package[$selected_zip]['items'] = array() : "";
                        $shipment_weight = 0;
                        $insurance = $hazmat = false;
                        foreach ($products as $key => $product) {
                            (isset($items_shipment[$product]) && ($items_shipment[$product] == true)) ? $en_package[$selected_zip]['item_ship_ltl_enable'] = 1 : "";
                            (isset($items[$product])) ? $en_package[$selected_zip]['items'][] = $items[$product] : "";
                            $product_quantity = (isset($items[$product]['productQty'])) ? $items[$product]['productQty'] : 1;
                            $product_weight = (isset($items[$product]['productWeight'])) ? $items[$product]['productWeight'] : 1;
                            (isset($items[$product]['hazmat']) && $items[$product]['hazmat'] == 'yes') ? $hazmat = true : '';
                            (isset($items[$product]['insurance']) && $items[$product]['insurance'] == 'yes') ? $insurance = true : '';
                            $shipment_weight += $product_weight * $product_quantity;
                        }
                    }
                    if (empty($en_package[$selected_zip]['items'])) {
                        unset($en_package[$selected_zip]);
                    } elseif (isset($en_package[$selected_zip]['item_ship_ltl_enable']) ||
                            ($shipment_weight > $weight_threshold && $exceedWeight == 'yes')) {
                        $en_package[$selected_zip][$carrie_name] = 1;
                    } elseif ($small_plugin_exist == 1) {
                        $en_package[$selected_zip]['small'] = 1;
                    }
                    if (isset($en_package[$selected_zip]) && !empty($en_package[$selected_zip])) {
                        $en_package[$selected_zip]['shipment_weight'] = $shipment_weight;
                        $hazmat == 'yes' ? $en_package[$selected_zip]['hazardous_material'] = $en_package[$selected_zip]['hazardousMaterial'] = $hazmat : '';
                        $insurance == 'yes' ? $en_package[$selected_zip]['insurance'] = $insurance : '';
                    }
                }
            }

            return $en_package;
        }

        /*
         * Used address_array
         * @param $value
         * return $dropship_data
         */

        public function address_array($value) {
            $dropship_data = array();
            $dropship_data['locationId'] = (isset($value->id)) ? $value->id : "";
            $dropship_data['zip'] = (isset($value->zip)) ? $value->zip : "";
            $dropship_data['city'] = (isset($value->city)) ? $value->city : "";
            $dropship_data['state'] = (isset($value->state)) ? $value->state : "";
            $dropship_data['address'] = (isset($value->address)) ? $value->address : "";
            $dropship_data['location'] = (isset($value->location)) ? $value->location : "";
            $dropship_data['country'] = (isset($value->country)) ? $value->country : "";
            $dropship_data['enable_store_pickup'] = (isset($value->enable_store_pickup)) ? $value->enable_store_pickup : "";
            $dropship_data['fee_local_delivery'] = (isset($value->fee_local_delivery)) ? $value->fee_local_delivery : "";
            $dropship_data['suppress_local_delivery'] = (isset($value->suppress_local_delivery)) ? $value->suppress_local_delivery : "";
            $dropship_data['miles_store_pickup'] = (isset($value->miles_store_pickup)) ? $value->miles_store_pickup : "";
            $dropship_data['match_postal_store_pickup'] = (isset($value->match_postal_store_pickup)) ? $value->match_postal_store_pickup : "";
            $dropship_data['checkout_desc_store_pickup'] = (isset($value->checkout_desc_store_pickup)) ? $value->checkout_desc_store_pickup : "";
            $dropship_data['enable_local_delivery'] = (isset($value->enable_local_delivery)) ? $value->enable_local_delivery : "";
            $dropship_data['miles_local_delivery'] = (isset($value->miles_local_delivery)) ? $value->miles_local_delivery : "";
            $dropship_data['match_postal_local_delivery'] = (isset($value->match_postal_local_delivery)) ? $value->match_postal_local_delivery : "";
            $dropship_data['checkout_desc_local_delivery'] = (isset($value->checkout_desc_local_delivery)) ? $value->checkout_desc_local_delivery : "";
            $dropship_data['odfl_account'] = (isset($value->odfl_account)) ? $value->odfl_account : "";
            $dropship_data['saia_account'] = (isset($value->saia_account)) ? $value->saia_account : "";
            $dropship_data['sender_origin'] = $dropship_data['location'] . ": " . $dropship_data['city'] . ", " . $dropship_data['state'] . " " . $dropship_data['zip'];
            return $dropship_data;
        }

        /*
         * Unit conversion
         * @ ft_to_mi_conversion
         * @param $distance_mi
         */

        function ft_to_mi_conversion($distance_mi) {
            if (is_string($distance_mi) && strpos($distance_mi, 'ft') !== false) {
                $mi = (int) $distance_mi;
                $mi = $mi * 0.00018939;
                return $mi;
            } else {
                return $distance_mi;
            }
        }

        /*
         * Get the warehouse
         * @ get_warehouse
         * @param $wpdb
         */

        public function get_warehouse() {
            global $wpdb;
            $locations_list = $wpdb->get_results(
                    "SELECT * FROM " . $wpdb->prefix . "warehouse WHERE location = 'warehouse'"
            );
            return $locations_list;
        }

        /*
         * Get Distance Combination
         * @ get_distance_combination
         * @param $combinations,$sender_details,$receiver_details
         */

        public function get_distance_combination($combinations, $sender_details, $receiver_details) {
            $url = esc_url("https://ws001.eniture.com/addon/google-location.php");
            $warehouse = $this->get_warehouse();
            $request = array(
                'eniureLicenceKey' => $this->en_licence_key,
                'ServerName' => $_SERVER['SERVER_NAME'],
                'acessLevel' => 'multiDistanceByCombination',
                'sender_details' => $sender_details,
                'receiver_details' => $receiver_details,
                'combinations' => $combinations,
                'warehouse' => $warehouse,
            );
            //  Eniture debug mood
            do_action("eniture_debug_mood", "Get Distance Combinatios request ($this->carrie_name)", $request);

            //  Get response from session
            $currentData = md5(json_encode($request));
            $requestFromSession = WC()->session->get('previousRequestData');
            if (isset($requestFromSession[$currentData]) && (!empty($requestFromSession[$currentData]))) {
                return json_decode($requestFromSession[$currentData], TRUE);
            }
            $response = $this->en_get_curl_response($url, $request);
            $response = json_decode($response, TRUE);

            if (!(isset($response['severity']) && $response['severity'] == "ERROR") && !isset($response['error'])) {
                $requestFromSession[$currentData] = json_encode($response);
                WC()->session->set('previousRequestData', $requestFromSession);
            }

            return $response;
        }

        /**
         * Get Curl Response
         * @param $url
         * @param $en_post_data
         * @return json/array
         */
        function en_get_curl_response($url, $en_post_data) {
            if (!empty($url) && !empty($en_post_data)) {
                $field_string = http_build_query($en_post_data);
                $response = wp_remote_post($url, array(
                    'method' => 'POST',
                    'timeout' => 60,
                    'redirection' => 5,
                    'blocking' => true,
                    'body' => $field_string,
                        )
                );
                $response = wp_remote_retrieve_body($response);
                $output_decoded = json_decode($response);
                if (empty($output_decoded)) {
                    return $response = json_encode(array('error' => 'Unable to get response from API'));
                }
                return $response;
            }
        }

        /*
         * @ add_more
         * @param $searched,$multiple
         */

        public function add_more($searched, $multiple) {
            foreach ($multiple as $multiple_zip => $multiple_value) {
                $re_arrange = array();
                foreach ($searched as $searched_zip => $searched_value) {
                    $difference = array_diff($searched_value, $multiple_value);
                    if (!empty($difference)) {
                        $re_arrange[$searched_zip] = $difference;
                    }
                }
                (!isset($re_arrange[$multiple_zip])) ? $re_arrange[$multiple_zip] = $multiple_value : $re_arrange[$multiple_zip] = array_merge($re_arrange[$multiple_zip], $multiple_value);
                $this->order[count($re_arrange)][] = $re_arrange;
            }
        }

        /*
         * @ matching_comb
         * @param $en_dropship_list,$products_arr
         */

        public function matching_comb($en_dropship_list, $products_arr) {
            foreach ($en_dropship_list as $zip => $products) {
                $pending = array_diff($products_arr, $products);
                $searched = array($zip => $products);
                $dropship_copy = $en_dropship_list;
                unset($dropship_copy[$zip]);
                $recently_added = array();
                $multiple = array();
                $recently_added = array_merge($recently_added, reset($searched));
                foreach ($dropship_copy as $zip_copy => $value) {
                    $matched = array_intersect($value, $pending);
                    $matched_frm_searched = array_intersect($matched, $recently_added);
                    $difference = array_diff($matched, $recently_added);
                    if (!empty($difference)) {
                        $searched[$zip_copy] = $difference;
                        $recently_added = array_merge($recently_added, $difference);
                    }
                    if (!empty($matched_frm_searched)) {
                        $multiple[$zip_copy] = $matched_frm_searched;
                    }
                }
                $this->add_more($searched, $multiple);
                $this->order[count($searched)][] = $searched;
            }
            return $this->order;
        }

    }

    new En_Micro_Warehouse();
}
