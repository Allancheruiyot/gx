<?php

class ClientsController extends \BaseController {

	/**
	 * Display a listing of clients
	 *
	 * @return Response
	 */
	public function index()
	{
		$clients = Client::all();

		if (! Entrust::can('view_client') ) // Checks the current user
        {
        return Redirect::to('dashboard')->with('notice', 'you do not have access to this resource. Contact your system admin');
        }else{

        Audit::logaudit('Clients', 'viewed clients', 'viewed clients in the system');

		return View::make('clients.index', compact('clients'));
	}
	}

	/**
	 * Show the form for creating a new client
	 *
	 * @return Response
	 */
	public function create()
	{
		$items = Item::all();
		if (! Entrust::can('create_client') ) // Checks the current user
        {
        return Redirect::to('dashboard')->with('notice', 'you do not have access to this resource. Contact your system admin');
        }else{
		return View::make('clients.create', compact('items'));
	}
	}

	/**
	 * Store a newly created client in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
		$validator = Validator::make($data = Input::all(), Client::$rules, Client::$messages);

		if ($validator->fails())
		{
			return Redirect::back()->withErrors($validator)->withInput();
		}

		$c = Client::orderBy("code","DESC")->first();

        $accno = 111111;
        if($c->code == null){
        $accno = 111111;
        }else{
        $accno = $c->code + 1;	
        }
		

		$client = new Client;

		$client->name = Input::get('name');
		$client->code = $accno;
		$client->date = date('Y-m-d');
		$client->contact_person = Input::get('cname');
		$client->email = Input::get('email_office');
		$client->contact_person_email = Input::get('email_personal');
		$client->contact_person_phone = Input::get('mobile_phone');
		$client->phone = Input::get('office_phone');
		$client->address = Input::get('address');
		$client->type = Input::get('type');
		$client->category = Input::get('category');
		$client->balance = Input::get('balance');
		$client->credit_limit = str_replace(',','',Input::get('credit_limit'));
		$client->credit_period = str_replace(',','',Input::get('credit_period'));
		/*$client->percentage_discount = Input::get('percentage_discount');*/
		$client->save();

        if(Input::get('balance') > 0 && Input::get('balance') != ""){
		$payment = new Payment;
        if(Input::get('type') === 'Customer'){
		$client = Client::findOrFail($client->id);
     	}else{
     	$client = Client::findOrFail($client->id);	
     	}
		if(Input::get('type') === 'Customer'){
		$payment->client_id = $client->id;
	    }else{
        $payment->client_id = $client->id;
	    }
		$payment->amount_paid = Input::get('balance');
		$payment->paymentmethod_id = 1;
		$payment->account_id = 1;
		$payment->prepared_by = Confide::user()->id;
		$payment->payment_date = date("Y-m-d");
		$prepared_by = Confide::user()->id;

		$payment->save();

		$id= $payment->id;

		$client = Client::findOrFail($client->id);
		$client->payment_id = $payment->id;
		$client->update();
 		
		if(Input::get('type') === 'Customer'){
			Account::where('id', Input::get('paymentmethod'))->increment('balance', Input::get('balance'));	
		} else{
			Account::where('id', Input::get('paymentmethod'))->decrement('balance', Input::get('balance'));
		}

		if (! Entrust::can('confirm_payments') ) // Checks the current user
        {

        $users = DB::table('roles')
		->join('assigned_roles', 'roles.id', '=', 'assigned_roles.role_id')
		->join('users', 'assigned_roles.user_id', '=', 'users.id')
		->join('permission_role', 'roles.id', '=', 'permission_role.role_id') 
		->select("users.id","email","username")
		->where("permission_id",29)->get();

		$key = md5(uniqid());

		

		foreach ($users as $user) {

        if(Input::get('type') === 'Customer'){
		Notification::notifyUser($user->id,"Hello, Approval to receive payment is required","payment","notificationshowpayment/".$prepared_by."/".$user->id."/".$key."/".$id,$key);
        }else{
        	Notification::notifyUser($user->id,"Hello, Approval for purchase payment is required","payment","notificationshowpayment/".$prepared_by."/".$user->id."/".$key."/".$id,$key);
        }
        }

        Audit::logaudit('Payments', 'created payment', 'created payment for client '.$client->name.', amount '.Input::get('balance').' but awaiting approval in the system');
        }else{

        $p = Payment::find($id);
        $p->confirmed_id = Confide::user()->id;
        $p->is_approved = 1;
        $p->update();

        Audit::logaudit('Payments', 'created payment', 'created payment for client '.$client->name.', amount '.Input::get('balance').' in the system');
        }
        }

		Audit::logaudit('Clients', 'created a client', 'created client '.Input::get('name').' in the system');

		return Redirect::route('clients.index')->withFlashMessage('Client successfully created!');
	}

	/**
	 * Display the specified client.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		$client = Client::findOrFail($id);

		if (! Entrust::can('view_client') ) // Checks the current user
        {
        return Redirect::to('dashboard')->with('notice', 'you do not have access to this resource. Contact your system admin');
        }else{

        Audit::logaudit('Clients', 'viewed a client details', 'viewed client details for client '.$client->name.' in the system');

		return View::make('clients.show', compact('client'));
	}
	}

	/**
	 * Show the form for editing the specified client.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		$client = Client::find($id);

		if (! Entrust::can('update_client') ) // Checks the current user
        {
        return Redirect::to('dashboard')->with('notice', 'you do not have access to this resource. Contact your system admin');
        }else{

		return View::make('clients.edit', compact('client'));
	}
	}

	/**
	 * Update the specified client in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		$client = Client::findOrFail($id);

		$validator = Validator::make($data = Input::all(), Client::rolesUpdate($client->id), Client::$messages);

		if ($validator->fails())
		{
			return Redirect::back()->withErrors($validator)->withInput();
		}

		$date=date('y-m-dh:i:s'); 

		$c = Client::orderBy("code","DESC")->first();

        $accno = 111111;
        if($c->code == null){
        $accno = 111111;
        }else{
        $accno = $c->code + 1;	
        }
		

		if($client->code == null || $client->code == ''){
          $client->code = $accno;
		}

		$client->name = Input::get('name');
		$client->contact_person = Input::get('cname');
		$client->email = Input::get('email_office');
		$client->contact_person_email = Input::get('email_personal');
		$client->contact_person_phone = Input::get('mobile_phone');
		$client->phone = Input::get('office_phone');
		$client->address = Input::get('address');
		$client->type = Input::get('type');
		$client->category = Input::get('category');
		$client->balance = Input::get('balance');
		$client->credit_limit = str_replace(',','',Input::get('credit_limit'));
		$client->credit_period = str_replace(',','',Input::get('credit_period'));
		/*$client->percentage_discount = Input::get('percentage_discount');*/
		// $client->save();

		$client->update();

		if(Input::get('balance') > 0 && Input::get('balance') != ""){
		if($client->payment_id == 0 || $client->payment_id == null){
		$payment = new Payment;
        if(Input::get('type') === 'Customer'){
		$client = Client::findOrFail($client->id);
     	}else{
     	$client = Client::findOrFail($client->id);	
     	}
		if(Input::get('type') === 'Customer'){
		$payment->client_id = $client->id;
	    }else{
        $payment->client_id = $client->id;
	    }
		$payment->amount_paid = Input::get('balance');
		$payment->paymentmethod_id = 1;
		$payment->account_id = 1;
		$payment->prepared_by = Confide::user()->id;
		$payment->payment_date = date("Y-m-d");
		$prepared_by = Confide::user()->id;

		$payment->save();

		$id= $payment->id;

		$client = Client::findOrFail($client->id);
		$client->payment_id = $payment->id;
		$client->update();
		
		if(Input::get('type') === 'Customer'){
			Account::where('id', Input::get('paymentmethod'))->increment('balance', Input::get('balance'));	
		} else{
			Account::where('id', Input::get('paymentmethod'))->decrement('balance', Input::get('balance'));
		}

		if (! Entrust::can('confirm_payments') ) // Checks the current user
        {

        $users = DB::table('roles')
		->join('assigned_roles', 'roles.id', '=', 'assigned_roles.role_id')
		->join('users', 'assigned_roles.user_id', '=', 'users.id')
		->join('permission_role', 'roles.id', '=', 'permission_role.role_id') 
		->select("users.id","email","username")
		->where("permission_id",29)->get();

		$key = md5(uniqid());

		

		foreach ($users as $user) {

        if(Input::get('type') === 'Customer'){
		Notification::notifyUser($user->id,"Hello, Approval to receive payment is required","payment","notificationshowpayment/".$prepared_by."/".$user->id."/".$key."/".$id,$key);
        }else{
        	Notification::notifyUser($user->id,"Hello, Approval for purchase payment is required","payment","notificationshowpayment/".$prepared_by."/".$user->id."/".$key."/".$id,$key);
        }
        }

        Audit::logaudit('Payments', 'created payment', 'created payment for client '.$client->name.', amount '.Input::get('balance').' but awaiting approval in the system');
        }else{

        $p = Payment::find($id);
        $p->confirmed_id = Confide::user()->id;
        $p->is_approved = 1;
        $p->update();

        Audit::logaudit('Payments', 'created payment', 'created payment for client '.$client->name.', amount '.Input::get('balance').' in the system');
        }
        }
        }

		Audit::logaudit('Clients', 'updated a client', 'updated client '.Input::get('name').' in the system');

		return Redirect::route('clients.index')->withFlashMessage('Client successfully updated!');
	}

	/**
	 * Remove the specified client from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{

        if (! Entrust::can('delete_client') ) // Checks the current user
        {
        return Redirect::to('dashboard')->with('notice', 'you do not have access to this resource. Contact your system admin');
        }else{
        $client = Client::find($id);
        Client::destroy($id);
        Audit::logaudit('Clients', 'deleted a client', 'deleted client '.$client->name.' from the system');
		return Redirect::route('clients.index')->withDeleteMessage('Client successfully deleted!');
	}
	}

}
