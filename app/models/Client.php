<?php

class Client extends \Eloquent {

	// Add your validation rules here
	public static $rules = [
		 'name' => 'required',
		 'email_office' => 'email|unique:clients,email',
		 'email_personal' => 'email|unique:clients,contact_person_email',
		 'type' => 'required',
		 'mobile_phone' => 'unique:clients,contact_person_phone',
		 'office_phone' => 'unique:clients,phone',
     'credit_limit' => 'required',
     'credit_period' => 'required',
	];

    public static function rolesUpdate($id)
    {
        return array(
        'name' => 'required',
		 'email_office' => 'email|unique:clients,email,' . $id,
		 'email_personal' => 'email|unique:clients,contact_person_email,' . $id,
		 'type' => 'required',
		 'mobile_phone' => 'unique:clients,contact_person_phone,' . $id,
		 'office_phone' => 'unique:clients,phone,' . $id,
     'credit_limit' => 'required',
     'credit_period' => 'required'
        );
    }

    public static $messages = array(
    	'name.required'=>'Please insert client name!',
        'email_office.email'=>'That please insert a vaild email address!',
        'email_office.unique'=>'That office email already exists!',
        'email_personal.email'=>'That please insert a vaild email address!',
        'email_personal.unique'=>'That office email already exists!',
        'mobile_phone.unique'=>'That mobile number already exists!',
        'office_phone.unique'=>'That office mobile already exists!',
        'credit_limit.required'=>'Please insert credit limit!',
        'credit_period.required'=>'Please insert credit period!'
    );

	// Don't forget to fill this array
	protected $fillable = [];


	public function erporders(){

		return $this->hasMany('Erporder');
	}

	public function payments(){

		return $this->hasMany('Payment');
	}

  public function prices(){

    return $this->hasMany('Price');
  }


  /**
   * GETTING CLIENT ACCOUNT BALANCES [TOTAL-BALANCES]
   * @param  [type] $id [description]
   * @return [type]     [description]
   */
	public static function due($id){

          $client = Client::find($id);
          $order = 0;

          if($client->type == 'Customer'){
             $order = DB::table('erporders')
                     ->join('erporderitems','erporders.id','=','erporderitems.erporder_id')
                     ->join('clients','erporders.client_id','=','clients.id')           
                     ->where('clients.id',$id)
                     ->where('erporders.type','=','sales')
                     ->where('erporders.status','!=','cancelled')   
                     ->selectRaw('SUM(price * quantity)-COALESCE(SUM(discount_amount),0)- COALESCE(SUM(erporderitems.client_discount),0) + COALESCE(clients.balance,0)  as total')
                     ->pluck('total');
          }
          else{
              $order = DB::table('erporders')
                     ->join('erporderitems','erporders.id','=','erporderitems.erporder_id')
                     ->join('clients','erporders.client_id','=','clients.id')           
                     ->where('clients.id',$id) ->selectRaw('SUM(price * quantity)as total')
                     ->pluck('total');
                   
          }

          $paid = DB::table('clients')
                 ->join('payments','clients.id','=','payments.client_id')
                 ->where('clients.id',$id) 
                 ->selectRaw('COALESCE(SUM(amount_paid),0) as due')
                 ->pluck('due');

          /*$discount = DB::table('erporders')
                    ->join('erporderitems','erporders.id','=','erporderitems.erporder_id')
                    ->join('clients','erporders.client_id','=','clients.id') 
                    ->select ('discount_amount')
                    ->get();*/

      return ($order-$paid);
  }



  /**
   * BALANCES TODAY
   */
  public static function dueToday($id){
      
      $from = date('Y-m-d 00:00:00');
      $to = date('Y-m-d 23:59:59');

      $client = Client::find($id);
      $order = 0;

      $fromdate = strtotime("-".($client->credit_period)." days", strtotime($from));
      date("Y-m-d 00:00:00", $fromdate);

      if($client->type == 'Customer'){
         $order = DB::table('erporders')
                 ->join('erporderitems','erporders.id','=','erporderitems.erporder_id')
                 ->join('clients','erporders.client_id','=','clients.id')           
                 ->where('clients.id',$id)
                 ->where('erporders.type','=','sales')
                 ->where('erporders.status','!=','cancelled') 
                 ->whereBetween('erporders.created_at', array(date("Y-m-d 00:00:00", $fromdate), date("Y-m-d 23:59:59")))
                 ->selectRaw('SUM(price * quantity)-COALESCE(SUM(discount_amount),0)- COALESCE(SUM(erporderitems.client_discount),0) + COALESCE(clients.balance,0)  as total')
                 ->pluck('total');
      }
      else{
          $order = DB::table('erporders')
                 ->join('erporderitems','erporders.id','=','erporderitems.erporder_id')
                 ->join('clients','erporders.client_id','=','clients.id')     
                 ->whereBetween('erporders.created_at', array(date("Y-m-d 00:00:00", $fromdate), date("Y-m-d 23:59:59")))     
                 ->where('clients.id',$id) ->selectRaw('SUM(price * quantity)as total')
                 ->pluck('total');
               
      }

      $paid = DB::table('clients')
             ->join('payments','clients.id','=','payments.client_id')
             ->where('clients.id',$id) 
             ->whereBetween('payments.payment_date',array(date("Y-m-d 00:00:00", $fromdate), date("Y-m-d 23:59:59"))) 
             ->selectRaw('COALESCE(SUM(amount_paid),0) as due')
             ->pluck('due');

      if(($order-$paid) < 0){
        return 0;
      }else{
      return ($order-$paid);
      }
  }

/**
 * BALANCES <= 30 DAYS
 */
public static function due30($id){
      $from = date('Y-m-d 00:00:00');
      $to = date('Y-m-d 23:59:59');

      $client = Client::find($id);
      $order = 0;

      $fromdate = strtotime("+".($client->credit_period-30)." days", strtotime($from));
      date("Y-m-d", $fromdate);

      $todate = strtotime("+".($client->credit_period-1)." days", strtotime($to));
      date("Y-m-d", $todate);

      if($client->type == 'Customer'){
         $order = DB::table('erporders')
                 ->join('erporderitems','erporders.id','=','erporderitems.erporder_id')
                 ->join('clients','erporders.client_id','=','clients.id')           
                 ->where('clients.id',$id)
                 ->where('erporders.type','=','sales')
                 ->where('erporders.status','!=','cancelled')  
                 ->whereBetween('erporders.created_at', array(date("Y-m-d 00:00:00", $fromdate), date("Y-m-d 23:59:59", $todate))) 
                 ->selectRaw('SUM(price * quantity)-COALESCE(SUM(discount_amount),0)- COALESCE(SUM(erporderitems.client_discount),0) + COALESCE(clients.balance,0)  as total')
                 ->pluck('total');
      }
      else{
          $order = DB::table('erporders')
                 ->join('erporderitems','erporders.id','=','erporderitems.erporder_id')
                 ->join('clients','erporders.client_id','=','clients.id') 
                 ->whereBetween('erporders.created_at', array(date("Y-m-d 00:00:00", $fromdate), date("Y-m-d 23:59:59", $todate)))
                 ->where('clients.id',$id) ->selectRaw('SUM(price * quantity)as total')
                 ->pluck('total');
               
      }

      $paid = DB::table('clients')
             ->join('payments','clients.id','=','payments.client_id')
             ->where('clients.id',$id) 
             ->whereBetween('payments.created_at', array(date("Y-m-d 00:00:00", $fromdate), date("Y-m-d 23:59:59", $todate)))
             ->selectRaw('COALESCE(SUM(amount_paid),0) as due')
             ->pluck('due');

      if(($order-$paid) < 0){
        return 0;
      }else{
      return ($order-$paid);
      }
  }

  /**
   * BALANCES 31 <= 60
   */
  public static function due60($id){
      $from = date('Y-m-d 00:00:00');
      $to   = date('Y-m-d 23:59:59');

      $client = Client::find($id);
      $order = 0;

      $fromdate = strtotime("+".($client->credit_period-60)." days", strtotime($from));
      date("Y-m-d", $fromdate);

      $todate = strtotime("+".($client->credit_period-31)." days", strtotime($to));
      date("Y-m-d", $todate);

      if($client->type == 'Customer'){
         $order = DB::table('erporders')
                 ->join('erporderitems','erporders.id','=','erporderitems.erporder_id')
                 ->join('clients','erporders.client_id','=','clients.id')           
                 ->where('clients.id',$id)
                 ->where('erporders.type','=','sales')
                 ->where('erporders.status','!=','cancelled')  
                 ->whereBetween('erporders.created_at', array(date("Y-m-d 00:00:00", $fromdate), date("Y-m-d 23:59:59", $todate))) 
                 ->selectRaw('SUM(price * quantity)-COALESCE(SUM(discount_amount),0)- COALESCE(SUM(erporderitems.client_discount),0) + COALESCE(clients.balance,0)  as total')
                 ->pluck('total');
      }
      else{
          $order = DB::table('erporders')
                 ->join('erporderitems','erporders.id','=','erporderitems.erporder_id')
                 ->join('clients','erporders.client_id','=','clients.id')           
                 ->whereBetween('erporders.created_at', array(date("Y-m-d 00:00:00", $fromdate), date("Y-m-d 23:59:59", $todate)))
                 ->where('clients.id',$id) ->selectRaw('SUM(price * quantity)as total')
                 ->pluck('total');
               
      }

      $paid = DB::table('clients')
             ->join('payments','clients.id','=','payments.client_id')
             ->where('clients.id',$id) 
             ->whereBetween('payments.created_at', array(date("Y-m-d 00:00:00", $fromdate), date("Y-m-d 23:59:59", $todate)))
             ->selectRaw('COALESCE(SUM(amount_paid),0) as due')
             ->pluck('due');

      if(($order-$paid) < 0){
        return 0;
      }else{
      return ($order-$paid);
      }
  }


  /**
   * BALANCES 61 <= 90
   */
  public static function due90($id){
      $from = date('Y-m-d 00:00:00');
      $to = date('Y-m-d 23:59:59');

      $client = Client::find($id);
      $order = 0;

      $fromdate = strtotime("+".($client->credit_period-90)." days", strtotime($from));
      date("Y-m-d 00:00:00", $fromdate);

      $todate = strtotime("+".($client->credit_period-61)." days", strtotime($to));
      date("Y-m-d 23:59:59", $todate);

      if($client->type == 'Customer'){
         $order = DB::table('erporders')
                 ->join('erporderitems','erporders.id','=','erporderitems.erporder_id')
                 ->join('clients','erporders.client_id','=','clients.id')           
                 ->where('clients.id',$id)
                 ->where('erporders.type','=','sales')
                 ->where('erporders.status','!=','cancelled') 
                 ->whereBetween('erporders.created_at', array(date("Y-m-d 00:00:00", $fromdate), date("Y-m-d 23:59:59", $todate)))
                 ->selectRaw('SUM(price * quantity)-COALESCE(SUM(discount_amount),0)- COALESCE(SUM(erporderitems.client_discount),0) + COALESCE(clients.balance,0)  as total')
                 ->pluck('total');
      }
      else{
          $order = DB::table('erporders')
                 ->join('erporderitems','erporders.id','=','erporderitems.erporder_id')
                 ->join('clients','erporders.client_id','=','clients.id')     
                 ->whereBetween('erporders.created_at', array(date("Y-m-d 00:00:00", $fromdate), date("Y-m-d 23:59:59", $todate)))       
                 ->where('clients.id',$id) ->selectRaw('SUM(price * quantity)as total')
                 ->pluck('total');
               
      }

      $paid = DB::table('clients')
             ->join('payments','clients.id','=','payments.client_id')
             ->where('clients.id',$id) 
             ->whereBetween('payments.created_at', array(date("Y-m-d 00:00:00", $fromdate), date("Y-m-d 23:59:59", $todate))) 
             ->selectRaw('COALESCE(SUM(amount_paid),0) as due')
             ->pluck('due');

      if(($order-$paid) < 0){
        return 0;
      }else{
      return ($order-$paid);
      }
  }


  /**
   * BALANCES >90
   */
  public static function due91($id){
      $date90 = date('Y-m-d', strtotime('-90 days'));

      $client = Client::find($id);
      $order = 0;

      $date = strtotime("+".($client->credit_period-90)." days", strtotime($date90));
      date("Y-m-d", $date);



      if($client->type == 'Customer'){
         $order = DB::table('erporders')
                 ->join('erporderitems','erporders.id','=','erporderitems.erporder_id')
                 ->join('clients','erporders.client_id','=','clients.id')           
                 ->where('clients.id',$id)
                 ->where('erporders.type','=','sales')
                 ->where('erporders.status','!=','cancelled') 
                 ->whereDate('erporders.created_at','<',date("Y-m-d", $date))   
                 ->selectRaw('SUM(price * quantity)-COALESCE(SUM(discount_amount),0)- COALESCE(SUM(erporderitems.client_discount),0) + COALESCE(clients.balance,0)  as total')
                 ->pluck('total');
      }
      else{
          $order = DB::table('erporders')
                 ->join('erporderitems','erporders.id','=','erporderitems.erporder_id')
                 ->join('clients','erporders.client_id','=','clients.id')     
                 ->whereDate('erporders.created_at','<',date("Y-m-d", $date))         
                 ->where('clients.id',$id) ->selectRaw('SUM(price * quantity)as total')
                 ->pluck('total');
               
      }

      $paid = DB::table('clients')
             ->join('payments','clients.id','=','payments.client_id')
             ->where('clients.id',$id) 
             ->where('payments.payment_date','<',date("Y-m-d", $date))   
             ->selectRaw('COALESCE(SUM(amount_paid),0) as due')
             ->pluck('due');

      if(($order-$paid) < 0){
        return 0;
      }else{
      return ($order-$paid);
      }
  }





public static function total($id){

    $client = Client::find($id);
    $order = 0;
    

          if($client->type == 'Customer'){
   $order = DB::table('erporders')
           ->join('erporderitems','erporders.id','=','erporderitems.erporder_id')
           ->join('clients','erporders.client_id','=','clients.id')           
           ->where('clients.id',$id) ->selectRaw('SUM(price * quantity)-COALESCE(SUM(discount_amount),0)- COALESCE(SUM(erporderitems.client_discount),0)  as total')
           ->pluck('total');
           }
            else{
    $order = DB::table('erporders')
           ->join('erporderitems','erporders.id','=','erporderitems.erporder_id')
           ->join('clients','erporders.client_id','=','clients.id')           
           ->where('clients.id',$id) ->selectRaw('SUM(price * quantity)as total')
           ->pluck('total');         
         }         

    
           return $order;

  }

  public static function payment($id){   
    $paid = DB::table('clients')
          ->join('payments','clients.id','=','payments.client_id')
          ->where('clients.id',$id) ->selectRaw('COALESCE(SUM(amount_paid),0) as due')
          ->pluck('due');                      
          
          return $paid;
  }


  /**
   * GET CLIENT MONTHLY SALES REPORT
   */
  public static function clientMonthlySales($id, $month, $days){
    //$date = "09-2016";
    $from = date('Y-m-d', strtotime('01-'.$month));
    $to = date('Y-m-d', strtotime($days.'-'.$month));

    $clientSalesTotal = DB::table('erporders')
                          ->join('erporderitems', 'erporders.id', '=', 'erporderitems.erporder_id')
                          ->join('items', 'erporderitems.item_id', '=', 'items.id')
                          ->join('clients', 'erporders.client_id', '=', 'clients.id')
                          ->where('erporders.client_id', $id)
                          ->where('erporders.type','=','sales')
                          ->where('erporders.status','!=','cancelled') 
                          ->whereBetween('erporders.date', array($from, $to))
                          ->selectRaw('COALESCE(SUM((erporderitems.price * erporderitems.quantity) - erporderitems.client_discount),0) as clientTotal')
                          ->pluck('clientTotal');

              return $clientSalesTotal;
  }


  /**
   * Cients Monthly Sales Report
   */
  public static function clientMonthlySalesSummary($id,$date){
    $month = substr($date, 0,2);
    $year = substr($date, 3,6);
    $clientSales = DB::table('erporders')
                      ->join('erporderitems', 'erporders.id', '=', 'erporderitems.erporder_id')
                      ->join('items', 'erporderitems.item_id', '=', 'items.id')
                      ->join('clients', 'erporders.client_id', '=', 'clients.id')
                      ->where('erporders.client_id', $id)
                      ->where('erporders.type','=','sales')
                      ->where('erporders.status','!=','cancelled')
                      ->whereMonth('erporders.date', '=', $month)
                      ->whereYear('erporders.date', '=', $year)
                      ->selectRaw('COALESCE(SUM((erporderitems.price * erporderitems.quantity) - erporderitems.client_discount),0) as orderTotal,
                          SUM(erporderitems.quantity) as qty, items.description as description, clients.name as client_name, clients.category as category')
                      //->selectRaw('erporders.*, erporderitems.*, items.*, clients.*')
                      ->first();

              return $clientSales;
  }


  /**
   * Cients Daily Sales Report
   */
  public static function clientDailySalesSummary($id){
    $clientSales = DB::table('erporders')
                      ->join('erporderitems', 'erporders.id', '=', 'erporderitems.erporder_id')
                      ->join('items', 'erporderitems.item_id', '=', 'items.id')
                      ->join('clients', 'erporders.client_id', '=', 'clients.id')
                      ->where('erporders.client_id', $id)
                      ->where('erporders.type','=','sales')
                      ->where('erporders.status','!=','cancelled')
                      ->where('erporders.date', date('Y-m-d'))
                      ->selectRaw('COALESCE(SUM((erporderitems.price * erporderitems.quantity) - erporderitems.client_discount),0) as orderTotal,
                          SUM(erporderitems.quantity) as qty, items.description as description, clients.name as client_name, clients.category as category')
                      //->selectRaw('erporders.*, erporderitems.*, items.*, clients.*')
                      ->first();

              return $clientSales;
  }

}