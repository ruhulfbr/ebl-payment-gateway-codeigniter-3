<?php
    @session_start();
    require "nvp/merchant.php";
    require "nvp/connection.php";

    class Skypay
    {
        protected $skypay;
        protected $merchant;
        protected $parser;
        protected $order;
        protected $completeUrl;

        public function __construct($configArray)
        {
            // The below value should not be changed
            if (!array_key_exists("proxyCurlOption", $configArray)) {
                $configArray["proxyCurlOption"] = CURLOPT_PROXYAUTH;
            }
            
            // The CURL Proxy type. Currently supported values: CURLAUTH_NTLM and CURLAUTH_BASIC
            if (!array_key_exists("proxyCurlValue", $configArray)) {
                $configArray["proxyCurlValue"] = CURLAUTH_NTLM;
            }

            // Base URL of the Payment Gateway. Do not include the version.
            if (!array_key_exists("gatewayUrl", $configArray)) {
                if ($configArray["gatewayMode"] === true) {
                    $configArray["gatewayUrl"] = "https://ap-gateway.mastercard.com/api/nvp";
                } else {
                    $configArray["gatewayUrl"] = "https://test-gateway.mastercard.com/api/nvp";
                }
            }
            // API username in the format below where Merchant ID is the same as above
            $configArray["apiUsername"] = "merchant." . $configArray["merchantId"];

            $this->merchant = new Merchant($configArray);
            $this->parser = new Parser($this->merchant);
        }

        public function Request($requestArray, $requestType="POST")
        {
            $requestUrl = $this->parser->FormRequestUrl($this->merchant);

            //This builds the request adding in the merchant name, api user and password.
            $request = $this->parser->ParseRequest($this->merchant, $requestArray);
            //Submit the transaction request to the payment server
            $response = $this->parser->SendTransaction($this->merchant, $request, $requestType);

            //Parse the response
            $result = $this->ParseData($response);

            return $result;
        }

        public function Checkout($orderArray)
        {
            $this->RectifyOrder($orderArray);
            $this->order = $this->Array2Dot($orderArray);

            // $this->pr(array_merge(array("apiOperation" => "INITIATE_CHECKOUT", "order.description" => "TEST ORDER"), $this->order));
            
            $requestArray = array_merge(array("apiOperation" => "INITIATE_CHECKOUT", "order.description" => "TEST ORDER"), $this->order);

            file_put_contents("log.txt", date("YYY-mm-dd hh:ii:ss"), FILE_APPEND);

            file_put_contents("log.txt", print_r($requestArray, true), FILE_APPEND);
            
            file_put_contents("log.txt", print_r($this->merchant, true), FILE_APPEND);
                                                     
            $checkout = $this->Request($requestArray);

            file_put_contents("log.txt", print_r($checkout, true), FILE_APPEND);

            // die();

            if ($checkout['result'] == 'SUCCESS') {
                $_SESSION['eblskypay'] = $checkout;
                $url = parse_url($this->merchant->GetGatewayUrl());
                $url['host'] = str_replace('-', '.', 'easternbank.' . $url['host']);
                $url['path'] = "/checkout/entry/" . $checkout["session.id"].'?checkoutVersion=1.0.0';
                $this->redirect($url['scheme'] . '://' . $url['host'] . $url['path']);
            }
            return $checkout;
        }

        public function RetrieveOrder($orderID)
        {
            $requestArray = array(
                "apiOperation" => "RETRIEVE_ORDER",
                "order.id" => $orderID
            );

            return $this->Request($requestArray);
        }

        public function VoidTransaction($orderID, $transactionID)
        {
            $requestArray = array(
                "apiOperation" => "VOID",
                "order.id" => $orderID,
                "transaction.targetTransactionId" => $transactionID,
                "transaction.id" => 'VOID-' . $transactionID
            );

            return $this->Request($requestArray);
        }

        // function for removing unnecessary data
        // basically it removes single dimension data from array
        public function RectifyOrder(&$orderArray)
        {
            foreach ($orderArray as $key=>$value) {
                if ($key == 'checkoutMode') {
                    continue;
                } elseif (!is_array($value)) {
                    //$this->pr($value);
                    unset($orderArray[$key]);
                }
            }
        }

        // array to dot notation
        public function Array2Dot($dataArray)
        {
            $recursiveDataArray = new RecursiveIteratorIterator(new RecursiveArrayIterator($dataArray));
            $result = array();
            foreach ($recursiveDataArray as $leafValue) {
                $keys = array();
                foreach (range(0, $recursiveDataArray->getDepth()) as $depth) {
                    $keys[] = $recursiveDataArray->getSubIterator($depth)->key();
                }
                $result[ join('.', $keys) ] = $leafValue;
            }
            return $result;
        }

        //convert nvp data to array
        public function ParseData($string)
        {
            $array=array();
            $pairArray = array();
            $param = array();
            if (strlen($string) != 0) {
                $pairArray = explode("&", $string);
                foreach ($pairArray as $pair) {
                    $param = explode("=", $pair);
                    $array[urldecode($param[0])] = urldecode($param[1]);
                }
            }
            return $array;
        }

        public function redirect($newURL)
        {
            header('Location: ' . $newURL);
            die();
        }

        public function pr($data)
        {
            echo '<pre>';
            print_r($data);
            echo '</pre>';
        }
    }
