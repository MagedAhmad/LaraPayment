<?php

namespace MagedAhmad\LaraPayment;

use GuzzleHttp\Client;
use MagedAhmad\LaraPayment\Paymob;
use MagedAhmad\LaraPayment\Models\Balance_summary;
use \PayPal\Auth\OAuthTokenCredential;
use \PayPal\Rest\ApiContext;
use \PayPal\Api\Payer;
use \PayPal\Api\Item;
use \PayPal\Api\ItemList;
use \PayPal\Api\Amount;
use \PayPal\Api\Transaction;
use \PayPal\Api\RedirectUrls;
use \PayPal\Api\Payment;
use \PayPal\Exception\PayPalConnectionException;
use \PayPal\Api\Details;
use \PayPal\Api\PaymentExecution;

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
    public $items;

    /**
     * Initiate payment helper class
     *
     * @param string $currency
     */
	public function __construct($currency=null)
	{ 
        $this->request = new Client();

        $this->currency=(null==$currency) ? "USD" : $currency; 
        
        $this->PAYMOB_API_KEY= config('larapayment.paymob_api_key');
    }
    
    /**
     * Undocumented function
     *
     * @param string $method
     * @param integer $amount
     * @return void
     */
	public function make_payment($method, $amount, $items = null){ 
        $this->method=$method;
        $this->amount=$this->clac_new_amount($method,$amount);
        $this->usdtoegp = 15;
        $this->items = $items;
        $this->amount_in_egp = $this->currency == 'USD' ? sprintf('%0.2f', ceil( $this->amount*$this->usdtoegp ) ) : $this->currency ; 

        if($this->method=="paymob"){ 
            return $this->paymob_payment(); 
        }else if($this->method=="paypal"){ 
            return $this->paypal_payment(); 
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
            'payment_token' => $paymentToken,
            'redirect'=>"https://accept.paymobsolutions.com/api/acceptance/iframes/".config("larapayment.paymob_iframe_id")."?payment_token=" . $paymentToken,
        ]; 
    }  

    public function paypal_payment(){ 
        $apiContext = new ApiContext(new OAuthTokenCredential(env('PAYPAL_CLIENT_ID'),env('PAYPAL_SECRET')));
        $apiContext->setConfig(
              array(
                'log.LogEnabled' => true,
                'log.FileName' => 'PayPal.log',
                'log.LogLevel' => 'DEBUG',
                'mode' => env('PAYPAL_MODE')
              )
        );
        $payer = new Payer();
        $payer->setPaymentMethod("paypal");  
        $item = new Item();
        $item->setName(env('PAYPAL_CREDIT_NAME'))
             ->setCurrency($this->currency)
             ->setQuantity(1)
             ->setPrice($this->amount);
        $itemList = new ItemList();
        $itemList->setItems(array($item)); 
        $details = new Details();
        $details->setSubtotal($this->amount); 
        $amount = new Amount();
        $amount->setCurrency($this->currency)
            ->setTotal($this->amount)
            ->setDetails($details);
        $transaction = new Transaction();
        $transaction->setAmount($amount)
            ->setItemList($itemList)
            ->setDescription("Nafezly Credit")
            ->setInvoiceNumber(uniqid());
        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl(route('payment.success'))
            ->setCancelUrl(route('balance'));
        $payment = new Payment();
        $payment->setIntent("sale")
            ->setPayer($payer)
            ->setRedirectUrls($redirectUrls)
            ->setTransactions(array($transaction));  
        $counter=0; 
    
        out_tyr:   
        try{  
            $payment->create($apiContext); 
            $approvalUrl = $payment->getApprovalLink();  
            $res=[
                'status'=>200, 
                'redirect'=>$approvalUrl,
                'message'=>'خطأ اثناء التنفيذ برجاء الرجوع للبنك الخاص بك او التأكد من سلامة البيانات المدخلة'
            ]; 

             
                $store_payment=$this->store_payment(
                    $payment_id=$payment->id,
                    $amount=$this->calc_amout_after_transaction("paypal",$payment->transactions[0]->amount->total),
                    $source="paypal",
                    $process_data=$payment,
                    $currency_code=strtoupper($payment->transactions[0]->amount->currency),
                    $status=strtoupper("PENDING")
                );  

            return $res; 
        }catch(\Exception $e)
        { $counter+=1;if($counter<3)goto out_tyr;  }


        $res=[
            'status'=>200, 
            'redirect'=>route('balance'),
            'message'=>'خطأ اثناء التنفيذ برجاء المحاولة مرة أخرى لاحقاً'
        ]; 

        return $res; 
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
                "items"=> $this->items ? $this->items : []
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

    /**
     * Calculate the new amount
     *
     * @param string $method
     * @param float $amount
     * @return float
     */
    public function clac_new_amount($method,$amount){
        if($method=='paymob'){
            return floatval($amount+($amount*config('larapayment.paymob_percentage_fee'))+config('larapayment.paymob_fixed_fee'));
        }else if($method=='paypal'){
            return floatval($amount+($amount*env('PAYPAL_PERCENTAGE_FEE'))+env('PAYPAL_FIXED_FEE'));
        } 
    }
    
    /**
     * Amount after transaction
     *
     * @param [type] $method
     * @param [type] $amount
     * @return void
     */
    public static function calc_amout_after_transaction($method,$amount){
        if($method=='paymob'){
            return floatval( ($amount-config('larapayment.paymob_fixed_fee'))/(1+config('larapayment.paymob_percentage_fee')) );
        }else if($method=='paypal'){
            return floatval( ($amount-env('PAYPAL_FIXED_FEE'))/(1+env('PAYPAL_PERCENTAGE_FEE')) );
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

    /**
     * Pay with vodafone cash and kiosk // paymob
     *
     * @return void
     */
    public function pay($phone, $paymentToken) {
        $response = $this->request->post("https://accept.paymobsolutions.com/api/acceptance/payments/pay", [
            "headers" => [
                'content-type' => 'application/json'
            ],
            "json" => [
                "source" => [
                    "identifier" => $phone, 
                    "subtype" => "WALLET"
                ],
                "payment_token" => $paymentToken  
            ]
        ]);

        return json_decode($response->getBody()->getContents());
    }
}
