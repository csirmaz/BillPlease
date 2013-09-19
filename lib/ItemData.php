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
   public $year; // TODO Review public properties
   public $month;
   public $day;
   public $dayid;
   protected $name;
   protected $value; /*< in the object, not an integer; in the DB, multiplied by 100 */
   protected $timespan;
   public $accountto;
   protected $accountfrom;
   protected $checked;
   protected $ctype; /*< in the object, can be 'X'; in the DB, empty string is used */
   protected $business;
   protected $clong;
   public $unixday; /*< = unixtime/24/60/60 */
   protected $unixdayto; /*< = unixday+timespan */
   protected $infuture = false; /*< Not in DB. Depends on current time. */
   protected $activelong = false; /*< Not in DB. Depends on current time. */

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
      $item = new Item($attrs);
      return $item;
   }

   public static function new_empty_on($year, $month, $day) {
      return self::from_raw(array('dayid' => - 1, //
      'year' => $year, //
      'month' => $month, //
      'day' => $day));
   }

   /** Construct object from raw, possibly incomplete data
    *
    * Year, month, day and unixday are calculated from each other.
    * A new dayid is requested if it is unset, false, or <0, *and* $DB is given.
    */
   public static function from_raw($attrs = array(), $DB = false) {
      // accounts -> accountto, accountfrom
      if (isset($attrs['accounts'])) {
         $attrs['accountto'] = substr($attrs['accounts'], 0, 1);
         $attrs['accountfrom'] = substr($attrs['accounts'], 1, 1);
      }
      // fix timespan
      if ((!isset($attrs['timespan'])) || $attrs['timespan'] < 1) {
         $attrs['timespan'] = 1;
      } elseif ($attrs['timespan'] == 30) {
         $attrs['timespan'] = thirtyone($attrs['month']); // TODO global function

      }
      // fix business
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
      if (!isset($attrs['unixday'])) {
         $attrs['unixday'] = CostsDB::date2unixday($attrs['year'], $attrs['month'], $attrs['day']);
      }
      // or get year, month, day
      if (!isset($attrs['year'])) {
         $at = $attrs['unixday'] * 60 * 60 * 24;
         $attrs['year'] = date('Y', $at);
         $attrs['month'] = date('n', $at);
         $attrs['day'] = date('j', $at);

      }
      // get unixdayto
      if (!isset($attrs['unixdayto'])) {
         $attrs['unixdayto'] = $attrs['unixday'] + $attrs['timespan'];
      }
      // try to get dayid if $DB is given
      if (!isset($attrs['dayid']) || $attrs['dayid'] === false || $attrs['dayid'] < 0) {
         if ($DB) {
            $attrs['dayid'] = $DB->get_free_dayid($attrs['unixday']);
         }
      }

      return (new Item($attrs));
   }

   public function store($DB) {
      $placeholders = array();
      $values = array();
      $names = array('year', 'month', 'day', 'dayid', 'name', 'value', 'timespan', 'accountto', 'accountfrom', 'checked', 'ctype', 'business', 'clong', 'unixday', 'unixdayto');
      foreach ($names as $n) {
         $v = $this->$n;
         if ($n == 'value') {
            $v*= 100;
         } elseif ($n == 'ctype') {
            $v = ($v == 'X' ? '' : $v);
         }
         $placeholders[] = '?';
         $values[] = $v;
      }

      $DB->exec_assert_change( //
      'insert into costs (' . implode(',', $names) . ') values (' . implode(',', $placeholders) . ')', //
      $values, 1);
   }

   /** Set flags that depend on the current time */
   public function set_nowday($nowday) { // expects unixtime/60/60/24
      if ($this->unixday > $nowday) {
         $this->infuture = true;
      } else {
         if ($this->unixday <= $nowday && $this->unixdayto > $nowday) {
            $this->activelong = true;
         }
      }
      return $this;
   }

   public function realvalue() {
      if ($this->accountfrom) {
         return 0;
      }
      return $this->value;
   }
}

?>