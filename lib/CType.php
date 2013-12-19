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

class CType {

   private $DB = array();
   private $types = array(); /*< label => array(name=>, chartcolor=>, addto=>, exceptlongto=>, chartorder=>) */
   private $sums = array(); /*< label => sum */
   private $gensums = array(); /*< '+'/'-' => sum */

   public function __construct($DB) {

      $this->DB = $DB;

      $DB->query_callback('select * from ctypes order by chartorder', false, function ($r) {
         $this->types[$r['sign']] = array( //
         'name' => $r['name'], //
         'chartcolor' => $r['chartcolor'], //
         'addto' => $r['addto'], //
         'exceptlongto' => $r['exceptlongto'], //
         'chartorder' => $r['chartorder'] //
         );
      });
   }

   /** Sum items per category.
    * If $timed is true, adjust the sum according to the timespan of the items.
    * $dayfrom and $dayto are inclusive.
    */
   public function sum($dayfrom, $dayto, $timed = false) {
      // This keeps the ordering right!
      foreach ($this->types as $sign => $val) {
         $this->sums[$sign] = 0;
      }
      $this->gensums = array('+'=>0, '-'=>0);

      if ($timed) {
         $query = 'select * from costs where accountfrom=\'\' and unixdayto > ? and unixday <= ?';
      } else {
         $query = 'select * from costs where accountfrom=\'\' and unixday >= ? and unixday <= ?';
      }
      $this->DB->query_callback($query, array($dayfrom, $dayto), function ($r) use ($dayto, $dayfrom, $timed) {
         $item = Item::from_db($r);
         $t = $this->types[$item->get_ctype() ];
         $v = $item->realvalue();

         if ($timed) {
            $visiblespan = min($dayto, $item->get_unixdayto() - 1) - max($dayfrom, $item->get_unixday()) + 1;
            $v = $v * $visiblespan / $item->get_timespan();
         }

         if($v<0){ // income
            $this->gensums['+'] -= $v;
         }else{
            $this->gensums['-'] += $v;
            if ($t['exceptlongto']) {
               $long = $item->get_clong_as_num();
               $v-= $long;
               $this->sums[$t['exceptlongto']]+= $long;
            }
            $c = $r['ctype'];
            $this->sums[$t['addto'] ? $t['addto'] : $item->get_ctype() ]+= $v;
         }
      });
   }

   public function get_sum($label) {
      return $this->sums[$label];
   }

   /** Call callback($label, $typedata, $sum) for each type (run sum() before calling this). */
   public function get_sum_callback($callback) {
      foreach ($this->types as $label => $data) {
         if ($data['chartorder'] == 0) {
            continue;
         }
         $callback($label, $data, $this->sums[$label]);
      }
   }

   /** Call callback($label, $typedata) for each type */
   public function get_type_callback($callback) {
      foreach ($this->types as $label => $data) {
         if ($data['chartorder'] == 0) {
            continue;
         }
         $callback($label, $data);
      }
   }

   public function get_gensums(){
      return $this->gensums;
   }

}

?>