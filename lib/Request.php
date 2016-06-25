<?php
/*
   This file is part of BillPlease, a single-user web app that keeps
   track of personal expenses.
   BillPlease is Copyright 2016 by Elod Csirmaz <http://www.github.com/csirmaz>

   This program is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/** This class makes data in the request accessible */
class Request {

   private $view;
   private $storage;
   private $action;

    public function __construct(){
        $this->view = self::get_value('view', true);
        $this->action = self::get_value('action', true);

        $this->storage = json_decode($_COOKIE['storage'], true);
    }
   
   // Possible values: list
   public function get_view(){ return $this->view; }
   
   // Possible values: new
   public function get_action(){ return $this->action; }
   
   // Keys: listscroll
   public function get_storage($key){ return $this->storage[$key]; }

   public static function get_value($key, $noexception=false) {
      if (!isset($_POST[$key])) {
         if (!isset($_GET[$key])) {
            if(!$noexception){ throw new Exception("Missing data '$key'"); }
            return '';
         }
         return $_GET[$key];
      }
      return $_POST[$key];
   }

   public function get_string($key) {
      $value = $this->get_value($key);
      if ($value == '') {
         throw new Exception("Empty string for '$key'");
      }
      return $value;
   }

   private function _check_int($value, $msg = '') {
      if ((!is_numeric($value)) || intval($value) != $value) {
         throw new Exception("'$msg' '$value' is not an integer");
      }
   }

   public function get_int($key) {
      $value = $this->get_value($key);
      $this->_check_int($value, $key);
      return $value;
   }

   public function get_money($key) {
      $value = $this->get_value($key);
      if (!is_numeric($value)) {
         throw new Exception("'$value' is not a valid value (1)");
      }
      if (!preg_match('/^-?\d+(\.\d{1,2})?$/', $value)) {
         throw new Exception("'$value' is not a valid value (2)");
      }
      return $value;
   }

   public function get_year($key) {
      $value = $this->get_value($key);
      $this->_check_int($value, 'Year');
      if ($value < 1970 || $value > 2200) {
         throw new Exception("Year '$value' is out of range");
      }
      return $value;
   }

   public function get_month($key) {
      $value = $this->get_value($key);
      $this->_check_int($value, 'Month');
      if ($value < 1 || $value > 12) {
         throw new Exception("Month '$value' is out of range");
      }
      return $value;
   }

   public function get_day($key) {
      $value = $this->get_value($key);
      $this->_check_int($value, 'Day');
      if ($value < 1 || $value > 31) {
         throw new Exception("Day '$value' is out of range");
      }
      return $value;
   }

   /** Returns 0 or 1 based on whether the checkbox was selected */
   public function get_checkbox($key) {
      if (!isset($_POST[$key])) {
         return 0;
      }
      return 1;
   }
}

?>