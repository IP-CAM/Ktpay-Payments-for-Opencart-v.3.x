<?php

class ModelExtensionPaymentKTPayment extends Model{
    public function getMethod($address) {
        $this->load->language('extension/payment/ktpayment');

        // $country = (array) $this->db->query("SELECT * FROM ". DB_PREFIX ."country WHERE iso_code_3='TUR'");

        // $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE country_id = '" . (int) $address['country_id'] . "' AND (zone_id = '" . (int) $address['zone_id'] . "' OR zone_id = '0')");

        // if ($country['row']['country_id'] == $address['country_id'] && $query->num_rows) {
        //     $status = true;
        // } else {
        //     $status = false;
        // }

        // $method_data = array();

        // if ($status) {
        //     $method_data = array(
        //         'code' => 'ktpayment',
        //         'title' => $this->language->get('text_title'),
        //         'terms' => '',
        //         'sort_order' => ''
        //     );
        // }

        return array(
            'code' => 'ktpayment',
            'title' => $this->language->get('text_title'),
            'terms' => '',
            'sort_order' => ''
        );;
    }
}