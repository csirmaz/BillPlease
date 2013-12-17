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

   private $DB;

   private $sum;
   private $timedsum;
   private $items = array();
   private $longitems = array(); // recurring items affecting this day

   public function __construct($DB, $year, $month, $day, $unixday = false) {
      $this->year = $year;
      $this->month = $month;
      $this->day = $day;
      if ($unixday === false) {
         $unixday = CostsDB::date2unixday($year, $month, $day);
      }
      $this->unixday = $unixday;
      $this->DB = $DB;
   }

   // Alternative constructor
   public static function from_unixday($DB, $unixday) {
      $ut = $unixday * 24 * 60 * 60;
      return new Day($DB, date('Y', $ut), date('n', $ut), date('j', $ut), $unixday);
   }

   public function get_sum() {
      $this->load_sums();
      return $this->sum; // TODO Why not call load_sums? (make DB property)

   }
   public function get_timedsum() {
      $this->load_sums();
      return $this->timedsum; // TODO Why not call load_sums? (make DB property)

   }
   public function get_js_date() {
      return 'new Date(' . $this->year . ',' . ($this->month - 1) . ',' . $this->day . ')';
   }

   private function load_sums() {
      if (!isset($this->sum)) {
         // Index: costs_acfr_ud_v
         $this->sum = $this->DB->querysingle( // TODO Sum set here and below
         'select sum(value) from costs where unixday=? and accountfrom=\'\'', array($this->unixday) //
         ) / 100;
      }
      if (!isset($this->timedsum)) {
         // Index: costs_acfr_ud_udt_v_s
         $this->timedsum = $this->DB->querysingle( //
         'select sum((value*1.0)/timespan) from costs where unixday<=? and unixdayto>? and accountfrom=\'\'', //
         array($this->unixday, $this->unixday) //
         ) / 100;
      }
   }

   public function load_items($nowday) {
      if (count($this->items) > 0) {
         return;
      }
      $sum = 0;
      $this->DB->query_callback('select * from costs where unixday=? order by dayid', //
      array($this->unixday), //
      function ($r) use ($nowday, &$sum) {
         $item = Item::from_db($r);
         $item->set_nowday($nowday);
         $this->items[] = $item;
         $sum+= $item->realvalue(); // TODO Sum loaded here and above

      });

      $this->sum = $sum;
   }

   public function load_long_items($nowday) {
      if (count($this->longitems) > 0) {
         return;
      }
      $this->DB->query_callback('select * from costs where unixday<? and unixdayto>? order by unixday, dayid', // excluding today
      array($this->unixday, $this->unixday), //
      function ($r) use ($nowday) {
         $item = Item::from_db($r);
         $item->set_nowday($nowday);
         $this->longitems[] = $item;
      });
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
          . '<b style="color:#00f">Sum</b> ' . printsum($this->get_timedsum(), true) . $image . '</span> &nbsp; ' //
          . '<span class="transp" title="Daily balance"><b style="color:#f00">Sum*</b> ' //
          . printsum($this->get_sum()) . '</span>' //
          . ' &nbsp; <a href="javascript://" title="Show recurring entries" class="getlongitems" data-ud="' . $this->unixday . '">L</a>');
      }

      return $h;
   }

   // Get HTML describing all elements affecting the current day
   public function get_long_info($nowday) {
      $this->load_items($nowday);
      $this->load_long_items($nowday);
      $out = '';
      /*
      foreach ($this->items as $item){
         $out .= $item->to_html_line();
      }
      */
      foreach ($this->longitems as $item) {
         $out.= $item->to_html_line();
      }
      return '<table>' . $out . '</table>';
   }

}

?>