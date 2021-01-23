<?php

namespace MagedAhmad\LaraPayment;

use GuzzleHttp\Client;
use MagedAhmad\LaraPayment\Models\Balance_summary;

class LaraPayment
{
    public $request;
    public $method;
    public $amount;
    public $token;
    public $currency;
    public $sk_code;
    public $usdtoegp;
    public $PAYMOB_API_KEY;
    public $FAWRY_MERCHANT;
    public $FAWRY_SECRET;

    /**
     * Initiate payment helper class
     *
     * @param [type] $method
     * @param [type] $amount
     * @param [type] $token
     * @param [type] $currency
     */
	public function __construct($method=null,$amount=null,$token=null,$currency=null)
	{ 
        $this->request = new Client();
        $this->method=$method;
        $this->amount=$this->clac_new_amount($method,$amount);
        $this->usdtoegp = 15;

        $this->amount_in_egp = sprintf('%0.2f', ceil( $this->amount*$this->usdtoegp ) ) ; 
        $this->currency=(null==$currency)?"USD":$currency; 
        
        $this->PAYMOB_API_KEY=env('PAYMOB_API_KEY');
	}

	public function make_payment(){ 
        if($this->method=="paymob"){ 
            return $this->paymob_payment(); 
        }  
    } 

    /**
     * Paymob payment workflow
     * This function go through the workflow of paymob integration 
     * and return the ifram url used to make the payment
     *
     * @return array 
     */
    public function paymob_payment(){ 
        $request = new Client();
        // get auth token
        $token = $this->getPaymobAuthenticationToken();
        // make order
        $order = $this->makePaymobOrder($token->token);

        // store payment in DB with bending status
        $store_payment= $this->store_payment(
            $payment_id=$order->id,
            $amount=$this->calc_amout_after_transaction("paymob",$this->amount),
            $source="credit",
            $process_data= json_encode($order),
            $currency_code="USD",
            $status=strtoupper("PENDING"),
            $note=$this->amount_in_egp
        ); 
        // create key token 
        // used for iframe 
        $paymentToken = $this->createPaymobPaymentToken($token->token, $order);
        
        return [
            'status'=>200,
            'redirect'=>"https://accept.paymobsolutions.com/api/acceptance/iframes/".env("PAYMOB_IFRAME_ID")."?payment_token=" . $paymentToken,
        ]; ;
    }  

    /**
     * Create payment token
     *
     * @param string $token
     * @param json $order
     * 
     * @return string token
     */
    public function createPaymobPaymentToken($token, $order) 
    {
        $response = $this->request->post("https://accept.paymobsolutions.com/api/acceptance/payment_keys", [
            "headers" => [
                'content-type' => 'application/json'
            ],
            "json" => [
                "auth_token"=> $token, 
                "expiration"=> 36000, 
                "amount_cents"=>$order->amount_cents,
                "order_id"=>$order->id,
                "billing_data"=>[
                    "apartment"=> "NA", 
                    "email"=> \Auth::user()->email, 
                    "floor"=> "NA", 
                    "first_name"=> (null==\Auth::user()->first_name)?\Auth::user()->name:\Auth::user()->first_name, 
                    "street"=> "NA", 
                    "building"=> "NA", 
                    "phone_number"=> \Auth::user()->phone , 
                    "shipping_method"=> "NA", 
                    "postal_code"=> "NA", 
                    "city"=> "NA", 
                    "country"=> "NA", 
                    "last_name"=> (null==\Auth::user()->last_name)?\Auth::user()->name:\Auth::user()->last_name, 
                    "state"=> "NA" 
                ],
                "currency"=>"EGP",
                "integration_id"=> env('PAYMOB_MOOD') == "live" ? env('PAYMOB_LIVE_INTEGRATION_ID') : env('PAYMOB_SANDBOX_INTEGRATION_ID') 
            ]
        ]);

        return json_decode($response->getBody()->getContents())->token;
    }

    /**
     * Make Paymob Order
     *
     * @param string $token
     * 
     * @return json
     */    
    public function makePaymobOrder($token) {
        $response = $this->request->post("https://accept.paymobsolutions.com/api/ecommerce/orders", [
            "headers" => [
                'content-type' => 'application/json'
            ],
            "json" => [
                "auth_token"=> $token, 
                "delivery_needed"=>"false",
                "amount_cents"=>$this->amount_in_egp*100,
                "items"=>[
                ]
            ]
        ]);

        return json_decode($response->getBody()->getContents());
    }

    /**
     * Paymob get authentication Token
     *
     * @return string token
     */
    public function getPaymobAuthenticationToken() {
        $response = $this->request->post("https://accept.paymobsolutions.com/api/auth/tokens", [
            "headers" => [
                'content-type' => 'application/json'
            ],
            "json" => [
                "api_key" => $this->PAYMOB_API_KEY
            ]
        ]);

        return json_decode($response->getBody()->getContents());
    }

    /**
     * Store payment information in DB
     *
     * @param integer $payment_id
     * @param integer $amount
     * @param string $source
     * @param array $process_data
     * @param string $currency_code
     * @param string $status
     * @param string $note
     * @return integer id
     */
    public function store_payment(
        $payment_id,
        $amount,
        $source,
        $process_data,
        $currency_code,
        $status,
        $note = null
    ){  
        $payment = Balance_summary::where([
            'user_id'=>\Auth::user()->id,
            'payment_id' => $payment_id, 
        ])->first(); 

        if($payment==null){  
            $payment = Balance_summary::create(
                [
                    "user_id"=> \Auth::user()->id ,
                    'payment_id'=>$payment_id,
                    "type"=>"RECHARGE",
                    "amount"=>$amount,
                    "status"=>strtoupper($status),
                    "source"=>$source, 
                    "currency_code"=>strtoupper($currency_code), 
                    "process_data"=>(string)$process_data,
                    "note"=>$note
                ]
            );
            return $payment->id;
        }else{
            return $payment->id;
        }
    }

    public function clac_new_amount($method,$amount){
        if($method=='paypal'){
            return floatval($amount+($amount*env('PAYPAL_PERCENTAGE_FEE'))+env('PAYPAL_FIXED_FEE'));
        } if($method=='paymob'){
            return floatval($amount+($amount*env('PAYMOB_PERCENTAGE_FEE'))+env('PAYMOB_FIXED_FEE'));
        } else if($method=='tap'){
            return floatval($amount+($amount*env('TAP_PERCENTAGE_FEE'))+env('TAP_FIXED_FEE'));
        }
    }
    
    public static function calc_amout_after_transaction($method,$amount){
        if($method=='paypal'){
            return floatval( ($amount-env('PAYPAL_FIXED_FEE'))/(1+env('PAYPAL_PERCENTAGE_FEE')) );
        }else if($method=='paymob'){
            return floatval( ($amount-env('PAYMOB_FIXED_FEE'))/(1+env('PAYMOB_PERCENTAGE_FEE')) );
        }else if($method=='tap'){
            return floatval( ($amount-env('TAP_FIXED_FEE'))/(1+env('TAP_PERCENTAGE_FEE')) );
        } 
    }
}
