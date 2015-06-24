<?php
/**
 * Main library, will dispatch commands to the others.
 * 
 * @author     Michiel Van Gucht
 * @version    0.0.1
 * @copyright  2015 Michiel Van Gucht
 * @license    LGPL
 */
 
defined('DS') ? null : define('DS', DIRECTORY_SEPARATOR);

class dpdLibrary {
  
  /**
   * Get all the libraries that were uploaded to the server
   * @return array 
   */
  static function getLibraries() {
    $dir_path = dirname(__FILE__);
    $result = array();
    foreach (glob("*.php") as $filename)
    {
      if($filename != basename(__FILE__)
        && $filename != "index.php") {
        $class_name = basename($filename, ".php");

        require_once($dir_path . DS . $filename);
        $result[$class_name::UID] = $class_name;
      }
    }
    return $result;
  }
  
  /**
   * Get the configuration fields required for each (or specific) libraries
   * @param array $libraries an array of library UIDs
   * @return array UID => dpdConfiguration[]
   */
  static function getConfiguration($libraries = false) {
    $selected = self::loadLibraries($libraries);
    $result = array();
    foreach($selected as $UID => $library_name) {
      $result[$UID] = $library_name::getConfiguration();
    }
    return $result;
  }
  
  /**
   * Get the services of each (or specific) library
   * @param array $libraries an array of library UIDs
   * @return array UID => dpdService[]
   */
  static function getServices($libraries = false) {
    $selected = self::loadLibraries($libraries);
    $result = array();
    foreach($selected as $UID => $library_name) {
      $result[$UID] = $library_name::getServices();
    }
    return $result;
  }
  
  /**
   * Simple construct
   * @param stdClass $config Contains all the values set by the configuration fields
   * @param dpdCache $cache A platform specific cache object.
   * @return dpdLibrary
   */
  public function __construct($config, dpdCache $cache) {
    $this->config = $config;
    $this->cache = $cache;
  }
  
  // Reminder: I didn't make this static because it needs the Config and Cache objects.
  /**
   * Get shops for all or specific libraries
   * @param dpdLocation $location A location object with address or long lat set.
   * @param int $limit Amount of shops returned (per library)
   * @param array $libraries array of libary UIDs
   * @result array[UID => dpdShop[]]
   */
  public function getShops(dpdLocation $location, $limit = 10, $libraries = false) {
    $selected = $this->loadLibraries($libraries);
    $result = array();
    foreach($selected as $library_name) {
      $class = new $library_name($this->config, $this->cache);
      $result[] = $class->getShops($location, $limit);
    }
    return $result;
  }
  
  /**
   * Get a label for a certain order
   * To define what Library is used we look at the parent UID of the dpdService object in the dpdOrder object
   * @param dpdOrder $order Order will be validated by the validate function of the dpdService object
   * @param $format The format of the label (dpdLabel constants)
   * @return dpdLabel
   */
  public function getLabel(dpdOrder $order, $format = dpdLabel::pdf) {
    $selected = $this->loadLibraries(array($order->service->parentId));
    $library_name = $selected[$order->service->parentId];
    $class = new $library_name($this->config, $this->cache);
    $result = $class->getLabel($order, $format);
    return $result;
  }
  
  /**
   * Get labels for multiple orders (mixed lirbary)
   * @param dpdOrder[] $orders
   * @param $format dpdLabel constants
   * @result dpdLabel[]
   */
  public function getLabels(array $orders, $format = dpdLabel::pdf) {
    $result = array();
    foreach ($orders as $order) {
      $label = $this->getLabel($order, $format);
      if($label) {
        $result[] = $label;
      }
    }
    return $result;
  }
  
  /**
   * Load the selected libaries
   * @param array $libaries An array of library UIDs
   * @return array[ UID => classname ]
   */
  private static function loadLibraries($libraries = false) {
    $selected = array();
    if(!$libraries) {
      $selected = self::getLibraries();
    }
    if(is_array($libraries)) {
      $all_libraries = self::getLibraries();
      foreach($libraries as $UID) {
        $selected[$UID] = $all_libraries[$UID];
      }
    }
    $dir_path = dirname(__FILE__);
    foreach($selected as $library_name) {
      require_once($dir_path . DS . $library_name . ".php");
    }
    return $selected;
  }
}