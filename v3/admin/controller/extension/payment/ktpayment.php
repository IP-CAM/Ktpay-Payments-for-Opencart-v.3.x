<?php

error_reporting(1);

class ControllerExtensionPaymentKTPayment extends Controller
{
    private $error = array();

    public function index()
    {
        $this->language->load('extension/payment/ktpayment');     
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        $this->document->addStyle('../admin/view/stylesheet/ktpayment/core.css');

        $data['breadcrumbs'] = array();
        $data['error_warning'] = '';

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/ktpayment', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        if (!$this->user->hasPermission('access', 'extension/payment/ktpayment')) {
            $this->error['warning'] = $this->language->get('error_permission');
            $this->response->setOutput($this->load->view('extension/payment/ktpayment', $data));
        }

        $merchant_id=$this->config->get('payment_ktpayment_merchantid');
        $environment=$this->config->get('payment_ktpayment_environment');
        $is_check_installment = $this->config->get('payment_ktpayment_is_check_installment');
        if($is_check_installment==null || empty($is_check_installment))
            $is_check_installment=0;

        include(DIR_SYSTEM . 'library/ktpay/ktconfig.php');

        if(isset($_POST["checkInstallmentDefinition"])){
            $result=$this->checkInstallmentDefinition($merchant_id, $environment);   
            if($result['success']==false)
            {
                $data['error_warning'] = $result['message'];
            }
        }
        else if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateBeforeSave()) {
            if($is_check_installment==0)
            {
                $result=$this->checkInstallmentDefinition($this->request->post["payment_ktpayment_merchantid"], $this->request->post["payment_ktpayment_environment"]);   
                if($result['success'])
                {
                    $is_check_installment=1;
                    $this->request->post["payment_ktpayment_rates"]=$this->getConfig('payment_ktpayment_rates','payment_ktpayment');
                }
                else
                {
                    $installmentArray=[1];
                    $this->request->post["payment_ktpayment_rates"]=KTPayConfig::init_rates($installmentArray);
                    $this->request->post["payment_ktpayment_installments"]=$installmentArray;
                }
            }
            $this->request->post["payment_ktpayment_is_check_installment"] = $is_check_installment;
            $this->model_setting_setting->editSetting('payment_ktpayment', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        $data['heading_title'] = $this->language->get('heading_title');
        $data['admin_label_isactive'] = $this->language->get('admin_label_isactive');
        $data['admin_label_environment'] = $this->language->get('admin_label_environment');
        $data['admin_label_merchantid'] = $this->language->get('admin_label_merchantid');
        $data['admin_label_customerid'] = $this->language->get('admin_label_customerid');
        $data['admin_label_username'] = $this->language->get('admin_label_username');
        $data['admin_label_password'] = $this->language->get('admin_label_password');
        $data['admin_label_hasneccessary3dapprove'] = $this->language->get('admin_label_hasneccessary3dapprove');
        $data['admin_label_over3damount'] = $this->language->get('admin_label_over3damount');
        $data['admin_label_installmentoptions'] = $this->language->get('admin_label_installmentoptions');       

        $data['admin_tooltip_merchantid'] = $this->language->get('admin_tooltip_merchantid');
        $data['admin_tooltip_customerid'] = $this->language->get('admin_tooltip_customerid');
        $data['admin_tooltip_username'] = $this->language->get('admin_tooltip_username');
        $data['admin_tooltip_password'] = $this->language->get('admin_tooltip_password');
        $data['admin_tooltip_over3damount'] = $this->language->get('admin_tooltip_over3damount');
        $data['admin_tooltip_installmentoptions'] = $this->language->get('admin_tooltip_installmentoptions');

        $data['admin_button_checkinstallmentoptions'] = $this->language->get('admin_button_checkinstallmentoptions');
        
        $rates=$this->config->get('payment_ktpayment_rates');
        $installments=(array) $this->config->get('payment_ktpayment_installments');

        if ($rates==null && $merchant_id !=null && strlen(trim($merchant_id)) != 0 && $environment!=null && strlen(trim($environment)) != 0) {
            $result=$this->checkInstallmentDefinition($merchant_id, $environment);   
            if($result['success'])
            {
                $rates=$this->getConfig('payment_ktpayment_rates', 'payment_ktpayment');      
                $installments=(array) $this->getConfig('payment_ktpayment_installments','payment_ktpayment_spec');   
            }
            else
            {
                $data['error_warning'] = $result['message'];
            }
        }

        $data['action'] = $this->url->link('extension/payment/ktpayment', 'user_token=' . $this->session->data['user_token'], 'SSL');

        $admin_params = array(
            'payment_ktpayment_status',
            'payment_ktpayment_environment',
            'payment_ktpayment_merchantid',
            'payment_ktpayment_customerid',
            'payment_ktpayment_username',
            'payment_ktpayment_password',
            'payment_ktpayment_has3dsecureapprove',
            'payment_ktpayment_over3dsecureamount',
            'payment_ktpayment_installmentoptions'
        );

        foreach ($admin_params as $key) {
            $data[$key] = isset($this->request->post[$key]) ? $this->request->post[$key] : $this->config->get($key);
        }
        
        $order_status_id = $this->config->get('payment_ktpayment_order_status_id');
        $cancel_order_status_id = $this->config->get('payment_ktpayment_cancel_order_status_id');

        if($order_status_id==null || $order_status_id=='')
        {
            $order_status_id = $this->config->get('config_order_status_id');
            $this->setConfig('payment_ktpayment_order_status_id', $order_status_id,'payment_ktpayment_spec', 0);
        }
        
        if($cancel_order_status_id==null || $cancel_order_status_id='')
        {
            $order_status = (array) $this->db->query("SELECT * FROM " . DB_PREFIX . "order_status WHERE name = 'Canceled'");
            $cancel_order_status_id = $order_status['row']['order_status_id'];
            
            $this->setConfig('payment_ktpayment_cancel_order_status_id', $cancel_order_status_id, 'payment_ktpayment_spec',0);
        }
        
        if($rates!=null && $installments!=null)
            $data['payment_ktpayment_rates_table'] = KTPayConfig::create_rates_update_form($rates,$installments);
        
        $data['payment_ktpayment_rates'] = $rates;
        $data['payment_ktpayment_order_status_id'] = $order_status_id;
        $data['payment_ktpayment_cancel_order_status_id'] = $cancel_order_status_id;
        $data['message'] = '';
        
        $this->response->setOutput($this->load->view('extension/payment/ktpayment', $data));
    }

    protected function checkInstallmentDefinition($merchant_id, $environment){
        if($merchant_id==null || strlen(trim($merchant_id)) === 0){
            return array(
                'success'=>false,
                'message'=>'Üye işyeri numarası girilmelidir'
            );
        }

        include(DIR_SYSTEM . 'library/ktpay/KTPay.php');
               
        $ktpay=new KTPay();        
        $installmentResult = $ktpay->check_installment_definition($environment, $merchant_id);

        if($installmentResult['success'])
        {                  
            $this->setConfig('payment_ktpayment_rates', json_encode(KTPayConfig::init_rates($installmentResult['data'])), 'payment_ktpayment',1);
            $this->setConfig('payment_ktpayment_installments', json_encode($installmentResult['data']),'payment_ktpayment_spec',1);

            return array(
                'success'=>true,                
            );
        }
        else
        {
            $this->logInsert($installmentResult['message']);
            return array(
                'success'=>false,
                'message'=>'Taksit tanımı kontrol edilemedi'
            );
        }

    }

    protected function validateBeforeSave()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/ktpayment')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        $validation_array = array(
            'payment_ktpayment_merchantid',
            'payment_ktpayment_customerid',
            'payment_ktpayment_username',
            'payment_ktpayment_password',
        );

        foreach ($validation_array as $key) {
            if (empty($this->request->post["{$key}"])) {
                $this->error[$key] = $this->language->get("error_$key");
            }
        }
        if (!$this->error) {
            return true;
        } else {
            return false;
        }
    }

    private function logInsert($message) {
        $log = new Log('ktpayment.log');
        $log->write($message);
    }

    private function setConfig($key, $value, $code, $serialized){
        $setting = $this->db->query("SELECT * FROM ". DB_PREFIX ."setting WHERE code = '".$code."' AND `key` = '".$key."'");

        if($setting!=null && $setting->num_rows>0)
        {
            $this->db->query("UPDATE " . DB_PREFIX . "setting SET value='".$value."' WHERE code = '".$code."' AND `key` = '".$key."'");
        }
        else
        {
            $this->db->query("INSERT INTO " . DB_PREFIX . "setting (code,`key`,value, serialized) VALUES  ('".$code."','".$key."','".$value."',".$serialized.")");
        }
    }

    private function getConfig($key ,$code){
        $setting = (array) $this->db->query("SELECT * FROM ". DB_PREFIX ."setting WHERE code = '".$code."' AND `key` = '".$key."'");

        return $setting['row']['value'];
    }
}