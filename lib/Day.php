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

// Class representing a day of expense items
class Day {
   private $year;
   private $month;
   private $day;
   private $unixday;

   private $sum;
   private $timedsum;
   public $items = array(); // TODO for PHP 5.3 only

   public function __construct($year, $month, $day, $unixday = false) {
      $this->year = $year;
      $this->month = $month;
      $this->day = $day;
      if ($unixday === false) {
         $unixday = CostsDB::date2unixday($year, $month, $day);
      }
      $this->unixday = $unixday;
   }

   // Alternative constructor
   public static function from_unixday($unixday) {
      $ut = $unixday * 24 * 60 * 60;
      return new Day(date('Y', $ut), date('n', $ut), date('j', $ut), $unixday);
   }

   public function get_sum() {
      return $this->sum;
   }
   public function get_timedsum() {
      return $this->timedsum;
   }
   public function get_js_date() {
      return 'new Date(' . $this->year . ',' . ($this->month - 1) . ',' . $this->day . ')';
   }

   public function load_sums($DB) {
      if (!isset($this->sum)) {
         // Index: costs_acfr_ud_v
         $this->sum = $DB->querysingle( // TODO Sum set here and below
         'select sum(value) from costs where unixday=? and accountfrom=\'\'', array($this->unixday) //
         ) / 100;
      }
      if (!isset($this->timedsum)) {
         // Index: costs_acfr_ud_udt_v_s
         $this->timedsum = $DB->querysingle( //
         'select sum((value*1.0)/timespan) from costs where unixday<=? and unixdayto>? and accountfrom=\'\'', //
         array($this->unixday, $this->unixday) //
         ) / 100;
      }
   }

   public function load_items($DB, $nowday) {
      $me = $this; // TODO for PHP 5.3 only
      $sum = 0;
      $DB->query_callback('select * from costs where unixday=?', //
      array($this->unixday), //
      function ($r) use ($me, $nowday, &$sum) {
         $item = Item::from_db($r);
         $item->set_nowday($nowday);
         $me->items[] = $item;
         $sum+= $item->realvalue(); // TODO Sum loaded here and above

      });

      $this->sum = $sum;
   }

   public function to_html($FirstChecked, $qfocuscheck, $nofooter) {

      // header
      $h = Html::table_header_row(false, $this->year, $this->month, $this->day, $this->unixday);

      foreach ($this->items as $item) {
         $h.= $item->to_html($FirstChecked, $qfocuscheck);
      }

      // footer
      if (!$nofooter) {
         $image = false;
         if ($this->timedsum < - 2) {
            $image = 'plus.png';
         }
         if ($this->timedsum > 0) {
            $image = 'minus.png';
         }
         if ($image) {
            $image = ' <img src="' . $image . '"/>';
         }
         $h.= Html::table_footer_row(' <span title="Daily balance corrected using recurrence">' //
          . '<b style="color:#00f">Sum</b> ' . printsum($this->timedsum, true) . $image . '</span> &nbsp; ' //
          . '<span class="transp" title="Daily balance"><b style="color:#f00">Sum*</b> ' //
          . printsum($this->sum) . '</span>' //
         );
      }

      return $h;
   }

}

?>