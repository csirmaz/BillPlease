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

/** Class representing an expense item */
class ItemData {
   protected $id;

   protected $year;
   protected $month;
   protected $day;
   protected $uday; /*< UnixDay object */
   protected $udayto; /*< UnixDay object; $uday+$timespan */

   protected $dayid;
   protected $name;
   protected $value; /*< in the object, not an integer; in the DB, multiplied by 100 */
   protected $timespan;
   protected $accountto;
   protected $accountfrom;
   protected $checked;
   protected $ctype; /*< in the object, can be 'X'; in the DB, empty string is used */
   protected $business;
   protected $clong;
   protected $infuture = false; /*< Not in DB. Depends on current time. */
   protected $activelong = false; /*< Not in DB. Depends on current time. */

   /** Expects an array keyed on property names and with appropriate values */
   private function __construct($attrs = array()) {
      foreach ($attrs as $k => $v) {
         if (property_exists($this, $k)) {
            $this->$k = $v;
         }
      }
   }

   /** Construct from data from the DB */
   public static function from_db($attrs = array()) {
      $attrs['value']/= 100;
      if (!$attrs['ctype']) {
         $attrs['ctype'] = 'X';
      }
      $attrs['uday'] = new UnixDay($attrs['unixday']);
      $attrs['udayto'] = new UnixDay($attrs['unixdayto']);
      $item = new Item($attrs);
      return $item;
   }

   /** Alternative constructor */
   public static function new_empty_on($year, $month, $day) {
      return self::from_raw(array( //
      'dayid' => - 1, //
      'year' => $year, //
      'month' => $month, //
      'day' => $day));
   }

   /** Construct object from raw, possibly incomplete data.
    *
    * Extra keys in $attrs: 'year', 'month', 'day', 'unixday', 'unixdayto'
    * Year, month, day and unixday are calculated from each other.
    * A new dayid is requested if it is unset, false, or <0, *and* $DB is given.
    */
   public static function from_raw($attrs = array(), $DB = false) {

      // accounts (two characters) -> accountto, accountfrom (one character each)
      if (isset($attrs['accounts'])) {
         $attrs['accountto'] = substr($attrs['accounts'], 0, 1);
         $attrs['accountfrom'] = substr($attrs['accounts'], 1, 1);
      }
      // fix business -> 1/0
      $attrs['business'] = (isset($attrs['business']) && $attrs['business']) ? 1 : 0;
      // fix checked
      if ((!isset($attrs['checked'])) || $attrs['checked'] == '') {
         $attrs['checked'] = 0;
      }
      // fix ctype
      if ((!isset($attrs['ctype'])) || (!$attrs['ctype'])) {
         $attrs['ctype'] = 'X';
      }

      // get unixday
      if (isset($attrs['unixday'])) {
         $attrs['uday'] = new UnixDay($attrs['unixday']);
         unset($attrs['unixday']);
      }else{
         $attrs['uday'] = UnixDay::from_ymd($attrs['year'], $attrs['month'], $attrs['day']);
      }

      // fix timespan
      if ((!isset($attrs['timespan'])) || $attrs['timespan'] < 1) {
         $attrs['timespan'] = 1;
      } elseif ($attrs['timespan'] == 30) {
         $attrs['timespan'] = thirtyone($attrs['uday']->month()); // TODO global function
      }

      // get unixdayto (needs timespan)
      if (isset($attrs['unixdayto'])) {
         $attrs['udayto'] = new UnixDay($attrs['unixdayto']);
         unset($attrs['unixdayto']);
      }else{
         $attrs['udayto'] = new UnixDay($attrs['uday']->ud() + $attrs['timespan']);
      }

      return (new Item($attrs));
   }

   /** Set flags that depend on the current time */
   public function set_nowday($nowday) { // expects unixtime/60/60/24
      if ($this->uday->ud() > $nowday) {
         $this->infuture = true;
      } else {
         if ($this->uday->ud() <= $nowday && $this->udayto->ud() > $nowday) {
            $this->activelong = true;
         }
      }
      return $this;
   }

   public function get_dayid() {
      return $this->dayid;
   }

   public function reset_dayid() {
      $this->dayid = -1;
      return $this;
   }

   public function get_name() {
      return $this->name;
   }

   public function set_name($x) {
      $this->name = $x;
      return $this;
   }

   public function get_unixday() {
      return $this->uday->ud();
   }

   public function get_unixdayto() {
      return $this->udayto->ud();
   }

   public function get_timespan() {
      return $this->timespan;
   }

   public function get_ctype() {
      return $this->ctype;
   }

   public function set_ctype($x) {
      if((!isset($x)) || $x===false || $x==''){ $x='X'; }
      $this->ctype = $x;
      return $this;
   }

   public function get_clong() {
      return $this->clong;
   }

   public function get_accountto() {
      return $this->accountto;
   }

   public function get_clong_as_num() {
      $v = $this->clong - 0;
      if ($v == 0) {
         throw new Exception('Error retrieving long value on ' . $this->uday->simple_string());
      }
      return $v;
   }

   public function get_info() {
      return $this->uday->simple_string() . ' ' . $this->name . ' ' . $this->value . '/' . $this->timespan;
   }

   public function get_value() {
      return $this->value;
   }

   public function set_value($x) {
      $this->value = $x;
      return $this;
   }

   public function realvalue() {
      if ($this->accountfrom) {
         return 0;
      }
      return $this->value;
   }

   public function store($DB) {
      $placeholders = array();
      $values = array();
      $names = array('year', 'month', 'day', 'dayid', //
      'name', 'value', 'timespan', 'accountto', 'accountfrom', //
      'checked', 'ctype', 'business', 'clong', 'unixday', 'unixdayto');
      foreach ($names as $n) {
         switch($n){
            case 'value':
               $v = $this->$n;
               $v*= 100;
               break;
            case 'ctype':
               $v = $this->$n;
               $v = ($v == 'X' ? '' : $v);
               break;
            case 'year':
               $v = $this->uday->year();
               break;
            case 'month':
               $v = $this->uday->month();
               break;
            case 'day':
               $v = $this->uday->day();
               break;
            case 'unixday':
               $v = $this->uday->ud();
               break;
            case 'unixdayto':
               $v = $this->udayto->ud();
               break;
            case 'dayid':
               if(isset($this->dayid) && $this->dayid !==false && $this->dayid >= 0){
                  $v = $this->dayid;
               }else{
                  $v = $DB->get_free_dayid($this->uday->ud());
                  $this->dayid = $v;
               }
               break;
            default:
               $v = $this->$n;
               break;
         }

         $placeholders[] = '?';
         $values[] = $v;
      }

      $DB->exec_assert_change( //
      'insert into costs (' . implode(',', $names) . ') values (' . implode(',', $placeholders) . ')', //
      $values, 1);
   }

}

?>