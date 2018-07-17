<?php
/**
 * @category Bitbull
 * @package  Bitbull_Tooso
* @author   Fabio Gollinucci <fabio.gollinucci@bitbull.it>
 */
class Bitbull_Tooso_Model_Observer_Tracking extends Bitbull_Tooso_Model_Observer
{
    const CONTAINER_BLOCK = 'before_body_end';

    /**
     * Add product tracking script that point to relative controller action endpoint
     * @param  Varien_Event_Observer $observer
     */
    public function includeProductTrackingScript(Varien_Event_Observer $observer){
        if(!Mage::helper('tooso')->isTrackingEnabled()){
            return;
        }
        $currentProduct = Mage::registry('current_product');
        if($currentProduct != null) {
            $layout = Mage::app()->getLayout();
            $block = Mage::helper('tooso/tracking')->getProductTrackingPixelBlock($currentProduct->getId());
            $layout->getBlock(self::CONTAINER_BLOCK)->append($block);
            $this->_logger->debug('Tracking product: added tracking script');
        }else{
            $this->_logger->warn('Tracking product view: product not found in request');
        }
    }

    /**
     * Add page tracking script that point to relative controller action endpoint
     * @param  Varien_Event_Observer $observer
     */
    public function includePageTrackingScript(Varien_Event_Observer $observer){
        if(!Mage::helper('tooso')->isTrackingEnabled()){
            return;
        }
        $layout = Mage::app()->getLayout();
        $block = Mage::helper('tooso/tracking')->getPageTrackingPixelBlock();
        $layout->getBlock(self::CONTAINER_BLOCK)->append($block);
        $this->_logger->debug('Tracking page view: added tracking script');
    }

    /**
     * Track checkout event
     * @param Varien_Event_Observer $observer
     */
    public function trackCheckout(Varien_Event_Observer $observer)
    {
        if(!Mage::helper('tooso')->isTrackingEnabled()){
            return;
        }

        $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        if($orderId != null){
            $layout = Mage::app()->getLayout();
            $block = Mage::helper('tooso/tracking')->getCheckoutTrackingPixelBlock($orderId);
            $layout->getBlock(self::CONTAINER_BLOCK)->append($block);
            $this->_logger->debug('Tracking cart: added tracking script');
        }else{
            $this->_logger->warn('Tracking checkout: can\'t find order id in session');
        }
    }

    /**
     * Track add to cart event
     * not using tracking script to track also ajax 'add to cart' call
     * @param Varien_Event_Observer $observer
     */
    public function trackAddToCart(Varien_Event_Observer $observer)
    {
        if(!Mage::helper('tooso')->isTrackingEnabled()){
            return;
        }

        $product = $observer->getEvent()->getProduct();
        if($product != null){
            $sku = $product->getSku();
            $profilingParams = Mage::helper('tooso')->getProfilingParams();
            $params = array(
                'objectId' => $sku
            );
            $this->_client->productAddedToCart($params, $profilingParams);
            $this->_logger->debug('Tracking cart: added tracking script');
        }else{
            $this->_logger->warn('Tracking cart: can\'t find product param');
        }
    }

}
