<?php

namespace MagedAhmad\LaraPayment;

use MagedAhmad\LaraPayment\Models\Balance_summary;

trait Paymob 
{
    /**
     * Verify completed Payment
     *
     * @param integer $paymentId
     * @param array $response
     * @return string
     */
    public function verify($paymentId, $response){ 
        $state = ['state' => null]; 

        if(!$response['success']) {
            return [
                'error' => 'Transaction wasnt successful'
            ];
        }
        $this->update_payment($paymentId,"DONE"); 
        // $this->set_response($paymentId, $response);
        $state['state'] = "DONE";  

        return $state; 
    } 

    /**
     * Set response data
     *
     * @param integer $payment_id
     * @param array $response
     * @return bool
     */
    public function set_response($payment_id,$response){
        $payment = Balance_summary::where([
            'payment_id'=>$payment_id,
            'user_id'=> auth()->user()->id
        ])->firstOrFail(); 

        $payment->update([
            'payment_response'=> $response
        ]);   

        return 1;
    }  

    /**
     * Update Payment
     *
     * @param integer $payment_id
     * @param string $status
     * @return void
     */
    protected function update_payment($payment_id, $status){
        $payment=Balance_summary::where([
            'payment_id'=>$payment_id,
            'user_id'=> auth()->user()->id,
            'status'=>"PENDING",
            'type'=>'RECHARGE'
        ])->update(['status'=>strtoupper($status)]); 
    }
}