<?php
	
	const FIXED_AMOUNT = 0;
	const PERCENTAGE = 1;
	
// Controls the viewer page for models
class Basket extends CI_Controller {

  public function __construct() {
    parent::__construct();
	
	//$this->load->library('paypal_lib');
	$this->load->library('braintree_lib');	

    // Load helpers
    $this->load->helper('url_helper');
	$this->load->helper('MY_user_helper');

    // Interface to work with users in the DB
    $this->load->model('DatabaseInterface','',TRUE);
    $this->load->model('BasketInterface','',TRUE);
	$this->load->model('UserInterface','',TRUE);

    $this->load->library('twig', [
      'paths' => [
        APPPATH . 'views/pages/twig',
        APPPATH . 'views/templates/twig',
        'assets/views/templates'
      ],
      'functions' => ['lang', 'uri_string']
    ]);

    $twig = $this->twig->getTwig();
    $twig->addGlobal('session', $this->session);
    $twig->addGlobal('active', ACTIVE);
    $twig->addGlobal('inactive', INACTIVE);
	
	$CI = &get_instance();
	$CI->config->load('braintree', TRUE);
	$braintree = $CI->config->item('braintree');
	
	// if ( $braintree['braintree_enabled'] == false )
	// 	show_404();
  }

  public function index() {	
      $session = $this->session->userdata('logged_in');
	  $userId = $session['id'];
      if ($userId == null) {
        // correct error should be shown here -> user not logged in
        redirect('account/login','refresh');
      }
	  $data['isAdministrator'] = $this->UserInterface->isAdministrator(getLoggedInUserId());
      $basketOrders = $this->BasketInterface->get_basket($userId);
      $data['basketOrders'] = $basketOrders;
	  $data['numberOfOrdersUser'] = $this->BasketInterface->getNumberOfOrders($userId);
	  $data['numberOfOrders'] = $this->BasketInterface->getNumberOfOrders();		  
	  $data['baseUrl'] = $this->config->base_url();
	  $data['userID'] = $userId;
	  $data['deliveryTypes'] = $this->BasketInterface->getDeliveryTypes();	 
	  $data['deliveryCosts'] = $this->BasketInterface->getDeliveryCosts();	  
		  
	  // group all data in an object
	  $dataObj = [];
	  $dataObj['data'] = $data;
	  
			
	  if ($data["deliveryTypes"])
		 $this->session->set_userdata('deliveryMode', $data["deliveryTypes"][0]->delivery_type_id);
	  else
		  $this->session->unset_userdata('deliveryMode');
	  $this->session->unset_userdata('promo');
	  $pendingPurchases = $this->BasketInterface->userPendingPurchases($userId);
	  $this->session->set_userdata('pending_purchase', $pendingPurchases);
	  $this->twig->display('basket', $dataObj); 
	  
  }

  public function updateCosts(){
	  $session = $this->session->userdata('logged_in');
	  $userId = $session['id'];
      if ($userId == null) {        
        redirect('account/login','refresh');
      }	 
      $basketOrders = $this->BasketInterface->get_basket($userId);
      $data['basketOrders'] = $basketOrders;		
	  if ($this->input->post('code')){					
			$code = $this->input->post('code');				
			$totalPurchase =$this->input->post('purchase');
			$discount = $this->BasketInterface->getDiscount($code, $totalPurchase);		
			if (!is_string($discount)){
				$data['discount'] = $discount;			
				$this->session->set_userdata('promo', $discount);
			}			
			else{
				$data['discount_error'] = $discount;
				$this->session->set_userdata('promo', []);
			}			
	  }
	  if ($this->input->post('deliveryMode')){
			$deliveryMode =  $this->input->post('deliveryMode');
			$this->session->set_userdata('deliveryMode', $deliveryMode);			
			$data['deliveryCosts'] = $this->BasketInterface->getDeliveryCosts($deliveryMode);			
		}		
		$dataObj = [];
		$dataObj['data'] = $data;

		$this->twig->display('basket-tmpl', $dataObj);	  
  }



  public function editorder($orderitemid) {

    // TODO: change this to be in a common pre-controller
    $data['description'] = 'basket - edit order';
    $data['title'] = '3DKumo - ';
    $data['keywords'] = '';

    $session = $this->session->userdata('logged_in');
    $userId = $session['id'];
    if ($userId == null) {
      // correct error should be shown here -> user not logged in
      redirect('account/login','refresh');
    }

    $basketOrder = $this->BasketInterface->get_basket_order($userId, $orderitemid);
    $data['basketOrder'] = $basketOrder;
    if ($basketOrder == null) {
      // correct error should be shown here -> order not found
      redirect('basket','refresh');
    }

    $storeModelTypes = $this->BasketInterface->get_model_types($basketOrder['file_id']);
    $data['modelTypes'] = $storeModelTypes;

    // group all data in an object
    $dataObj = [];
    $dataObj['data'] = $data;

    // we pass the basketOrder as a parameter -> go into 'edit order' mode
    $this->twig->display('order', $dataObj);

  }
  
  public function orders($userId = null){
	$session = $this->session->userdata('logged_in');
	$merchantId = "";
	$braintreeUrl = "";
	if ($userId == null){
		if (!(isUserLoggedIn() && $this->UserInterface->isAdministrator(getLoggedInUserId())))
			redirect('account/login','refresh');
		else{
			$CI = &get_instance();
			$CI->config->load('braintree', TRUE);
			$braintree = $CI->config->item('braintree');
			$merchantId = $braintree['braintree_merchant_id'];
			$braintreeUrl = ( $braintree['braintree_environment'] == 'sandbox')? 'https://sandbox.braintreegateway.com/': 'https://www.braintreegateway.com/';			
		}
	}
	else{
		if (!(isUserLoggedIn() && ($this->UserInterface->isAdministrator(getLoggedInUserId()) || $userId == getLoggedInUserId() )))
			redirect('account/login','refresh'); 
	}   
	$data['isAdministrator'] = $this->UserInterface->isAdministrator(getLoggedInUserId());
    $orders = $this->BasketInterface->getOrders(null, $userId);	
    $data['orders'] = $orders;	    
	$data['baseUrl'] = $this->config->base_url();
	if ( $userId )
		$data['orderItems'] = $this->BasketInterface->getNumberOfOrderItems($userId);
	$data['allUsers'] = ($userId == null)? "true" : "false";
	if ($merchantId)
		$data['merchantId'] = $merchantId;
	if ($braintreeUrl)
		$data['braintree_url'] = $braintreeUrl;
	
    // group all data in an object
    $dataObj = [];
    $dataObj['data'] = $data;
    
    $this->twig->display('orders', $dataObj);
	  
  }

	public function buy(){
		$session = $this->session->userdata('logged_in');
		$userId = $session['id'];
		if ($userId == null) {
		  show_404();
		}
		$basketOrderId = $this->BasketInterface->get_orderId($userId);
		if ($basketOrderId  == null )
			show_404();
				
        //Set variables for paypal form
        $returnURL = $this->config->base_url().'basket/success'; //payment success url
        $cancelURL = $this->config->base_url().'basket/cancel'; //payment cancel url
		$notifyURL = $this->config->base_url().'basket/ipn'; //ipn url		
        $products = [];
		$products = $this->BasketInterface->getOrders($basketOrderId);	
		$custom = $basketOrderId;	
        $logo = $this->config->base_url().'assets/images/3dkumologo.png';
        
        $this->paypal_lib->add_field('return', $returnURL);
        $this->paypal_lib->add_field('cancel_return', $cancelURL);
		$this->paypal_lib->add_field('notify_url', $notifyURL);       
		$this->paypal_lib->add_field('custom', $custom); 		  
		$this->paypal_lib->add_field('upload', "1");
		$i = 1;
		$productsPrice = 0;
		if ($products){
			foreach ($products as $product) {		
				$this->paypal_lib->add_field('item_name_'.$i, 'Name: ' .  $product['name'] . '(id:' . $product['file_id'] .') , Material: ' . $product['material_description'] . ', Size: ' . $product['size_description']  );
				$this->paypal_lib->add_field('item_number_'.$i,  $product['order_item_id']);
				$this->paypal_lib->add_field('amount_'.$i, $product['price']/$product['quantity']);
				$this->paypal_lib->add_field('quantity_'.$i, $product['quantity']);
				$productsPrice += $product['price'];
				$i++;
			}
		}
		// $this->paypal_lib->image($logo);
		$this->paypal_lib->add_field('tax_rate', 21); // VAT 21%				
		$deliveryMode = $this->session->userdata('deliveryMode');
		$deliveryCosts = $this->BasketInterface->getDeliveryCosts($deliveryMode);		
		$discount = $this->session->userdata('promo');		
		if ($discount){
			if ($discount->discount_type == FIXED_AMOUNT)
				$discount_amount = $discount->value;
			else //percentage
				$discount_amount = ($productsPrice + $deliveryCosts )*$discount->value/100;
			$this->paypal_lib->add_field('discount_amount_cart', $discount_amount);	
		}
		//$this->paypal_lib->add_field('shipping', $deliveryCosts);
		$this->paypal_lib->add_field('item_name_'.$i,  'Delivery costs');
		$this->paypal_lib->add_field('amount_'.$i, $deliveryCosts);		
				
		$this->paypal_lib->paypal_auto_form();
    }	

	public function setDeliveryId() {
		$order_id = $this->input->post('order_id');		
		$delivery_id = $this->input->post('order_id');
		$response = null;

		if (isUserLoggedIn()) {
		  $userId = getLoggedInUserId();
		  if (! $this->UserInterface->isAdministrator($userId)) {
			$response['error'] = 'user is not owner of the model';
		  } else {		  
			$this->BasketInterface->setDeliveryId($order_id, $delivery_id);	   
			$response = [];
		  }
		} else {
		  $response['error'] = 'User needs to be logged in to change the status';
		}

		$this->output->set_content_type('application/json');
		$this->output->set_output(json_encode($response));
		
	}
	
	public function promocodes() {
		// TODO: change this to be in a common pre-controller
		$data['description'] = 'edit Model description';
		$data['title'] = '3DKumo edit model';
		$data['keywords'] = '3dkumo edit model';   
		$data['baseUrl'] = $this->config->base_url();

		// check if we are administrator
		if (!$this->UserInterface->isAdministrator(getLoggedInUserId()))
		  redirect('listlibrary', 'refresh');
		
		$data['isAdministrator'] = $this->UserInterface->isAdministrator(getLoggedInUserId());
		$data['promocodes']= $this->BasketInterface->getPromoCodes();
		
		// group all data in one object
		$dataObj = [];
		$dataObj['data'] = $data;

		$this->twig->display('promocodes', $dataObj);
  }
  
  public function editcodes($promotional_code_id){
	  // TODO: change this to be in a common pre-controller
    $data['description'] = 'edit Model description';
    $data['title'] = '3DKumo edit model';
    $data['keywords'] = '3dkumo edit model';   
    $data['baseUrl'] = $this->config->base_url();

    // check if we are administrator
    if (!$this->UserInterface->isAdministrator(getLoggedInUserId()))
      redirect('listlibrary', 'refresh');

    $data['promocode']= $this->BasketInterface->getPromoCodes($promotional_code_id);
    $data['isAdministrator'] = $this->UserInterface->isAdministrator(getLoggedInUserId());
    // group all data in one object
    $dataObj = [];
    $dataObj['data'] = $data;

    $this->twig->display('promocodeedit', $dataObj);  
	  
  }
  
  public function newPromocode (){
	  if (isUserLoggedIn() &&  ($this->UserInterface->isAdministrator(getLoggedInUserId()))) {		 
			$promotional_code_id = $this->BasketInterface->addPromotionalCode();       
			redirect('promocodes/edit/' . $promotional_code_id, 'refresh');	 	  
		}
		else
		  redirect('listlibrary', 'refresh');	  
  }
  
  public function setStartDate() {
    $promocodeId = $this->input->post('promocodeId');
    $startDate = $this->input->post('startdate');

    $response = null;

    if (isUserLoggedIn()) {
      $userId = getLoggedInUserId();
      if (!$this->UserInterface->isAdministrator($userId)) {
        $response['error'] = 'user is not administrator';
      } else {
        $this->BasketInterface->setStartDate($promocodeId, $startDate);
        $response = [];
      }
    } else {
      $response['error'] = 'User needs to be logged in to change the status';
    }

    $this->output->set_content_type('application/json');
    $this->output->set_output(json_encode($response));
  }
  
  public function setEndDate() {
    $promocodeId = $this->input->post('promocodeId');
    $endDate = $this->input->post('enddate');

    $response = null;

    if (isUserLoggedIn()) {
      $userId = getLoggedInUserId();
      if (!$this->UserInterface->isAdministrator($userId)) {
        $response['error'] = 'user is not administrator';
      } else {
        $this->BasketInterface->setEndDate($promocodeId, $endDate);
        $response = [];
      }
    } else {
      $response['error'] = 'User needs to be logged in to change the status';
    }

    $this->output->set_content_type('application/json');
    $this->output->set_output(json_encode($response));
  }
  
  public function setMinimumPurchase() {
    $promocodeId = $this->input->post('promocodeId');
    $minimumPurchase = $this->input->post('minimumpurchase');

    $response = null;

    if (isUserLoggedIn()) {
      $userId = getLoggedInUserId();
      if (!$this->UserInterface->isAdministrator($userId)) {
        $response['error'] = 'user is not administrator';
      } else {
        $this->BasketInterface->setMinimumPurchase($promocodeId, $minimumPurchase);
        $response = [];
      }
    } else {
      $response['error'] = 'User needs to be logged in to change the status';
    }

    $this->output->set_content_type('application/json');
    $this->output->set_output(json_encode($response));
  }
  
  public function setDiscountType() {
    $promocodeId = $this->input->post('promocodeId');
    $discountType = $this->input->post('discounttype');

    $response = null;

    if (isUserLoggedIn()) {
      $userId = getLoggedInUserId();
      if (!$this->UserInterface->isAdministrator($userId)) {
        $response['error'] = 'user is not administrator';
      } else {
        if (!$this->BasketInterface->setDiscountType($promocodeId, $discountType))
			$response['error'] = "It is not possible to modify discount type because the code is already in use.";
		else
			$response = [];
      }
    } else {
      $response['error'] = 'User needs to be logged in to change the status';
    }	
    $this->output->set_content_type('application/json');
    $this->output->set_output(json_encode($response));
  }
  
  public function setDiscountValue() {
    $promocodeId = $this->input->post('promocodeId');
    $discountValue = $this->input->post('discountvalue');

    $response = null;

    if (isUserLoggedIn()) {
      $userId = getLoggedInUserId();
      if (!$this->UserInterface->isAdministrator($userId)) {
        $response['error'] = 'user is not administrator';
      } else {
        if (!$this->BasketInterface->setDiscountValue($promocodeId, $discountValue))
			$response['error'] = "It is not possible to modify discount value because the code is already in use.";
		else
			$response = [];
      }
    } else {
      $response['error'] = 'User needs to be logged in to change the status';
    }

    $this->output->set_content_type('application/json');
    $this->output->set_output(json_encode($response));
  } 
  
  public function setPromoCode() {
    $promocodeId = $this->input->post('promocodeId');
    $promoCode = $this->input->post('promocode');

    $response = null;

    if (isUserLoggedIn()) {
      $userId = getLoggedInUserId();
      if (!$this->UserInterface->isAdministrator($userId)) {
        $response['error'] = 'user is not administrator';
      } else {
        if (!$this->BasketInterface->setPromoCode($promocodeId, $promoCode))
			$response['error'] = "It is not possible to modify code because the code is already in use.";
		else
			$response = [];
      }
    } else {
      $response['error'] = 'User needs to be logged in to change the status';
    }

    $this->output->set_content_type('application/json');
    $this->output->set_output(json_encode($response));
  }
  
  public function deleteCode() {
   
	$response = [];
    // check if the user is allowed (administrator)
    if (isUserLoggedIn()) {
		$data['user'] = ['id' => getLoggedInUserId(), 'username' => getLoggedInUserUsername()];
		// if administrator    
      if ($this->UserInterface->isAdministrator($data['user']['id'])) {
		  $promocodeId = $this->input->post('promocodeId');
		  if(!$this->BasketInterface->deleteCode($promocodeId))
			  $response['error'] = "It is not possible to delete the code because it is already in use.";
		  else
			  $response = [];
	  }
	  else {
        $response = ['error' => 'user not allowed'];
      }		  
	}
    else {
      $response = ['error' => 'user not logged in'];
    }
    $this->output->set_content_type('application/json');
    $this->output->set_output(json_encode($response));
    return ;	
  }
  
  
  public function payment(){
	 $session = $this->session->userdata('logged_in');
	 $userId = $session['id'];
     if ($userId == null) {       
		redirect('account/login','refresh');
      }	  
	
      $dbcountries = $this->DatabaseInterface->get_country_list();
      $countrylist = array();
      if ($dbcountries) {
		foreach ($dbcountries as $country) {
           $countrylist[] = ['country_id' => $country["country_id"], 'country_name' => $country["name"]];
        }
      }		
	  $order_info = $this->calculate_order_info(); 
      if ($order_info['total'] == 0)
		redirect('basket/index','refresh');
	  else{
		$data['total'] = $order_info['total'];		
		$data['countrylist'] = $countrylist;
		$data['userId'] = getLoggedInUserId();
		$dataObj['data'] = $data;			
		}		 
		$this->twig->display('payment', $dataObj);			

	}
	
	public function checkout(){		
		$session = $this->session->userdata('logged_in');
		$userId = $session['id'];
		if ($userId == null) {
			show_404();
		}		
		$nonceFromTheClient = $this->input->post('paymentMethodNonce');
		$firstName = $this->input->post('firstName');
		$lastName = $this->input->post('lastName');
		$phone = $this->input->post('phone');
		$company = $this->input->post('company');
		$streetAddress = $this->input->post('streetAddress');
		$extendedAddress = $this->input->post('extendedAddress');
		$locality = $this->input->post('locality');
		$region = $this->input->post('region');
		$postalCode = $this->input->post('postalCode');
		$countryName = $this->input->post('countryName');
		$email = $this->input->post('email');
		$promocodeId = $this->input->post('promocodeId');
		$discountType = $this->input->post('discounttype');

		$order_info = $this->calculate_order_info();
			
		$basketOrderId = $this->BasketInterface->get_orderId($userId);
		if ($basketOrderId  == null )
			show_404();
		$products = $this->BasketInterface->getOrders($basketOrderId);
		
		$items="";
		if ($products){
			$i = 1;
			foreach ($products as $product) {				
				$items .= $product['order_item_id'] . ' - ';
				$i++;
			}
		}		
		$result = Braintree_Transaction::sale([
		  'amount' => $order_info['total'],		 
		  'taxAmount' => $order_info['tax'],
		  'orderId' => $order_info['orderId'],		 
		  'paymentMethodNonce' => $nonceFromTheClient,
		  'customer' => [
			'firstName' => $firstName,
			'lastName' => $lastName,
			'company' => $company,
			'phone' => $phone,			
			'email' => $email
		  ],
		  'billing' => [
			'firstName' => $firstName,
			'lastName' => $lastName,
			'company' => $company,
			'streetAddress' => $streetAddress,
			'extendedAddress' => $extendedAddress,
			'locality' => $locality,
			'region' => $region,
			'postalCode' => $postalCode,
			'countryCodeAlpha2' => $countryName
		  ],
		  'shipping' => [
			'firstName' => $firstName,
			'lastName' => $lastName,
			'company' => $company,
			'streetAddress' => $streetAddress,
			'extendedAddress' => $extendedAddress,
			'locality' => $locality,
			'region' => $region,
			'postalCode' => $postalCode,
			'countryCodeAlpha2' => $countryName
		  ],
		  'options' => [
			'submitForSettlement' => true
		  ],
		  'customFields' => [
			'itemid' => $items,
			'itemsprice' => $order_info['productsPrice'] . ' €',
			'promoid' => $order_info['discountId'] . '-->' . $order_info['discount'] . ' €',
			'deliveryid' => $order_info['deliveryId'] . '-->' . $order_info['delivery'] . ' €'
		  ]	
		]);

		$this->output->set_content_type('application/json');
		$this->output->set_output(json_encode($result));
		return ;				
	}
	
	public function update_order(){		
		$session = $this->session->userdata('logged_in');
		$userId = $session['id'];		
		if ($userId == null) {
		  show_404();
		}
        $data = [];
		$txn_id = $this->input->post('txn_id');
		$payment_amt = $this->input->post('payment_amt');
        $data['txn_id'] = $txn_id;
        $data['payment_amt'] = $payment_amt;		
		$data['userId'] = $userId;	
		$this->BasketInterface->pay_basket($userId, $data);	
		$dataObj['data'] = $data; 
		$pendingPurchases = $this->BasketInterface->userPendingPurchases($userId);
		$this->session->set_userdata('pending_purchase', $pendingPurchases);
		return;			
	}
	
	public function payment_message(){
		$session = $this->session->userdata('logged_in');
		$userId = $session['id'];		
		if ($userId == null) 
		  show_404();
		
		$data['userId'] = $userId;
		$dataObj['data'] = $data;
		$this->twig->display('paymentmessage', $dataObj);		
	}
	
	public function calculate_order_info(){
		$session = $this->session->userdata('logged_in');
		$userId = $session['id'];
		if ($userId == null) {
		  show_404();
		}
		$basketOrderId = $this->BasketInterface->get_orderId($userId);
		if ($basketOrderId  == null )
			show_404();
		$i = 1;
		$productsPrice = 0;
		$products = [];
		$products = $this->BasketInterface->getOrders($basketOrderId);
		if ($products){
			foreach ($products as $product) {				
				$productsPrice += $product['price'];
				$i++;
			}
		}				
		$deliveryMode = $this->session->userdata('deliveryMode');
		$deliveryCosts = $this->BasketInterface->getDeliveryCosts($deliveryMode);		
		$discount = $this->session->userdata('promo');
		$discount_amount = 0;
		if ($discount){
			if ($discount->discount_type == FIXED_AMOUNT)
				$discount_amount = $discount->value;
			else //percentage
				$discount_amount = ($productsPrice + $deliveryCosts )*$discount->value/100;			
		}
				
		$amount = $productsPrice + $deliveryCosts  - $discount_amount;
		$tax_rate = $amount*21/100; //VAT 21%
		$amount = $amount + $tax_rate;
		
		$order_info['total'] = round($amount, 2);
		$order_info['productsPrice'] = $productsPrice;
		$order_info['discountId'] = ($discount)? $discount->promotional_code_id: "";
		$order_info['discount'] = ($discount)? round($discount_amount, 2) : 0;
		$order_info['deliveryId'] = $deliveryMode;
		$order_info['delivery'] = round($deliveryCosts,2);
		$order_info['tax'] = round($tax_rate, 2);
		$order_info['orderId'] = $basketOrderId;
		
		return $order_info;
	}
	
	//Paypal
   public function success(){
        //get the transaction data		
        $paypalInfo = $this->input->get();
        $data = [];
		$data['success']	= true;
        $data['txn_id'] = $paypalInfo["tx"];
        $data['payment_amt'] = $paypalInfo["amt"];
        $data['currency_code'] = $paypalInfo["cc"];
        $data['status'] = $paypalInfo["st"];      
		$session = $this->session->userdata('logged_in');
		$userId = $session['id'];		
		if ($userId == null) {
		  show_404();
		}	
		$data['userId'] = $userId;
		$data['baseUrl'] = $this->config->base_url();
		$this->BasketInterface->pay_basket($userId, $data);	
		$dataObj['data'] = $data; 
		$this->twig->display('paymentmessage', $dataObj);
     }
     
   public function cancel(){	   
		$data['success'] = false;
		$dataObj['data'] = $data;
		$this->twig->display('paymentmessage', $dataObj);
     }

}