<?php
/**
 * @package Bitbull_Tooso
 * @author Gennaro Vietri <gennaro.vietri@bitbull.it>
 */

class Bitbull_Tooso_Model_Indexer
{
    const XML_PATH_INDEXER_STORES = 'tooso/indexer/stores_to_index';
    const XML_PATH_INDEXER_DRY_RUN = 'tooso/indexer/dry_run_mode';
    const DRY_RUN_FILENAME = 'tooso_index_%store%.csv';

    /**
     * Client for API comunication
     *
     * @var Bitbull_Tooso_Client
     */
    protected $_client;

    /**
     * @var Bitbull_Tooso_Helper_Log
     */
    protected $_logger = null;


    public function __construct()
    {
        $this->_client = Mage::helper('tooso')->getClient();

        $this->_logger = Mage::helper('tooso/log');
    }

    /**
     * Rebuild Tooso Index
     *
     * @return boolean
    */
    public function rebuildIndex()
    {
        try {
            $stores = $this->_getStoreViews();
            foreach ($stores as $storeCode => $storeId) {
                $this->_logger->debug("Indexer: indexing store ".$storeCode);
                if($this->_isDebugEnabled()){
                    $this->_logger->debug("Indexer: store output into debug file ");
                    $this->_writeDebugFile($this->_getCsvContent($storeId), $storeCode);
                }else{
                    $this->_client->index($this->_getCsvContent($storeId), $storeCode);
                }
                $this->_logger->debug("Indexer: store ".$storeCode." index completed");
            }
        } catch (Exception $e) {
            $this->_logger->logException($e);
            return false;
        }

        return true;
    }

    /**
     * Clean Tooso Index
     *
     * @todo Should be implemented, but so far Tooso don't support index cleaning
     *
     * @return boolean
    */
    public function cleanIndex()
    {
        return true;
    }

    /**
     * Get catalog exported CSV content
     *
     * return string
    */
    protected function _getCsvContent($storeId)
    {
        $excludeAttributes = array(
            'image_label',
            'old_id',
            'small_image_label',
            'thumbnail_label',
            'uf_product_link',
            'url_path',
            'custom_layout_update',
            'recurring_profile',
            'group_price',
            'is_recurring',
            'minimal_price',
            'msrp',
            'msrp_display_actual_price_type',
            'msrp_enabled',
            'options_container',
            'page_layout',
            'price_view',
            'country_of_manufacture',
            'gift_message_available',
            'tax_class_id',
            'tier_price'
        );
        $attributeFrontendTypes = array(
            'text',
            'textarea',
            'multiselect',
            'select',
            'boolean',
            'price'
        );
        /*
         * Excluding:
         *   'date'
         *   'media_image'
         *   'image'
         *   'gallery'
         */
        $attributeBackendTypes = array(
            'varchar',
            'int',
            'text',
            'decimal',
        );
        /*
         * Excluding:
         *   'date',
         *   'datetime',
         *   'static'
         */

        $attributes = array(
            'sku' => 'sku',
            'name' => 'text',
            'description' => 'text',
            'short_description' => 'text',
            'status' => 'text',
            'availability' => 'text'
        );
        $headers = array_merge($attributes, array(
            'variables' => 'variables'
        ));

        $attributesCollection = Mage::getResourceModel('catalog/product_attribute_collection')
            ->addFieldToFilter('backend_type', array('in' => $attributeBackendTypes))
            ->addFieldToFilter('frontend_input', array('in' => $attributeFrontendTypes))
            ->addFieldToFilter('attribute_code', array('nin' => $excludeAttributes))
        ;

        $productCollection = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToFilter('visibility', array('neq' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE))
            ->addStoreFilter($storeId)
        ;
        foreach ($attributes as $attributeCode){
            $productCollection->addAttributeToSelect($attributeCode);
        }

        foreach ($attributesCollection as $attribute) {
            $attributes[$attribute->getAttributeCode()] = $attribute->getFrontendInput();
            $headers[$attribute->getAttributeCode()] = $attribute->getAttributeCode();

            $productCollection->addAttributeToSelect($attribute->getAttributeCode());

            $productCollection->joinAttribute(
                $attribute->getAttributeCode(),
                'catalog_product/' . $attribute->getAttributeCode(),
                'entity_id',
                null,
                'left',
                $storeId
            );
        }

        $writer = $this->_getWriter();
        $writer->setHeaderCols($headers);

        foreach ($productCollection as $product) {
            $row = array();
            foreach ($attributes as $attributeCode => $frontendInput) {
                if($frontendInput === 'select'){
                    $row[$attributeCode] = $product->getAttributeText($attributeCode);
                }else{
                    $row[$attributeCode] = $product->getData($attributeCode);
                }
            }
            $row["variables"] = json_encode($this->_getVariablesObject($product));
            $writer->writeRow($row);
        }

        return $writer->getContents();
    }

    /**
     * Return product variables object with associated products
     *
     * @param $product
     */
    protected function _getVariablesObject($product){
        $variables = array();
        if($product->getTypeId() == Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE) {
            $productAttributesOptions = $product->getTypeInstance(true)->getConfigurableOptions($product);

            foreach ($productAttributesOptions as $productAttributeOption) {
                $configurableData[$product->getId()] = array();
                foreach ($productAttributeOption as $optionValues) {
                    $optionData = array();
                    $optionData[$optionValues['attribute_code']] = $optionValues['option_title'];
                    $variables[$optionValues['sku']] = $optionData;
                }
            }
        }
        return $variables;
    }

    /**
     * Get stores grouped by lang code
     * @return array stores
     */
    protected function _getStoreViews()
    {
        $storesConfig = Mage::getStoreConfig(self::XML_PATH_INDEXER_STORES);

        $stores = array();
        if($storesConfig == null){
            $collection = Mage::getModel('core/store')->getCollection();
            foreach ($collection as $store) {
                $stores[$store->getCode()] = $store->getId();
            }
        }else{
            $storesArrayConfig = explode(",", $storesConfig);
            foreach ($storesArrayConfig as $storeId) {
                $store = Mage::getModel('core/store')->load($storeId);
                $stores[$store->getCode()] = $store->getId();
            }
        }

        $this->_logger->debug("Indexer: using stores ".json_encode($stores));

        return $stores;
    }

    /**
     * @return Mage_ImportExport_Model_Export_Adapter_Csv
    */
    protected function _getWriter()
    {
        return $this->_writer = Mage::getModel('importexport/export_adapter_csv', array());
    }

    /**
     * Print content into debug CSV file
     *
     * @param $content
     * @param $store_id
     * @return bool
     */
    protected function _writeDebugFile($content, $storeId = null){

        $logPath = Mage::getBaseDir('var').DS.'log';
        $fileName = "";
        if($storeId == null){
            $fileName = str_replace("_%store%", "", self::DRY_RUN_FILENAME);
        }else{
            $fileName = str_replace("%store%", $storeId, self::DRY_RUN_FILENAME);
        }
        $file_path = $logPath.DS.$fileName;
        $file = fopen($file_path, "w");
        if(!$file){
            $this->_logger->logException(new Exception("Unable to open file CSV debug file [".$file_path."]"));
            return false;
        }else{
            fwrite($file, $content);
            fclose($file);
            return true;
        }
    }

    /**
     * Debug flag
     *
     * @return bool
     */
    protected function _isDebugEnabled(){
        return Mage::getStoreConfigFlag(self::XML_PATH_INDEXER_DRY_RUN);
    }

    /**
     * Return stores for backend multiselect options
     */
    public function toOptionArray() {
        return Mage::getSingleton('adminhtml/system_store')->getStoreValuesForForm(false, true);
    }
}
