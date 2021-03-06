<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Loction extends CI_Controller {

    function __construct() {
        parent::__construct();
        if (!$this->ion_auth->logged_in()) {
            redirect('auth/login');
        }
        $this->load->model('Loction_model');
        $this->load->model('Location_gallery_model');
        $this->load->model('Loction_Tour_Type_Model');
        $this->load->model('Country_model');
        $this->load->model('Common_Model');
        $this->load->model('Attraction_Model');
        
        $this->load->library('form_validation');
        $this->loctionImageDir = $this->config->item('upload_loction_image');
        $this->loctionTourTypeDir = $this->config->item('upload_loction_tour_type_image');
    }

    public function index() {
        $country_list = array();
        
        $loction_list = $this->Loction_model->get_all();
        $data = array(
            'title' => 'Loction',
            'list_heading' => 'Loction Destination',
            'loction_list' => $loction_list,
        );
        $this->template->load('admin/base', 'location/loction', $data);
    }

    public function add_loction() {
        $insertedArray = array();
        $countries = '';
        $attraction = '';
        $data = $this->input->post();
        if (!empty($data)) {
            $countryId = !empty($data['country_id']) ? $data['country_id'] : null;
            
            $loction_info = returnValidData('loction_destination',$data);
            $name = !empty($data['loction']) ? $data['loction'] : '';

            $slug = url_title($name, 'dash', true);
            $loction_info['slug'] = $slug;
            
            /*************************Upload loction multiple images******************************/
            
             if (!empty($_FILES['location_images'])) {
                $files = $_FILES;
                $images = array();
                $locationPrimaryImage = "";
                $cpt = count($_FILES['location_images']['name']);
                $config['upload_path'] = $this->loctionImageDir;
                $config['allowed_types'] = 'jpeg|jpg|png';
                $this->load->library('upload', $config);
                for ($i = 0; $i < $cpt; $i++) {
                    $_FILES['location_images']['name'] = $files['location_images']['name'][$i];
                    $_FILES['location_images']['type'] = $files['location_images']['type'][$i];
                    $_FILES['location_images']['tmp_name'] = $files['location_images']['tmp_name'][$i];
                    $_FILES['location_images']['error'] = $files['location_images']['error'][$i];
                    $_FILES['location_images']['size'] = $files['location_images']['size'][$i];

                    if ($this->upload->do_upload('location_images')) {
                       $uploads = $this->upload->data();
                        $imgName = $uploads['file_name'];
                        if ($data['primary'] == $i) {
                            $locationPrimaryImage = $imgName;
                        }
                        $images[$i] = $imgName;
                    } else {
                        
                        setMessage($this->upload->display_errors(),'warning');
                        redirect('loction/add_loction');
                    }
                }
            }
            
            if(!empty($_FILES['banner_image']['name'])){
                $banner['banner_image'] = $_FILES['banner_image'];
                $condition_array = array(
                    'path'=>$this->loctionImageDir,
                    'extention'=>'jpeg|jpg|png',
                    'redirect_url'=>'loction/add_loction',
                    'max_width'=> '1800',
                    'max_height'=> '600'
                    );
                
                $bannerImg = $this->Common_Model->uploadFile($banner,$condition_array);
                $loction_info['banner_image'] = $bannerImg;
            }
            
            try {
                
                $loction_attraction = returnValidData('loction_attraction',$data);
                $best_timeData =   returnValidData('loction_peak_duration',$data);
                
                if (empty($loction_info['parent_id'])) {
                    $loction_info['parent_id'] = 0;
                }
                
                if (!empty($data['is_capital'])) {

                    $conditionArray = array('country_id' => $countryId);
                    $captitalCity = isUnique('capital_cities', $conditionArray);
                    if (!$captitalCity) {
                        setMessage('Capital city is already added for this country!','warning');
                        redirect('loction/add_loction','warning');
                    } else {
                        $capitalCity = array();
                        $capitalCity['country_id'] = $countryId;
                        $capitalCity['location_id'] = empty($data['parent_id']) ? 0 : $data['parent_id'];
                        $capitalCity['city_name'] = !empty($data['loction']) ? $data['loction'] : null;
                        $this->Country_model->addCapitalCity($capitalCity, FALSE);
                    }
                }

                $last_inserted_id = $this->Loction_model->insert($loction_info, FALSE);
               
                //add multiple attraction based on loction
                
                if(!empty($loction_attraction)){
                    $this->Loction_model->addAttraction($loction_attraction, $last_inserted_id);
                }

                if(!empty($best_timeData)){
                    $this->Loction_model->addBestTimeToVisit($best_timeData, $last_inserted_id);
                }

                if(!empty($images)){
                    $insertedArray = array();
                    $loction_images = implode(',', $images);
                    $insertedArray['image'] = '{' . $loction_images . '}';
                    $insertedArray['country_id'] = $countryId;
                    $insertedArray['location_id'] = $last_inserted_id;
                    $insertedArray['primary_image'] = $locationPrimaryImage;
                    $this->Location_gallery_model->insert($insertedArray, FALSE);
                }
                
                setMessage('Loction added Successfully','success');
                redirect('loction', 'refresh');
            } catch (Exception $ex) {
                   $sdata['message'] = 'Loction not added! Please Try again';
                    $flashdata = array(
                        'flashdata' => $sdata['message'],
                        'message_type' => 'error'
                    );
                    $this->session->set_userdata($flashdata);
                    redirect('loction', 'refresh');
            }
        }
        
        $countries = $this->Country_model->fields('id,name')->get_all();
        $attraction = $this->Attraction_Model->fields('id,name')->get_all();
        $data = array(
            'title' => 'Add Loction',
            'countries' => $countries,
            'attraction' => $attraction,
        );
        
        $this->template->load('admin/base', 'location/add_loction', $data);
    }
    
    function loction_tour_types(){
        
        $list = $this->Loction_Tour_Type_Model->
                            with_country('fields:name')->
                            with_loction('fields:loction')->
                            get_all();
       
        $data = array(
            'title' => 'Loction Tour Type',
            'list_heading' => 'Loction Tour Type',
            'loction_tour_type_list' => $list,
        );
        $this->template->load('admin/base', 'location/loction_tour_type', $data);
        
    }
    
    
    function addLoctionTourType(){
         $insertedArray = array();
        if(!empty($_POST)){
            $data = $this->input->post();
            if(!empty($data['activity_id'])){
               $data['tour_type_group_id'] = null;
               $data['tour_type_id'] = null;
            }
            
            if(!empty($data['tour_type_group_id'])){
               $data['activity_id'] = null;
            }
            
            $loction_name = !empty($data['loction']) ? $data['loction'] : null;
            $loctionData = getLoctionIDByName(trim($loction_name));
            $loctionId = !empty($loctionData['id']) ? $loctionData['id'] : null;
            
             /*************************Upload loction multiple images******************************/
            
             if (!empty($_FILES['loction_tour_type_images'])) {
                $files = $_FILES;

                $images = array();

                $cpt = count($_FILES['loction_tour_type_images']['name']);

                $config['upload_path'] = $this->loctionTourTypeDir;
                $config['allowed_types'] = 'jpeg|jpg|png';
                $this->load->library('upload', $config);
                for ($i = 0; $i < $cpt; $i++) {
                    $_FILES['loction_tour_type_images']['name'] = $files['loction_tour_type_images']['name'][$i];
                    $_FILES['loction_tour_type_images']['type'] = $files['loction_tour_type_images']['type'][$i];
                    $_FILES['loction_tour_type_images']['tmp_name'] = $files['loction_tour_type_images']['tmp_name'][$i];
                    $_FILES['loction_tour_type_images']['error'] = $files['loction_tour_type_images']['error'][$i];
                    $_FILES['loction_tour_type_images']['size'] = $files['loction_tour_type_images']['size'][$i];

                    if (!$this->upload->do_upload('loction_tour_type_images')) {
                        $sdata['message'] = $this->upload->display_errors();
                        $flashdata = array(
                            'flashdata' => $sdata['message'],
                            'message_type' => 'notice'
                        );
                        $this->session->set_userdata($flashdata);
                    } else {
                        
                        $sdata['message'] = $this->upload->display_errors();
                        $flashdata = array(
                            'flashdata' => $sdata['message'],
                            'message_type' => 'notice'
                        );
                        $this->session->set_userdata($flashdata);
                    }

                    $images[] = $_FILES['loction_tour_type_images']['name'];
                }

            }
            
            
            try {
                
                $loction_tour_type = returnValidData('location_tour_types',$data);
                $best_timeData     = returnValidData('loction_peak_duration',$data);
                
                $loction_tour_type['loction_id'] = $loctionId;
                
                $last_inserted_id = $this->Loction_Tour_Type_Model->insert($loction_tour_type, FALSE);
                if(!empty($best_timeData)){
                    $res = $this->Loction_Tour_Type_Model->addBestTimeToVisit($best_timeData, $last_inserted_id);
                }
                
                if(!empty($images)){
                    $this->Loction_Tour_Type_Model->addImages($images, $last_inserted_id);
                }
                
                 $sdata['message'] = 'Loction Tour Type added Successfully';
                $flashdata = array(
                    'flashdata' => $sdata['message'],
                    'message_type' => 'success'
                );
                $this->session->set_userdata($flashdata);
                redirect('loction/loction_tour_types', 'refresh');
                
                
            } catch (Exception $ex) {
                 $sdata['message'] = 'Loction Tour Type added! Please Try again';
                    $flashdata = array(
                        'flashdata' => $sdata['message'],
                        'message_type' => 'error'
                    );
                    $this->session->set_userdata($flashdata);
                    redirect('loction/loction_tour_types', 'refresh');
            }
            
        }
        
        $data = array(
            'title' => 'Loction Tour Types',
            'list_heading' => 'Loction Tour Types',
        );
        
        $this->template->load('admin/base', 'location/add_loction_tour_type', $data);
    }
    
    
    public function editTourTypeLoction($id = null){
        $bestTimeVisit = '';
        $galleryImages = '';
        if(!empty($id)){
           $edit_data = $this->Loction_Tour_Type_Model->
                            with_country('fields:name')->
                            with_loction('fields:loction')->
                            get($id);
           
           $sql = "select image from location_gallery where loction_tour_type_id = $id";
           $galleryImages = $this->db->query($sql)->result();
           
           $sql2 = "select best_time_from,best_time_to,description from loction_peak_duration where loction_tour_type_id = $id";
           $bestTimeVisit = $this->db->query($sql2)->result();
           
        }
        
        if(!empty($_POST)){
            $data = $this->input->post();
            if(!empty($data['activity_id'])){
               $data['tour_type_group_id'] = null;
               $data['tour_type_id'] = null;
            }
            
            if(!empty($data['tour_type_group_id'])){
               $data['activity_id'] = null;
            }
            
            $loction_name = !empty($data['loction']) ? $data['loction'] : null;
            $loctionData = getLoctionIDByName(trim($loction_name));
            $loctionId = !empty($loctionData['id']) ? $loctionData['id'] : null;
            
             /*************************Upload loction multiple images******************************/
            
             if (!empty($_FILES['loction_tour_type_images'])) {
                $files = $_FILES;

                $images = array();

                $cpt = count($_FILES['loction_tour_type_images']['name']);

                $config['upload_path'] = $this->loctionTourTypeDir;
                $config['allowed_types'] = 'jpeg|jpg|png';
                $this->load->library('upload', $config);
                for ($i = 0; $i < $cpt; $i++) {
                    $_FILES['loction_tour_type_images']['name'] = $files['loction_tour_type_images']['name'][$i];
                    $_FILES['loction_tour_type_images']['type'] = $files['loction_tour_type_images']['type'][$i];
                    $_FILES['loction_tour_type_images']['tmp_name'] = $files['loction_tour_type_images']['tmp_name'][$i];
                    $_FILES['loction_tour_type_images']['error'] = $files['loction_tour_type_images']['error'][$i];
                    $_FILES['loction_tour_type_images']['size'] = $files['loction_tour_type_images']['size'][$i];

                    if (!$this->upload->do_upload('loction_tour_type_images')) {
                        $sdata['message'] = $this->upload->display_errors();
                        $flashdata = array(
                            'flashdata' => $sdata['message'],
                            'message_type' => 'notice'
                        );
                        $this->session->set_userdata($flashdata);
                    } else {
                        
                        $sdata['message'] = $this->upload->display_errors();
                        $flashdata = array(
                            'flashdata' => $sdata['message'],
                            'message_type' => 'notice'
                        );
                        $this->session->set_userdata($flashdata);
                    }

                    $images[] = $_FILES['loction_tour_type_images']['name'];
                }

            }
            
             
            try {
                
                $loction_tour_type = returnValidData('location_tour_types',$data);
                $best_timeData     = returnValidData('loction_peak_duration',$data);
                
                $loction_tour_type['loction_id'] = $loctionId;
                
                
                $last_inserted_id = $this->Loction_Tour_Type_Model->update($loction_tour_type, $id);
                
                if(!empty($best_timeData)){
                    $del = $this->Loction_Tour_Type_Model->deleteBestTimeVisit($id);
                    
                    if($del){
                        $res = $this->Loction_Tour_Type_Model->addBestTimeToVisit($best_timeData, $id);
                    }
                    
                }
                
                if(!empty($images)){
                    $delImage = $this->Loction_Tour_Type_Model->deleteImages($id);
                    
                    if($delImage){
                        $this->Loction_Tour_Type_Model->addImages($images, $id);
                    }
                    
                }
                
                
                $sdata['message'] = 'Loction Tour Type added Successfully';
                $flashdata = array(
                    'flashdata' => $sdata['message'],
                    'message_type' => 'success'
                );
                $this->session->set_userdata($flashdata);
                redirect('loction/loction_tour_types', 'refresh');
                
                
            } catch (Exception $ex) {
                 $sdata['message'] = 'Loction Tour Type added! Please Try again';
                    $flashdata = array(
                        'flashdata' => $sdata['message'],
                        'message_type' => 'error'
                    );
                    $this->session->set_userdata($flashdata);
                    redirect('loction/loction_tour_types', 'refresh');
            }
        }
        
        $data = array(
            'title' => 'Loction Tour Types',
            'list_heading' => 'Loction Tour Types',
            'edit_data' => $edit_data,
            'gallery_images' => $galleryImages,
            'best_time_visit' => $bestTimeVisit,
        );
        
        $this->template->load('admin/base', 'location/edit_loction_tour_type', $data);
        
        
    }
    
     function updateLocationWithAttractions($id = null){
         $bestTimeVisit = '';
         $edit_data = array();
         $bestTimeVisit = array();
         $galleryImages = array();
         $locationAttractions = array();
         $attractionData = array();
         
         if(!empty($id)){
            $edit_data = $this->Loction_model->get($id);
//            dump($edit_data);die;
            $countryId = !empty($edit_data->country_id) ? $edit_data->country_id : null;
            
            $sql = 'SELECT image from location_gallery where location_id ='.$id;
            $galleryImages = $this->db->query($sql)->row();

            $sql2 = "select best_time_from,best_time_to,description from loction_peak_duration where location_id = $id";
            $bestTimeVisit = $this->db->query($sql2)->result();
            
             $sql3 = "select attraction_id from loction_attraction where location_id = $id";
             $locationAttraction = $this->db->query($sql3)->result();
             if (!empty($locationAttraction)) {
                 foreach($locationAttraction as $value){
                     $locationAttractions[] = $value->attraction_id;
                 }
//                $locationAttraction = array_column($locationAttraction, 'attraction_id');
            }

        }
        
        $data = $this->input->post();
        if(!empty($data)){
            
            $loction_info = returnValidData('loction_destination',$data);
            $name = !empty($data['loction']) ? $data['loction'] : '';
            $slug = url_title($name, 'dash', true);
            $loction_info['slug'] = $slug;
            
            /*************************Upload loction multiple images******************************/
            
             if (!empty($_FILES['location_images'])) {
                $files = $_FILES;
                $images = array();
                $locationPrimaryImage = "";

                $cpt = count($_FILES['location_images']['name']);

                $config['upload_path'] = $this->loctionImageDir;
                $config['allowed_types'] = 'jpeg|jpg|png';
                $this->load->library('upload', $config);
                for ($i = 0; $i < $cpt; $i++) {
                    $_FILES['location_images']['name'] = $files['location_images']['name'][$i];
                    $_FILES['location_images']['type'] = $files['location_images']['type'][$i];
                    $_FILES['location_images']['tmp_name'] = $files['location_images']['tmp_name'][$i];
                    $_FILES['location_images']['error'] = $files['location_images']['error'][$i];
                    $_FILES['location_images']['size'] = $files['location_images']['size'][$i];

                    if ($this->upload->do_upload('location_images')) {
                       $uploads = $this->upload->data();
                        $imgName = $uploads['file_name'];
                        if ($data['primary'] == $i) {
                            $locationPrimaryImage = $imgName;
                        }
                        $images[$i] = $imgName;
                    } else {
                        
                        $sdata['message'] = $this->upload->display_errors();
                        $flashdata = array(
                            'flashdata' => $sdata['message'],
                            'message_type' => 'notice'
                        );
                        $this->session->set_userdata($flashdata);
                    }
                }
            }
            
            if(!empty($_FILES['banner_image']['name'])){
                $banner['banner_image'] = $_FILES['banner_image'];
                $condition_array = array(
                    'path'=>$this->loctionImageDir,
                    'extention'=>'jpeg|jpg|png',
                    'redirect_url'=>'loction/add_loction',
                    'max_width'=> '1800',
                    'max_height'=> '600'
                    );
                
                $bannerImg = $this->Common_Model->uploadFile($banner,$condition_array);
                $loction_info['banner_image'] = $bannerImg;
            }else{
                unset($loction_info['banner_image']);
            }
            
            
            try {
               
                $loction_attraction = returnValidData('loction_attraction',$data);
                $best_timeData =   returnValidData('loction_peak_duration',$data);
                if (empty($loction_info['parent_id'])) {
                    $loction_info['parent_id'] = 0;
                }

                $ab = $this->Loction_model->update($loction_info, $id);
                
                if(!empty($loction_attraction)){
                    $del = $this->Loction_model->delLocationAttraction($id);
                    if($del){
                        $this->Loction_model->addAttraction($loction_attraction, $id);
                    }
                    
                }

                if(!empty($best_timeData)){
//                    dump($best_timeData);die;
                    $del = $this->Loction_model->deleteBestTimeVisit($id);
                    if($del){
                         $this->Loction_model->addBestTimeToVisit($best_timeData, $id);
                    }
                }

                if(!empty($images)){
                    $del = $this->Loction_model->deleteImages($id);
                    if($del){
                        $insertedArray = array();
                        $loction_images = implode(',', $images);
                        $insertedArray['image'] = '{' . $loction_images . '}';
                        $insertedArray['country_id'] = $countryId;
                        $insertedArray['location_id'] = $id;
                        $insertedArray['primary_image'] = $locationPrimaryImage;
                        $this->Location_gallery_model->insert($insertedArray, FALSE); 
                    }
                }
                
                $sdata['message'] = 'Loction Updated Successfully';
                $flashdata = array(
                    'flashdata' => $sdata['message'],
                    'message_type' => 'success'
                );
                $this->session->set_userdata($flashdata);
                redirect('loction', 'refresh');
            } catch (Exception $ex) {
                   $sdata['message'] = 'Loction not added! Please Try again';
                    $flashdata = array(
                        'flashdata' => $sdata['message'],
                        'message_type' => 'error'
                    );
                    $this->session->set_userdata($flashdata);
                    redirect('loction', 'refresh');
            }
            
        }
        
        $countries = $this->Country_model->fields('id,name')->get_all();
        
        $attraction = $this->Attraction_Model->fields('id,name')->where('country_id',$countryId)->get_all();
		if(!empty($attraction)){
                    foreach($attraction as $value){
                        $attractionData[$value->id] = $value->name;
                    }
		}
                
        $data = array(
            'title' => 'Update Location',
            'list_heading' => 'Update Location',
            'countries' => $countries,
            'attractions' => $attractionData,
            'location_attraction' => $locationAttractions,
            'edit_data' => $edit_data,
            'gallery_images' => $galleryImages,
            'best_time_visit' => $bestTimeVisit,
        );
        
        $this->template->load('admin/base', 'location/edit_loction', $data);
    }
    
    function location_search() {
        
        $countryId = 0;
        $result = array();
        $searchTerm = $_GET['term'];
        if(!empty($_GET['country_id'])){
            $countryId = $_GET['country_id'];
        }
        
        if(!empty($searchTerm)){
            $query = $this->db->query("SELECT * FROM loction_destination WHERE LOWER(loction) LIKE LOWER('%".$searchTerm."%') AND country_id = $countryId ORDER BY loction ASC");
            $data = $query->result_array();
            if(!empty($data)){
                foreach ($data as $datas){
                    $result['loction'] = $datas['loction'];
                }
                echo json_encode($result);die;
            }
            
            echo 'false';die;
        }
    }

}
