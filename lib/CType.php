<?php
/*
   This file is part of BillPlease, a single-user web app that keeps
   track of personal expenses.
   BillPlease is Copyright 2019 by Elod Csirmaz <http://www.github.com/csirmaz>

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

/** This class collects sums per type (category) */

class CType {

   private $DB = array();
   private $types = array(); /*< label => array(name=>, chartcolor=>, chartorder=>) */
   private $sums = array(); /*< label => sum */
   private $gensums = array(); /*< '+'/'-' => sum */

   public function __construct($DB) {

      $this->DB = $DB;

      $DB->query_callback(
         'select * from ctypes order by chartorder',
         false,
         function ($r) {
            $this->types[$r['sign']] = array(
               'name' => $r['name'],
               'chartcolor' => $r['chartcolor'],
               'chartorder' => $r['chartorder']
            );
         }
      );
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
      $this->gensums = array('+' => 0, '-' => 0);

      if ($timed) {
         // {TIMEDVALUE}*
         // Note that here we compare a time period to a time period
         $query = 'select * from costs where istransfer=0 and ((unixdayto > ? and unixday <= ?) or (unixday > ? and unixdayto <= ?))';
         $query_args = array($dayfrom, $dayto, $dayfrom, $dayto);
      } else {
         $query = 'select * from costs where istransfer=0 and unixday >= ? and unixday <= ?';
         $query_args = array($dayfrom, $dayto);
      }

      // debug $q2 = 'select count(*) '.substr($query, 8);
      // debug print_r([$q2, $query_args, $this->DB->querysingle($q2, $query_args)]);
      
      $this->DB->query_callback(
         $query,
         $query_args,
         function ($r) use ($dayto, $dayfrom, $timed) {
            $item = Item::from_db($r);
            $t = $this->types[$item->get_ctype()];
            $v = $item->realvalue();

            if ($timed) {
               // {TIMEDVALUE}*
               $item_from = min($item->get_unixdayto(), $item->get_unixday());
               $item_to = max($item->get_unixdayto(), $item->get_unixday());
               $visiblespan = min($dayto, $item_to - 1) - max($dayfrom, $item_from) + 1;
               $v = $v * $visiblespan / abs($item->get_timespan());
            }

            if ($v < 0) { // income
               $this->gensums['+'] -= $v;
            } else {
               $this->gensums['-'] += $v;
               $c = $r['ctype'];
               $this->sums[$item->get_ctype()] += $v;
            }
         }
      );
   }

   public function get_sum($label) {
      return $this->sums[$label];
   }
   
   /** Return the name related to a label */
   public function get_name_from_label($label) {
      return $this->types[$label]['name'];
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

   public function get_gensums() {
      return $this->gensums;
   }
   
    public function get_saving() {
        return $this->gensums['-'] - $this->gensums['+'];
    }

   /** Retrun income/expense sums minus expenses marked as 'X' */
   public function get_gensums_corrected() {
      return array(
         '+' => ($this->gensums['+'] - $this->sums['X']),
         '-' => ($this->gensums['-'] - $this->sums['X'])
      );
   }

}

?>
