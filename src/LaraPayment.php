<?php

namespace MagedAhmad\LaraPayment;

use GuzzleHttp\Client;
use MagedAhmad\LaraPayment\Paymob;
use MagedAhmad\LaraPayment\Models\Balance_summary;

class LaraPayment
{
    use Paymob;

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
     * @param string $currency
     */
	public function __construct($currency=null)
	{ 
        $this->request = new Client();

        $this->currency=(null==$currency)?"USD":$currency; 
        
        $this->PAYMOB_API_KEY= config('larapayment.paymob_api_key');
    }
    
    /**
     * Undocumented function
     *
     * @param string $method
     * @param integer $amount
     * @return void
     */
	public function make_payment($method, $amount){ 
        $this->method=$method;
        $this->amount=$this->clac_new_amount($method,$amount);
        $this->usdtoegp = 15;

        $this->amount_in_egp = sprintf('%0.2f', ceil( $this->amount*$this->usdtoegp ) ) ; 

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
            'redirect'=>"https://accept.paymobsolutions.com/api/acceptance/iframes/".config("larapayment.paymob_iframe_id")."?payment_token=" . $paymentToken,
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
                "integration_id"=> config('larapayment.paymob_mood') == "live" ? config('larapayment.paymob_live_integration_id') : config('larapayment.paymob_sandbox_integration_id') 
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
        if($method=='paymob'){
            return floatval($amount+($amount*config('larapayment.paymob_percentage_fee'))+config('larapayment.paymob_fixed_fee'));
        }
    }
    
    public static function calc_amout_after_transaction($method,$amount){
        if($method=='paymob'){
            return floatval( ($amount-config('larapayment.paymob_fixed_fee'))/(1+config('larapayment.paymob_percentage_fee')) );
        }
    }

    /**
     * Paymob verify transacation
     *
     * @param integer $paymentId
     * @param array $response
     * @return string status 
     */
    public function verify_paymob($paymentId, $response){
        return $this->verify($paymentId, $response);
    }
}
