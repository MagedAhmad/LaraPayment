<?php 

namespace MagedAhmad\LaraPayment;

use MagedAhmad\LaraPayment\Models\Balance_summary;
use \PayPal\Rest\ApiContext;
use \PayPal\Auth\OAuthTokenCredential;
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

class Paypal 
{
    public function verify($paymentId,$token,$PayerID){ 
        $client = \App\Http\Controllers\PaypalControllers\PayPalClient::client();
        $apiContext=new ApiContext(new OAuthTokenCredential(env('PAYPAL_CLIENT_ID'),env('PAYPAL_SECRET'))); 
        $apiContext->setConfig(
              array(
                'log.LogEnabled' => true,
                'log.FileName' => 'PayPal.log',
                'log.LogLevel' => 'DEBUG',
                'mode' => env('PAYPAL_MODE')
              )
        );
        $state=['state'=>null]; 
        $payment_get = Payment::get($paymentId , $apiContext); 
        
        if(isset($payment_get->payer->status)&&$payment_get->payer->status=="VERIFIED"){  

            $execution= new PaymentExecution;
            $execution->setPayerId($PayerID);
            try{
                $result=$payment_get->execute($execution,$apiContext);
                
                $this->update_payment($payment_get->id,"DONE");
                $this->set_payment_response($paymentId,$result);
                $state['state']="DONE";
            }catch(\Exception $e){ 
                exit(1);
                abort(404);
            } 
                
        } 
        else if(isset($payment_get->state)&&$payment_get->state=="created"){ 
                $this->update_payment($payment_get->id,"PENDING");
                $this->set_payment_response($paymentId,$payment_get);
                $state['state']="PENDING";
        } 

        return $state;   
    }
}