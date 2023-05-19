<?php
defined('BASEPATH') or exit('No direct script access allowed');

// Model to handle actions for a 3d model
// ---------------------------------

class BasketInterface extends CI_Model
{

  function __construct()
  {
    parent::__construct();
    $this->load->database();

  }

  function get_basket($userid)
  {
    $this->db->select('order_item_id, oi.order_id, oi.file_id, f.name, oi.material_id, oi.size_id, oi.price,oi.quantity, delivery_id, material_description, size_description, smt.image, oi.creation_date');
    $this->db->from('orders o');
    $this->db->join('order_items oi', 'oi.order_id = o.order_id', 'inner');
    $this->db->join('file f', 'f.file_id = oi.file_id', 'left');
    $this->db->join('store_model_material smm', 'smm.material_id = oi.material_id', 'left');
    $this->db->join('store_model_size sms', 'sms.size_id = oi.size_id', 'left');
    $this->db->join('store_model_types smt', 'smt.file_id = oi.file_id and smt.size_id = oi.size_id and smt.material_id = oi.material_id', 'left');
    $this->db->where('purchase_date', null);
    $this->db->where('o.user_id', $userid);

    $dbQuery = $this->db->get();
    $totalResults = $dbQuery->num_rows();

    if ($totalResults == 0) {
      return [];
    } else {
      $basketOrders = $this->processArray($dbQuery);
      return $basketOrders;
    }
  }

  function pay_basket($userid, $paymentInfo = null)
  {
    date_default_timezone_set("Europe/London");
    $date = date('Y-m-d H:i:s', time());
    // loop through the orders and update
    $this->db->set('purchase_date', $date);
    $this->db->set('transaction_id', $paymentInfo['txn_id']);
    $this->db->set('amount_paid', $paymentInfo['payment_amt']);
    if ($this->session->userdata('promo'))
      $this->db->set('promotional_code_id', $this->session->userdata('promo')->promotional_code_id);
    if ($this->session->userdata('deliveryMode'))
      $this->db->set('delivery_type_id', $this->session->userdata('deliveryMode'));
    $this->db->where('user_id', $userid);
    $this->db->where('purchase_date', null);
    $this->db->update('orders');
    return $this->db->affected_rows();
  }

  function get_basket_order($userid, $orderitemid)
  {
    // loop through the orders and update
    $this->db->select('order_item_id, oi.order_id, oi.file_id, f.name, oi.material_id, oi.size_id, oi.price, oi.quantity, delivery_id, material_description, size_description, smt.image, oi.creation_date');
    $this->db->from('orders o');
    $this->db->join('order_items oi', 'oi.order_id = o.order_id', 'inner');
    $this->db->join('file f', 'f.file_id = oi.file_id', 'left');
    $this->db->join('store_model_material smm', 'smm.material_id = oi.material_id', 'left');
    $this->db->join('store_model_size sms', 'sms.size_id = oi.size_id', 'left');
    $this->db->join('store_model_types smt', 'smt.file_id = oi.file_id and smt.size_id = oi.size_id and smt.material_id = oi.material_id', 'left');
    $this->db->where('purchase_date', null);
    $this->db->where('o.user_id', $userid);
    $this->db->where('order_item_id', $orderitemid);

    $dbQuery = $this->db->get();
    $totalResults = $dbQuery->num_rows();

    if ($totalResults == 0) {
      return null;
    } else {
      $basketOrder = $this->processRow($dbQuery->row_array());
      return $basketOrder;
    }
  }

  function get_orderId($userid)
  {
    $this->db->distinct();
    $this->db->select('order_id');
    $this->db->from('orders');
    $this->db->where('purchase_date', null);
    $this->db->where('user_id', $userid);
    $dbQuery = $this->db->get();
    $num_rows = $dbQuery->num_rows();
    if ($num_rows == 1) {
      return $dbQuery->row()->order_id;
    } else {
      return null;
    }
  }

  function getOrders($order_id = null, $userId = null)
  {
    $this->db->select('oi.order_item_id, oi.order_id, oi.file_id , o.user_id, f.name, smm.material_description,sms.size_description , oi.price, oi.update_date, o.delivery_id, o.transaction_id, oi.quantity, p.discount_type, p.value, d.price as delivery_price, purchase_date, amount_paid, smt.image');
    $this->db->from('order_items oi');
    $this->db->join('orders o ', 'oi.order_id = o.order_id', 'inner');
    $this->db->join('file f', 'f.file_id = oi.file_id', 'left');
    $this->db->join('store_model_material smm', 'smm.material_id = oi.material_id', 'left');
    $this->db->join('store_model_size sms', 'sms.size_id = oi.size_id', 'left');
    $this->db->join('delivery_type d ', 'd.delivery_type_id = o.delivery_type_id', 'left');
    $this->db->join('promotional_codes p ', 'p.promotional_code_id = o.promotional_code_id', 'left');
    $this->db->join('store_model_types smt', 'oi.file_id = smt.file_id and oi.material_id = smt.material_id and oi.size_id = smt.size_id ', 'left');
    if ($order_id) {
      $this->db->where('o.order_id', $order_id);
    } else {
      $this->db->where('purchase_date is not null');
      $this->db->order_by('purchase_date', 'desc');
    }
    if ($userId) {
      $this->db->where('o.user_id', $userId);
    }
    $dbQuery = $this->db->get();
    $totalResults = $dbQuery->num_rows();

    if ($totalResults == 0) {
      return null;
    } else {
      return $dbQuery->result_array();
      ;
    }
  }

  function get_model_types($modelid)
  {
    $this->db->select('file_id, smt.size_id, smt.material_id, price, image, smm.material_description, sms.size_description, material_image');
    $this->db->from('store_model_types smt');
    $this->db->join('store_model_material smm', 'smm.material_id = smt.material_id', 'left');
    $this->db->join('store_model_size sms', 'sms.size_id = smt.size_id', 'left');
    $this->db->where('file_id', $modelid);

    $dbQuery = $this->db->get();
    $totalResults = $dbQuery->num_rows();

    if ($totalResults == 0) {
      return [];
    } else {
      $storeModelTypes = $this->processArray($dbQuery);
      return $storeModelTypes;
    }
  }

  function processRow($row)
  {
    $newBasketOrder = $row;
    $newBasketOrder["image"] = storedata_model_types_url($row["image"]);
    return $newBasketOrder;
  }

  function processArray($query)
  {
    $basketOrders = [];

    foreach ($query->result_array() as $index => $basketOrder) {
      $basketOrders[] = $this->processRow($basketOrder);
    }

    return $basketOrders;
  }

  function setDeliveryId($order_id, $delivery_id)
  {
    $data = array('delivery_id' => $delivery_id);
    $this->db->where('order_id', $order_id);
    $result = $this->db->update('orders', $data);
    return $result;
  }

  function getDiscount($code, $totalPurchase)
  {
    $this->db->select('*');
    $this->db->from('promotional_codes');
    $this->db->where('code', $code);
    $dbQuery = $this->db->get();

    if ($dbQuery->num_rows() == 0)
      return "Invalid code";
    else
      $codeRow = $dbQuery->row_array();

    if (date('Y-m-d H:i:s') > $codeRow["end_date"])
      return "The code has already expired";
    elseif (date('Y-m-d H:i:s') < $codeRow["start_date"])
      return "Code not yet activated";
    elseif ($totalPurchase < $codeRow["minimum_purchase"])
      return "The minimum purchase must be greater than " . $codeRow["minimum_purchase"] . " euro";
    else
      return $dbQuery->row();
  }

  function getDeliveryCosts($deliveryType = null)
  {
    $this->db->select('price');
    $this->db->from('delivery_type');
    if ($deliveryType)
      $this->db->where('delivery_type_id', $deliveryType);
    else {
      $this->db->where('enabled', 1);
      $this->db->order_by('price asc');
    }

    $dbQuery = $this->db->get();
    if ($dbQuery->num_rows() == 0)
      return false;
    else
      return $dbQuery->row()->price;

  }

  function getDeliveryTypes()
  {
    $this->db->select('delivery_type_id, description, price');
    $this->db->from('delivery_type');
    $this->db->where('enabled', 1);
    $this->db->order_by('price asc');
    $dbQuery = $this->db->get();
    if ($dbQuery->num_rows() == 0)
      return false;
    else
      return $dbQuery->result();
  }

  function getNumberOfOrders($userId = null)
  {
    $this->db->select('count(*) as count');
    $this->db->from('orders');
    if ($userId)
      $this->db->where('user_id', $userId);
    $this->db->where('purchase_date is not null');
    $dbQuery = $this->db->get();
    if ($dbQuery->num_rows() == 0)
      return 0;
    else
      return $dbQuery->row()->count;
  }

  function getNumberOfOrderItems($userId)
  {
    $this->db->select('count(*) as count');
    $this->db->from('order_items oi');
    $this->db->join('orders o', 'oi.order_id = o.order_id', 'inner');
    $this->db->where('user_id', $userId);
    $this->db->where('purchase_date is null');
    $dbQuery = $this->db->get();
    if ($dbQuery->num_rows() == 0)
      return 0;
    else
      return $dbQuery->row()->count;
  }

  function getPromoCodes($promotional_code_id = null)
  {
    $this->db->select('*');
    $this->db->from('promotional_codes');
    if ($promotional_code_id)
      $this->db->where('promotional_code_id', $promotional_code_id);
    $dbQuery = $this->db->get();
    if ($dbQuery->num_rows() == 0)
      return false;
    else
      if ($promotional_code_id)
        return $dbQuery->row();
      else
        return $dbQuery->result();
  }

  function addPromotionalCode()
  {
    $data = [];
    $data = array(
      'code' => "NEWCODE"
    );
    $this->db->insert('promotional_codes', $data);
    if ($this->db->affected_rows())
      return $this->db->insert_id();
    else
      return false;
  }

  function setStartDate($promocodeId, $startDate)
  {
    $data = array('start_date' => urldecode($startDate));
    $this->db->where('promotional_code_id', $promocodeId);
    $result = $this->db->update('promotional_codes', $data);
    return $result;
  }

  function setEndDate($promocodeId, $endDate)
  {
    $data = array('end_date' => urldecode($endDate));
    $this->db->where('promotional_code_id', $promocodeId);
    $result = $this->db->update('promotional_codes', $data);
    return $result;
  }

  function setMinimumPurchase($promocodeId, $minimumPurchase)
  {
    $data = array('minimum_purchase' => urldecode($minimumPurchase));
    $this->db->where('promotional_code_id', $promocodeId);
    $result = $this->db->update('promotional_codes', $data);
    return $result;
  }

  function setDiscountType($promocodeId, $discountType)
  {
    //check if the code is already used
    $codeUsed = $this->isCodeUsed($promocodeId);
    if ($codeUsed == 1)
      return false;
    else {
      $data = array('discount_type' => urldecode($discountType));
      $this->db->where('promotional_code_id', $promocodeId);
      $result = $this->db->update('promotional_codes', $data);
      return $result;
    }
  }

  function setDiscountValue($promocodeId, $discountValue)
  {
    //check if the code is already used
    $codeUsed = $this->isCodeUsed($promocodeId);
    if ($codeUsed == 1)
      return false;
    else {
      $data = array('value' => urldecode($discountValue));
      $this->db->where('promotional_code_id', $promocodeId);
      $result = $this->db->update('promotional_codes', $data);
      return $result;
    }
  }

  function setPromoCode($promocodeId, $promoCode)
  {
    //check if the code is already used
    $codeUsed = $this->isCodeUsed($promocodeId);
    if ($codeUsed == 1)
      return false;
    else {
      $data = array('code' => urldecode($promoCode));
      $this->db->where('promotional_code_id', $promocodeId);
      $result = $this->db->update('promotional_codes', $data);
      return $result;
    }
  }

  function deleteCode($promocodeId)
  {
    //check if the code is already used
    $codeUsed = $this->isCodeUsed($promocodeId);
    if ($codeUsed == 1)
      return false;
    else {
      $this->db->where('promotional_code_id', $promocodeId);
      $result = $this->db->delete('promotional_codes');
      return $result;
    }
  }

  function isCodeUsed($promocodeId)
  {
    $this->db->select('promotional_code_id');
    $this->db->from('orders');
    $this->db->where('promotional_code_id', $promocodeId);
    $dbQuery = $this->db->get();
    if ($dbQuery->num_rows() == 0)
      return false;
    else
      return true;
  }

  function userPendingPurchases($userId)
  {
    $this->db->select('SUM(quantity) as total_items');
    $this->db->from('order_items');
    $this->db->join('orders', 'order_items.order_id = orders.order_id', 'inner');
    $this->db->where('user_id ', $userId);
    $this->db->where('purchase_date ', null);
    $dbQuery = $this->db->get();
    if ($dbQuery->num_rows() == 1)
      return $dbQuery->row()->total_items;
    else
      return false;
  }

  ///////////////////////////////////////////////////////////



}
?>