<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Order extends CI_Controller {

    function __construct() {
        parent::__construct();
        $this->load->model('Insert_model', 'put');
        $this->load->model('Select_model', 'get');
        $this->load->model('Update_model', 'set');
        $this->load->library('upload');
        $this->load->library('lineapi');
    }

    public function Summary($orderid = "") {
        $this->load->view('template/bill');
    }

    public function paymentsuccess($token = "") {
        $ordertoken = $this->get->ordertoken(array('token' => $token))->row();
        if ($ordertoken == null) {
            redirect(base_url());
        }
        $data["obj"] = $this;
        $uid = $ordertoken->uid;
        $merchantid = $ordertoken->merchantid;
        $data["merchant"] = $this->get->merchant(array('id' => $merchantid))->row();
        $this->load->view('template/paymentsuccess', $data);
    }

    public function trackorder($token = "", $merchantuid = "") {
        $ordertoken = $this->get->ordertoken(array('token' => $token))->row();
        if ($ordertoken == null) {
            redirect(base_url());
        }
        $data["obj"] = $this;
        $data["uid"] = $ordertoken->uid;

        $merchantid = $ordertoken->merchantid;
        $orderid = $ordertoken->orderid;

        $data["merchant"] = $this->get->merchant(array('id' => $merchantid))->row();
        $data["order"] = $this->get->order(array('id' => $orderid))->row();
        $data["custdetail"] = $this->get->customer(array('id' => $data["order"]->custid))->row();
        $data["orderdetail"] = $this->get->orderdetail(array('orderid' => $orderid))->result();
        $data["items"] = $this->get->items(array('merchantid' => $merchantid))->result();
        $data["order"] = $this->get->order(array('id' => $orderid))->row();




        $meruid = $this->get->v_merchantlineuid(array('token' => $token, 'lineuid' => $merchantuid))->num_rows();
        $data["canedit"] = $meruid > 0 ? true : false;


        $this->load->view('template/track', $data);
    }

    public function getpaymentmethoddetail($id) {
        $res = "";
        $paymentmethod = $this->get->paymentmethod(array('id' => $id))->row();
        $res .= "ธนาคาร " . $paymentmethod->bankname;
        $res .= "ประเภท " . $paymentmethod->acctype;
        $res .= "เลขที่บัญชี " . $paymentmethod->accno;
        $res .= "ชื่อบัญชี " . $paymentmethod->accname;
        return $res;
    }

    public function getamount($orderdetail, $itemid) {
        foreach ($orderdetail as $item) {
            if ($item->itemid == $itemid) {
                return $item->amount;
            }
        }

        return "0";
    }

    public function payment($token = "") {
        $data["ordertoken"] = $token;

        $ordertoken = $this->get->ordertoken(array('token' => $token))->row();
        $data["uid"] = $ordertoken->uid;
        $merchantid = $ordertoken->merchantid;
        $orderid = $ordertoken->orderid;
        $data["obj"] = $this;
        $data["merchant"] = $this->get->merchant(array('id' => $merchantid))->row();
        $data["orderdetail"] = $this->get->orderdetail(array('orderid' => $orderid))->result();
        $data["items"] = $this->get->items(array('merchantid' => $merchantid))->result();
        $data["order"] = $this->get->order(array('id' => $orderid))->row();
        $data["paymentmethod"] = $this->get->paymentmethod(array('merchantid' => $merchantid))->result();
        $data["customer"] = $this->get->customer(array('uid' => $data["uid"]))->row();

        if ($data["order"]->status >= 1) {
            redirect(base_url("/track/$token"));
        }

        $data["province"] = $this->get->province(array())->result();
        if ($_POST) {
            $image = "";
            if (!empty($_FILES['txtfileupload']['name'])) {

                $upPath = "./public/upload/slip";

                $config['upload_path'] = "$upPath";
                $config['allowed_types'] = 'gif|jpg|png';
                $config['max_size'] = '0';
                $config['max_width'] = '0';
                $config['max_height'] = '0';

                $this->upload->initialize($config);

                if (!$this->upload->do_upload('txtfileupload')) {
                    $data['imageError'] = $this->upload->display_errors();
                } else {
                    $imageDetailArray = $this->upload->data();
                    $image = $imageDetailArray['file_name'];
                }
            }
            $imagepath = "";
            if ($image != "") {
                $imagepath = "/public/upload/slip/$image";
            }

            $txtfullname = $this->input->post("txtfullname");
            $txttel = $this->input->post("txttel");
            $txtaddress = $this->input->post("txtaddress");
            $txtprovince = $this->input->post("txtprovince");
            $txtaumpure = $this->input->post("txtaumpure");
            $txttumbol = $this->input->post("txttumbol");
            $txtzipcode = $this->input->post("txtzipcode");
            $paymenttype = $this->input->post("paymenttype");
            $txtpaiddate = $this->input->post("txtpaiddate");
            $txtpaidtime = $this->input->post("txtpaidtime");

            $input = array(
                'fullname' => $txtfullname,
                'tel' => $txttel,
                'provinceid' => $txtprovince,
                'tumbolid' => $txttumbol,
                'aumpureid' => $txtaumpure,
                'zipcode' => $txtzipcode,
                'fulladdress' => $txtaddress,
                'uid' => $data["uid"],
                'updatedate' => date('Y-m-d H:i:s'),
            );
            $custid = $this->put->customer($input);

//            $cond = array('uid' => $input['uid']);
//            if ($this->get->customer($cond)->num_rows() == 0) {
//                $this->put->customer($input);
//            } else {
//                $this->set->customer($input);
//            }



            $input = array(
                'id' => $orderid,
                'custid' => $custid,
                'billingaddress' => $this->getfulladdress($txtaddress, $txttumbol, $txtaumpure, $txtprovince, $txtzipcode),
                'status' => '1',
                'slipimage' => $imagepath,
                'paymentmethodid' => $paymenttype,
                'paymentinfo' => $txtpaiddate . ' ' . $txtpaidtime,
                'updatedate' => date('Y-m-d H:i:s'),
            );
            $this->set->order($input);
            //$this->lineapi->pushmsg($data["uid"], "ขอบคุณที่อุดหนุนค่ะ ลูกค้าสามารถติดตามการสั่งซื้อได้ที่ลิงค์นี้ https://perdbill.co/track/$token");

            $v_merchantuid = $this->get->v_merchantuid(array('ordertoken' => $token))->result();
            foreach ($v_merchantuid as $item) {
                $this->lineapi->pushmsg($item->lineuid, "ลูกค้าได้ส่งคำสั่งการสั่งซื้อ สามารถดูได้ที่ https://perdbill.co/track/$token/$item->lineuid");
            }

            redirect(base_url("/paymentsuccess/$token"));
        }


        $this->load->view('template/payment', $data);
    }

    public function getfulladdress($txtaddress, $txttumbol, $txtaumpure, $txtprovince, $txtzipcode) {
        $fulladdr = $txtaddress;
        $fulladdr .= " ตำบล/แขวง " . $this->get->district(array('DISTRICT_ID' => $txttumbol))->row()->DISTRICT_NAME;
        $fulladdr .= " อำเภอ/เขต " . $this->get->amphur(array('AMPHUR_ID' => $txtaumpure))->row()->AMPHUR_NAME;
        $fulladdr .= " จังหวัด " . $this->get->province(array('PROVINCE_ID' => $txtprovince))->row()->PROVINCE_NAME;
        $fulladdr .= " รหัสไปรษณีย์ " . $txtzipcode;
        return $fulladdr;
    }

}
