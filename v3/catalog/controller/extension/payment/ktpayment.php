<?php
error_reporting(0);

class ControllerExtensionPaymentKTPayment extends Controller{

    public function index()
    {        
        $this->language->load('extension/payment/ktpayment');
        $this->load->model('extension/payment/ktpayment');
        $this->load->model('checkout/order');      

        $order_id = $this->session->data['order_id'];
        if (!isset($order_id) or !$order_id) {
            die('Sipariş ID bulunamadı');
        }
        
        $data['order_id'] = $order_id;
        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (isset($this->request->post['order_id']) && $this->request->post['order_id'] == $order_id) {          
            $this->pay($order_id);
        }

        $rates = $this->config->get('payment_ktpayment_rates');
        $installment_count_array = (array) $this->config->get('payment_ktpayment_installments');
        $has_installment =  'off';
        $installment_count = 1;

        require_once DIR_SYSTEM . 'library/ktpay/ktconfig.php';
        require_once DIR_SYSTEM . 'library/ktpay/KTPay.php';
        if($rates!=null && $installment_count_array!=null && count($installment_count_array)>1)
        {
            $data['rates'] = KTPayConfig::calculate_price_with_installments($order_info['total'], $rates);
            $has_installment =  'on';
            $installment_count = count($installment_count_array);
        }

        $ktpay=new KTPay();
        $check_onus_card_url = $ktpay->check_onus_card_test_url;
        if($this->config->get('environment') == 'PROD') {
            $check_onus_card_url = $ktpay->check_onus_card_prod_url;
        }

        $data['installment_mode'] = $this->config->get('payment_ktpayment_installmentoptions');
        $data['has_installment'] = $has_installment;
        $data['check_onus_card_url'] = $check_onus_card_url;
        $data['installment_count'] = $installment_count;
        $data['installment_count_text'] = $this->language->get('installment_count_text');
        $data['payment_page'] = $this->language->get('payment_page');
        $data['card_holder_name_surname'] = $this->language->get('card_holder_name_surname');
        $data['card_holder_name_placeholder'] = $this->language->get('card_holder_name_placeholder');
        $data['card_number'] = $this->language->get('card_number');
        $data['card_expire_date'] = $this->language->get('card_expire_date');
        $data['card_expire_date_placeholder'] = $this->language->get('card_expire_date_placeholder');
        $data['pay'] = $this->language->get('pay');
        $data['installment'] = $this->language->get('installment');

        $data['action'] = $this->url->link('extension/payment/ktpayment', '', 'SSL');
        if (VERSION >= '2.2.0.0') {
            $template_url = 'extension/payment/ktpayment';
        } else {
            $template_url = 'default/template/extension/payment/ktpayment';
        }
        return $this->load->view($template_url, $data);
    }

    function pay($order_id)
    {       
        $this->load->model('checkout/order');
        include DIR_SYSTEM . 'library/ktpay/ktconfig.php';
        include DIR_SYSTEM . 'library/ktpay/KTPay.php';
        $server_conn_slug = $this->getServerConnectionSlug();
        $order_info = $this->model_checkout_order->getOrder($order_id);

        $card_holder_name = $_POST['card-holder'];
        $card_number = $this->replaceSpace($_POST['card-number']);

        $card_expire_date=explode("/",$_POST['card-expire-date']);
        $card_expire_month = $card_expire_date[0];
        $card_expire_year = $card_expire_date[1];
        $card_cvv =$_POST['card-cvv'];
        $installment = isset($_POST['installment']) ? $_POST['installment'] : 1;
        $orderId = 'OC-'.$order_id;

        $environment=$this->config->get('payment_ktpayment_environment');
        $merchant_id=$this->config->get('payment_ktpayment_merchantid');
        $customer_id=$this->config->get('payment_ktpayment_customerid');
        $api_username=$this->config->get('payment_ktpayment_username');
        $api_password=$this->config->get('payment_ktpayment_password');
        $td_mode=$this->config->get('payment_ktpayment_has3dsecureapprove');
        $td_overamount=$this->config->get('payment_ktpayment_over3dsecureamount');
        $rates=$this->config->get('payment_ktpayment_rates');

        $total = KTPayConfig::calculate_total_price($order_info['total'], $rates,  $installment);
        $currency = KTPayConfig::get_currency_code($order_info['currency_code']);

        $is3dTransaction=($td_mode == 'on') || ($td_mode =='off' && $td_overamount!=null && $this->replaceSpace($td_overamount)!='' && $total > (float) $td_overamount);
        $route_url = 'extension/payment/ktpayment/callback';
        $success_url = $this->getSiteUrl() . 'index.php?action=success&route=' . $route_url;
        $fail_url = $this->getSiteUrl() . 'index.php?action=error&route=' . $route_url;
        $email = !empty($order_info['email']) ? $order_info['email'] : "NOT PROVIDED";

        $ktpay = new KTPay();
        $params = array(
            'environment'=> $environment,
            'merchant_id' => $merchant_id,
            'customer_id' => $customer_id,
            'api_user_name' => $api_username,
            'api_user_password' => $api_password,
            'success_url' => $is3dTransaction ? $success_url : '',
            'fail_url' => $is3dTransaction ? $fail_url : '',
            'merchant_order_id' =>$orderId,
            'amount' => $total,
            'installment_count' => $installment,
            'currency_code' => $currency,
            'customer_ip' => $_SERVER['REMOTE_ADDR'],
            'customer_mail' => $email,
            //'phone_number' => $order->get_billing_phone(),
            'card_holder_name' => $card_holder_name,
            'card_number' => $card_number,
            'card_expire_month' => $card_expire_month,
            'card_expire_year' => $card_expire_year,
            'card_cvv' => $card_cvv
        );
        $ktpay->set_payment_params($params);
        
        if($is3dTransaction){
            //3d akışı
            $parameters = array(
                'data' => $ktpay->init_3d_request_body(),
                'url' => $environment == 'TEST' ? $ktpay->td_payment_test_url : $ktpay->td_payment_prod_url,
                'isJson' => $is3dTransaction
            );
            $response=$ktpay->send_request($parameters);

            try {
                if($response['success'])
                {
                    $kt_error_response='<form name="responseForm"';
                    if(substr($response['data'],0,1)=='{')
                    {                           
                        $jsonResponse=json_decode($response['data']);
                        if(isset($jsonResponse->ResponseCode) && $jsonResponse->Success==false)
                        {
                            $message = (string)$jsonResponse->ResponseMessage;
                            $this->session->data['error'] = $message;
                            $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_ktpayment_order_status_id'), $message, false);
                            $this->response->redirect($this->url->link('checkout/checkout', '', $server_conn_slug));
                        }
                    } 
                    else if(substr($response['data'],0,strlen($kt_error_response))==$kt_error_response)
                    {
                        $document = new DOMDocument();
                        $document->loadHTML(mb_convert_encoding($response['data'], 'HTML-ENTITIES', "UTF-8"));
                        $xp = new DOMXpath($document);
                        $is_success =  strtolower($xp->query('//input[@name="Success"]')[0]->getAttribute('value')) == 'true';
                        $bussines_key= (string) $xp->query('//input[@name="BusinessKey"]')[0]->getAttribute('value');
                        $response_code= (string) $xp->query('//input[@name="ResponseCode"]')[0]->getAttribute('value');
                        $response_message= (string) $xp->query('//input[@name="ResponseMessage"]')[0]->getAttribute('value');
                        if(!$is_success)
                        {
                            $this->session->data['error'] = $response_message;
                            $logMessage='BusinessKey='.$bussines_key.', ResponseCode='.$response_code.', ResponseMessage='.$response_message;
                            $this->orderHistoryLog($order_id, $logMessage);
                            $this->response->redirect($this->url->link('checkout/checkout', '', $server_conn_slug));
                        }
                    }
                    else
                    {
                        $this->orderDbUpdate($order_id, $order_info, $total, $installment);
                        echo $response['data'];
                    }
                }
                else
                {
                    $message = (string)$response['message'];
                    $this->session->data['error'] = $message;
                    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_ktpayment_order_status_id'), $message, false);
                    $this->response->redirect($this->url->link('checkout/checkout', '', $server_conn_slug));
                }
            } catch (\Throwable $th) {
                $message = (string)$th->getMessage();
                $this->session->data['error'] = $message;
                $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_ktpayment_order_status_id'), $message, false);
                $this->response->redirect($this->url->link('checkout/checkout', '', $server_conn_slug));
            }               
        }
        else
        {
            //non3d akışı     
            $parameters = array(
                'data' => $ktpay->init_non3d_request_body(),
                'url' => $environment == 'TEST' ? $ktpay->ntd_payment_test_url : $ktpay->ntd_payment_prod_url,
                'isJson' => $is3dTransaction,
                'returnType' => 'xml',
            );
            $response = $ktpay->send_request($parameters);

            if($response['success'])
            {
                $VPosTransactionResponseContract=new SimpleXMLElement($response['data']->asXML());
                if($VPosTransactionResponseContract->ResponseCode=="00")
                {                  
                    $orderMessage = 'Payment ID: ' . $orderId;
                    $this->orderDbUpdate($order_id, $order_info, $total, $installment);
                    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_ktpayment_order_status_id'), 'İşlem başarılıdır', false);
                    $this->response->redirect($this->url->link('checkout/success', '', $server_conn_slug));
                }
                else
                {
                    $message = (string)$VPosTransactionResponseContract->ResponseMessage;
                    $this->session->data['error'] = $message;
                    $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_ktpayment_order_status_id'), $message, false);
                    $this->response->redirect($this->url->link('checkout/checkout', '', $server_conn_slug));
                }
            }
            else
            {
                $message = (string)$response['message'];
                $this->session->data['error'] = $message;
                $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_ktpayment_order_status_id'), $message, false);
                $this->response->redirect($this->url->link('checkout/checkout', '', $server_conn_slug));
            }
        }

    }

    public function callback()
    {
        $server_conn_slug = $this->getServerConnectionSlug();
        $this->load->language('extension/payment/ktpayment');
        $this->load->model('extension/payment/ktpayment');
        $this->load->model('checkout/order');
        $postParams = $_POST;
        $order_id=isset($postParams['Result_MerchantOrderId']) ? $postParams['Result_MerchantOrderId'] : "";
        $orderid=substr($order_id,3,strlen($order_id));

        $order_info = $this->model_checkout_order->getOrder($orderid);
        $environment=$this->config->get('payment_ktpayment_environment');
        $merchant_id=$this->config->get('payment_ktpayment_merchantid');
        $customer_id=$this->config->get('payment_ktpayment_customerid');
        $api_username=$this->config->get('payment_ktpayment_username');
        $api_password=$this->config->get('payment_ktpayment_password');

        $total_amount = $order_info['total'];

        include DIR_SYSTEM . 'library/ktpay/KTPay.php';
        include DIR_SYSTEM . 'library/ktpay/ktconfig.php';
        
        $bodyParams=array(
            'md'=>(isset($postParams['Result_MD'])) ? $postParams['Result_MD'] : "",
            'merchant_id'=>$merchant_id,
            'customer_id' =>$customer_id,
            'amount' =>$total_amount,
            'order_id' =>isset( $postParams['Result_OrderId']) ? $postParams['Result_OrderId'] : "",
            'merchant_order_id' =>isset( $postParams['Result_MerchantOrderId']) ? $postParams['Result_MerchantOrderId'] : "",
            'api_user_name' =>$api_username,
            'api_user_password' =>$api_password,
            'response_message' => isset( $postParams['ResponseMessage']) ? $postParams['ResponseMessage'] : "",
        );

        $action = isset($_GET['action']) ? $_GET['action'] : "fail";
        $ktpay=new KTPay();
        $response=$ktpay->callback($action, $bodyParams);
        try {
            if($response['status']=="success")
            {  
                $orderMessage = 'Payment ID: ' . $postParams['Result_MerchantOrderId'];
                $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_ktpayment_order_status_id'), $response['message'], false);
                $this->response->redirect($this->url->link('checkout/success', '', $server_conn_slug));
            }
            else
            {
                $message = (string)$response['message'];
                $message = !empty($message) ? $message : 'İşlem sırasında beklenmedik bir hata oluştu!';
                $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_ktpayment_cancel_order_status_id'), $message, false);
                $this->response->redirect($this->url->link('checkout/checkout', '', $server_conn_slug));
            }
        } catch (\Throwable $th) {
            $message = (string)$th->getMessage();
            $message = !empty($message) ? $message : 'İşlem sırasında beklenmedik bir hata oluştu';
            $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_ktpayment_cancel_order_status_id'), $message, false);
            $this->response->redirect($this->url->link('checkout/checkout', '', $server_conn_slug));
        }
    }

    private function orderDbUpdate($order_id, $order_info, $total_amount, $installment)
    {
        if($installment>1)
        {
            $order_total = (array) $this->db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '" . (int) $order_id . "' AND code = 'total' ");
            $last_sort_value = $order_total['row']['sort_order'] - 1;
            $exchange_rate = $this->currency->getValue($order_info['currency_code']);
            $new_amount = str_replace(',', '', $total_amount);
            $old_amount = str_replace(',', '', $order_info['total'] * $order_info['currency_value']);
            $installment_fee_variation = ($new_amount - $old_amount) / $exchange_rate;
        
            $this->db->query("INSERT INTO " . DB_PREFIX . "order_total SET order_id = '" .
            (int) $order_id . "',code = '" . $this->db->escape('kt_installement_fee') .
            "',  title = '" . $this->db->escape('Installment Commission') . "' , `value` = '" .
            (float) $installment_fee_variation . "', sort_order = '" . (int) $last_sort_value . "'");

            $this->db->query("UPDATE " . DB_PREFIX . "order_total SET  `value` = '" . (float) $total_amount . "' WHERE order_id = '$order_id' AND code = 'total' ");
            $this->db->query("UPDATE `" . DB_PREFIX . "order` SET total = '" . $total_amount . "' WHERE order_id = '" . (int) $order_id . "'");           
        }
    }

    private function orderHistoryLog($order_id, $message)
    {
        $this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int) $order_id . "', order_status_id = '0', notify = '0', comment = '" .
                            $this->db->escape($message) . "', date_added = NOW()");
    }

    public function getSiteUrl()
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) {
            $site_url = is_null($this->config->get('config_ssl')) ? HTTPS_SERVER : $this->config->get('config_ssl');
        } else {
            $site_url = is_null($this->config->get('config_url')) ? HTTP_SERVER : $this->config->get('config_url');
        }
        return $site_url;
    }

    public function getServerConnectionSlug()
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) {
            $connection = 'SSL';
        } else {
            $connection = 'NONSSL';
        }

        return $connection;
    }

    public function replaceSpace($veri)
    {
        $veri = str_replace("/s+/", "", $veri);
        $veri = str_replace(" ", "", $veri);
        $veri = str_replace(" ", "", $veri);
        $veri = str_replace(" ", "", $veri);
        $veri = str_replace("/s/g", "", $veri);
        $veri = str_replace("/s+/g", "", $veri);
        $veri = trim($veri);
        return $veri;
    }
}