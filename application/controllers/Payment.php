<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Payment extends CI_Controller {

    function __construct() {
        parent::__construct();
    }

    public function index() {
        $paymentData = [
            'amount'  => '1200',
            'user_id' => 'ruhul64' 
        ];

        $orderId = 'ebl-'.time();

        $this->load->library('ebl');
        $skypay = $this->ebl->load();

        $postData = [];
        $postData['order'] = array(
            'id' => $orderId,
            'amount' => $paymentData['amount'],
            'description' => 'OneSky monthly internet bill for user id: '.$paymentData['user_id'],
            'currency' => 'BDT',
        );
        $postData['submit'] = 'PAY WITH EBL SKYPAY';

        $postData['interaction'] = [
            'merchant' => [
                'name' => 'OneSky Communications Limited.',
                'logo' => 'https://onesky.com.bd/assets/frontend/images/logo.png',
            ],
            'displayControl' =>[
                'billingAddress' => 'HIDE'
            ],
            'operation' => 'PURCHASE',
            'timeout'   => '600',
            'timeoutUrl' => site_url('payment/callback/timeout/'.$orderId),
            'cancelUrl'  => site_url('payment/callback/cancel/'.$orderId),
            'returnUrl'  => site_url('payment/callback/complete/'.$orderId),
        ];

        $paymentData['order_id']   = $orderId;
        $paymentData['status']     = 'processing';
        $paymentData['request']    = json_encode($postData);
        $paymentData['created_at'] = date('Y-m-d H:i:s');
        // Store Payment request data to database

        $result = $skypay->Checkout($postData);

        if(isset($result['result']) && $result['result'] == 'ERROR'){
            
            $updatePayment = [];
            $updatePayment['status']     = 'error';
            $updatePayment['response']   = json_encode($result);
            $updatePayment['updated_at'] = date('Y-m-d H:i:s');
            //Update Payment Information in Database

            $data = ['type' => 'error', 'message' => 'Payment can not be initialize. please try again.'];

            echo "<pre>";
            print_r($data);
            exit();
        }
    }

    public function callback($type, $orderId){
        $data = ['type' => 'error', 'message' => 'Payment failed. please try again.'];

        if( $type == 'cancel' ){  

            $updatePayment = [];
            $updatePayment['status']     = 'cancel';
            $updatePayment['response']   = json_encode($_REQUEST);
            $updatePayment['updated_at'] = date('Y-m-d H:i:s');            
            //Update Payment Information in Database

            $data = ['type' => 'error', 'message' => 'Your payment has been canceled.'];
        }

        if( $type == 'timeout' ){  
            $updatePayment = [];
            $updatePayment['status']     = 'timeout';
            $updatePayment['response']   = json_encode($_REQUEST);
            $updatePayment['updated_at'] = date('Y-m-d H:i:s');
            
            //Update Payment Information in Database

            $data = ['type' => 'error', 'message' => 'Your payment operation timeout'];
        }

        if( $type == 'complete' ){
            $this->load->library('ebl');

            $transactionId   = $orderId;
            $resultIndicator = $this->input->get('resultIndicator') ? $this->input->get('resultIndicator') : '';
            $eblskypay       = $this->session->userdata('eblskypay'); // That was set in skypay.php 78 number line
            $status          = 'failed';

            $responseArray = [];
            if( !empty($eblskypay['successIndicator']) && ($eblskypay['successIndicator'] == $resultIndicator) ) {
                $skypay        = $this->ebl->load();
                $responseArray = $skypay->RetrieveOrder($transactionId);

                if(isset($responseArray['id']) && ($responseArray['id'] == $transactionId) && ($responseArray['result'] == 'SUCCESS') && ($responseArray['amount'] == $responseArray['totalAuthorizedAmount']) && ($responseArray['amount'] == $responseArray['totalAuthorizedAmount']) && ($responseArray['status'] == 'CAPTURED')) {
                    
                    $status = 'success';
                }
            }
            else{
                $responseArray = $_REQUEST;
            }

            $updatePayment = [];
            $updatePayment['status']     = $status;
            $updatePayment['response']   = json_encode($responseArray);
            $updatePayment['updated_at'] = date('Y-m-d H:i:s');

            //Update Payment Information in Database

            if( $status == 'success' ){
                $data = ['type' => 'success', 'message' => 'Payment received successfully, thank you.'];
            }
            else{
                $data = ['type' => 'error', 'message' => 'Payment failed. please try again.'];
            }
        }

        echo "<pre>";
        print_r($data);
        exit();
        
    }

}
