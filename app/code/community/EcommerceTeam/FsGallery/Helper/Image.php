<?php
/**
 * Filesystem Product Gallery - Magento Extension
 *
 * @package FsGallery
 * @category EcommerceTeam
 * @copyright Copyright 2012 EcommerceTeam Inc. (http://www.ecommerce-team.com)
 * @version: 1.0.0
 */

class EcommerceTeam_FsGallery_Helper_Image
    extends Mage_Catalog_Helper_Image
{
    const MODE_DIRECTORY = 'directory';
    const MODE_FILE      = 'file';

    protected $_baseDir    = '';
    protected $_dirCache   = array();
    protected $_galleryDir = 'fsg';

    public function __construct()
    {
        /** @var $mediaConfig Mage_Catalog_Model_Product_Media_Config */
        $mediaConfig    = Mage::getSingleton('catalog/product_media_config');
        $this->_baseDir = $mediaConfig->getBaseMediaPath() . DS . $this->_galleryDir;
    }

    /**
     * @param string $node
     * @return mixed
     */
    public function getConfigData($node)
    {
        return Mage::getStoreConfig(sprintf('catalog/fsgallery/%s', $node));
    }

    /**
     * @param string $node
     * @return bool
     */
    public function getConfigFlag($node)
    {
        return (bool) Mage::getStoreConfig(sprintf('catalog/fsgallery/%s', $node));
    }

    /**
     * @param string $dirName
     * @return array
     */
    protected function _getImages($dirName)
    {
        if (!$dirName) {
            return array();
        }
        if (!isset($this->_dirCache[$dirName])) {
            $this->_dirCache[$dirName] = array();
            $dirPath = $this->_baseDir . DS . $dirName;
            if (is_dir($dirPath)){
                $handle = opendir($dirPath);
                if (is_resource($handle)) {
                    while (false !== ($file = readdir($handle))) {
                        if (is_file($this->_baseDir . DS . $dirName . DS . $file)) {
                            $this->_dirCache[$dirName][] = $file;
                        }
                    }
                    closedir($handle);
                }
            }
        }
        return $this->_dirCache[$dirName];
    }

    /**
     * Initialize image file anf watermark
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string $attributeName
     * @param null|string $imageFile
     * @return EcommerceTeam_FsGallery_Helper_Image
     */
    public function init(Mage_Catalog_Model_Product $product, $attributeName, $imageFile = null)
    {

        $this->_reset();
        $this->setProduct($product);
        /** @var $imageModel EcommerceTeam_FsGallery_Model_Product_Image */
        $imageModel = Mage::getModel('ecommerceteam_fsgallery/product_image');
        $this->_setModel($imageModel);
        $imageModel->setDestinationSubdir($attributeName);
        $this->setWatermark(Mage::getStoreConfig("design/watermark/{$attributeName}_image"));
        $this->setWatermarkImageOpacity(Mage::getStoreConfig("design/watermark/{$attributeName}_imageOpacity"));
        $this->setWatermarkPosition(Mage::getStoreConfig("design/watermark/{$attributeName}_position"));
        $this->setWatermarkSize(Mage::getStoreConfig("design/watermark/{$attributeName}_size"));
        if ($imageFile) {
            $this->setImageFile($imageFile);
        } else if($this->getConfigFlag('enabled')) {
            if (self::MODE_DIRECTORY == $this->getConfigData('mode')) {
                $imageAttributeName = $this->getConfigData('dir');
                $productDir = trim($product->getData($imageAttributeName));
                if ($productDir) {
                    $image  = null;
                    $images = $this->_getImages($productDir);
                    try {
                        if (empty($images)) {
                            Mage::throwException($this->__('Images not found.'));
                        }
                        switch($attributeName){
                            case 'image' == $attributeName:
                            default:
                                $imageName    = trim($this->getConfigData('base_image_name'));
                                $availableExt = trim($this->getConfigData('base_image_ext'));
                                break;
                            case 'small_image' == $attributeName:
                                $imageName    = trim($this->getConfigData('small_image_name'));
                                $availableExt = trim($this->getConfigData('small_image_ext'));
                                break;
                            case 'thumbnail' == $attributeName:
                                $imageName    = trim($this->getConfigData('thumbnail_image_name'));
                                $availableExt = trim($this->getConfigData('thumbnail_image_ext'));
                                break;
                        }

                        if (!$imageName) {
                            Mage::throwException($this->__("{$attributeName} not defined."));
                        }
                        if ($availableExt) {
                            $availableExt = explode(',', $availableExt);
                        }
                        if (!empty($availableExt)){
                            $available = array();
                            foreach ($availableExt as $ext) {
                                $available[] = $imageName . '.' . trim($ext);
                            }
                        } else {
                            $available = array($imageName);
                        }

                        foreach ($images as $_image) {
                            if (in_array($_image, $available)) {
                                $image = $_image;
                                break;
                            }
                        }

                        if ($image) {
                            $imageFilePath = $this->_galleryDir . DS . $productDir . DS . $image;
                            $this->setImageFile($imageFilePath);
                            $imageModel->setBaseFile($imageFilePath);
                        } else {
                            $imageModel->setBaseFile(null);
                        }
                    } catch (Exception $e) {
                        $imageModel->setBaseFile(null);
                    }
                }
            } else if(self::MODE_FILE == $this->getConfigData('mode')) {
                switch($attributeName){
                    case 'image' == $attributeName:
                    default:
                        $imageAttributeName = trim($this->getConfigData('base_image'));
                        $availableExt       = trim($this->getConfigData('base_image_ext'));
                        break;
                    case 'small_image' == $attributeName:
                        $imageAttributeName = trim($this->getConfigData('small_image'));
                        $availableExt       = trim($this->getConfigData('small_image_ext'));
                        break;
                    case 'thumbnail' == $attributeName:
                        $imageAttributeName = trim($this->getConfigData('thumbnail_image'));
                        $availableExt       = trim($this->getConfigData('thumbnail_image_ext'));
                        break;
                }
                $imageName = trim($product->getData($imageAttributeName));
                if ($availableExt) {
                    $availableExt = explode(',', $availableExt);
                }
                if (!empty($availableExt)){
                    $available = array();
                    foreach ($availableExt as $ext) {
                        $available[] = $imageName . '.' . trim($ext);
                    }
                } else {
                    $available = array($imageName);
                }

                foreach ($available as $imageFilename) {
                    if (file_exists($this->_baseDir . DS . $imageFilename)) {
                        $this->setImageFile($this->_galleryDir . DS . $imageFilename);
                        $imageModel->setBaseFile($this->_galleryDir . DS . $imageFilename);
                    }
                }
            }
        } else {
            $baseFile = $product->getData($attributeName);
            $imageModel->setBaseFile($baseFile);
        }
        return $this;
    }
}