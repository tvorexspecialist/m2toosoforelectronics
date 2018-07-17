<?php
class Bitbull_Tooso_TrackingController extends Mage_Core_Controller_Front_Action {

    /**
     * @var Bitbull_Tooso_Helper_Log
     */
    protected $_logger = null;

    /**
     * @var Bitbull_Tooso_Client
     */
    protected $_client = null;


    public function _construct(){
        parent::_construct();

        $this->_logger = Mage::helper('tooso/log');
        $this->_client = Mage::helper('tooso')->getClient();
    }

    public function pixelAction() {

        $product_id = $this->getRequest()->getParam('product');
        if($product_id == null){
            $this->_logger->warn('Tracking: product param not found');
            return;
        }

        $current_product = Mage::getModel('catalog/product')->load($product_id);
        if($current_product == null){
            $this->_logger->warn('Tracking: product not found with id '.$product_id);
            return;
        }

        if(Mage::helper('tooso/tracking')->isUserComingFromSearch()){ //request from search page

            $this->_logger->debug('Tracking: elaborating result tracking pixel..');
            $id = $current_product->getId();
            $sku = $current_product->getSku();
            $toosoSearchId = Mage::helper('tooso/session')->getSearchId();

            if($toosoSearchId){
                // Get rank collection from search collection
                $searchRankCollection = Mage::helper('tooso/session')->getRankCollection();
                $rank = -1;
                if($searchRankCollection != null && isset($searchRankCollection[$id])){
                    $rank = $searchRankCollection[$id];
                }else{
                    if($searchRankCollection == null){
                        $this->_logger->debug('Tracking: rank collection not found in session');
                    }else{
                        $this->_logger->debug('Tracking: sku not found in rank collection, printing..');
                        foreach ($searchRankCollection as $rankId => $rankPos){
                            $this->_logger->debug('Tracking: '.$rankId.' => '.$rankPos);
                        }
                    }
                }

                $order = Mage::helper('tooso/session')->getSearchOrder();
                if($order == null){
                    $order = "relevance";
                }

                $profilingParams = Mage::helper('tooso')->getProfilingParams();
                $params = array(
                    "searchId" => $toosoSearchId,
                    "resultId" => $sku,
                    "rank" => $rank,
                    "order" => $order
                );

                $this->_client->resultTracking($params, $profilingParams);

            }else{
                $this->_logger->warn('Tracking: search id not found in session');
                return;
            }

        }else{ // request not from search page

            $this->_logger->debug('Tracking: elaborating product view tracking pixel..');

            $sku = $current_product->getSku();
            $profilingParams = Mage::helper('tooso')->getProfilingParams();
            $params = array(
                "sku" => $sku
            );

            $this->_client->productViewTracking($params, $profilingParams);

        }

        // Prevent browser cache
        $this->getResponse()->setHeader('Expires', '0');
        $this->getResponse()->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
        $this->getResponse()->setHeader('Pragma','no-cache');
        $this->getResponse()->setHeader('Cache-Control', 'post-check=0, pre-check=0');

        // Set javascript content type
        $this->getResponse()->setHeader('Content-type', 'application/javascript');

        // Response with empty script
        $this->getResponse()->setBody("");

        $this->_logger->debug('Tracking: pixel added into page');
    }

}