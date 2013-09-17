<?php
/*
   This file is part of BillPlease, a single-user web app that keeps
   track of personal expenses.
   BillPlease is Copyright 2013 by Elod Csirmaz <http://www.github.com/csirmaz>

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

   public function get_value($key) {
      if (!isset($_POST[$key])) {
         throw new Exception("Missing data '$key'");
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
      if ((!is_numeric($value)) || intval($value * 100) != $value * 100) {
         throw new Exception("'$value' is not a valid value.");
      }
      return $value;
   }

   public function get_year($key) {
      $value = $this->get_value($key);
      $this->_check_int($value, 'Year');
      if ($value < 2000 || $value > 2200) {
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