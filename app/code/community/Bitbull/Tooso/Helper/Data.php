<?php
/**
 * @package Bitbull_Tooso
 * @author Gennaro Vietri <gennaro.vietri@bitbull.it>
 */

class Bitbull_Tooso_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_ENABLE_SEARCH = 'tooso/active/frontend';

    const XML_PATH_ENABLE_INDEX = 'tooso/active/index';

    const XML_PATH_ENABLE_TRACKING = 'tooso/active/tracking';

    const XML_PATH_SUGGEST_MAX_RESULTS = 'tooso/suggest/max_results';

    const XML_PATH_SERVER_APIKEY = 'tooso/server/api_key';

    const XML_PATH_SERVER_SECRETKEY = 'tooso/server/secret_key';

    const XML_PATH_SERVER_API_BASEURL = 'tooso/server/api_base_url';

    protected $_fixedSearchString = null;
    
    protected $_searchId = null;

    protected $_products = null;

    protected $_searchAlreadyDone = false;

    public function isSearchEnabled($store = null)
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLE_SEARCH, $store);
    }

    public function isIndexEnabled()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLE_INDEX);
    }

    public function isTrackingEnabled($store = null)
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLE_TRACKING, $store);
    }

    public function getSuggestMaxResults($store = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_SUGGEST_MAX_RESULTS, $store);
    }

    /**
     * @return array
     */
    public function getProducts()
    {
        return $this->_products;
    }

    /**
     * @param array $products
     */
    public function setProducts($products)
    {
        $this->_products = $products;
    }

    /**
     * @param string $fixedSearchString
     */
    public function setFixedSearchString($fixedSearchString)
    {
        $this->_fixedSearchString = $fixedSearchString;
    }

    /**
     * @return string
     */
    public function getFixedSearchString()
    {
        return $this->_fixedSearchString;
    }

    /**
     * @param string $searchId
     */
    public function setSearchId($searchId)
    {
        $this->_searchId = $searchId;
    }

    /**
     * @return string
     */
    public function getSearchId()
    {
        return $this->_searchId;
    }

    public function isTypoCorrectedSearch()
    {
        return Mage::app()->getRequest()->getParam('typoCorrection', 'true') == 'true';
    }

    /**
     * @return string
     */
    public function getParentSearchId()
    {
        return Mage::app()->getRequest()->getParam('parentSearchId');
    }
    
    /**
     * Create and configure a Tooso API Client instance
     * 
     * @return Bitbull_Tooso_Client
    */
    public function getClient($storeCode = null, $language = null)
    {
        $apiKey = Mage::getStoreConfig(self::XML_PATH_SERVER_APIKEY);
        $secretKey = Mage::getStoreConfig(self::XML_PATH_SERVER_SECRETKEY);
        $apiBaseUrl = Mage::getStoreConfig(self::XML_PATH_SERVER_API_BASEURL);
        if($language == null){
            $language = Mage::app()->getLocale()->getLocaleCode();
            /*
            if(strpos($language, "_") !== false){
                $language = explode("_", $language)[0];
            }*/
        }
        if($storeCode == null){
            $storeCode = Mage::app()->getStore()->getCode();
        }
        $client = new Bitbull_Tooso_Client($apiKey, $secretKey, $apiBaseUrl, $language, $storeCode);

        $client->setLogger(Mage::helper('tooso/log'));
        $client->setReportSender(Mage::helper('tooso/log_send'));
        $client->setSessionStorage(Mage::helper('tooso/session'));

        return $client;
    }

    /**
     * Get profiling parameters
     *
     * @param null|array $override
     * @return array
     */
    public function getProfilingParams($override = null)
    {
        $customerSession = Mage::getSingleton('customer/session');
        $sessionId = Mage::getSingleton('core/session')->getSessionId();

        if ($customerSession->isLoggedIn()) {
            $userId = $customerSession->getCustomerId();
        } else {
            $userId = $sessionId;
        }

        $clientId = Mage::helper('tooso/session')->getClientId();

        $params = array(
            'ip' => Mage::helper('core/http')->getRemoteAddr(),
            'userId' => $userId,
            'sessionId' => $sessionId,
            'clientId' => $clientId,
            'isMobile' => Mage::helper('tooso/tracking')->isMobile(),
            'lastPage' => Mage::helper('tooso/tracking')->getLastPage(),
            'currentPage' => Mage::helper('tooso/tracking')->getCurrentPage()
        );

        if($override != null && is_array($override)){
            foreach ($override as $key => $value){
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * Set SearchAlreadyDone to true
     */

    public function setSearchAlreadyDone(){
        $this->_searchAlreadyDone = true;
    }

    /**
     * @return boolean
     */

    public function getSearchAlreadyDone(){
        return $this->_searchAlreadyDone;
    }

    /**
     * Get product attributes
     */

    public function getAttributesToIndex(){

    }
}