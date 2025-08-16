<?php
/*
   This file is part of BillPlease, a single-user web app that keeps
   track of personal expenses.
   BillPlease is Copyright 2013-2025 by Elod Csirmaz <https://www.epcsirmaz.co.uk/>

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

   private $DB;
   private $types = []; /*< label => [name=>, chartcolor=>, chartorder=>] */
   private $sums = []; /*< label => sum */
   private $gensums = []; /*< '+'/'-' => sum */
   private $logs = []; // for debugging
   public $item_ids = []; // for debugging

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
    */
   public function sum($dayfrom, $dayto, $timed=false, $debug=false) {
      // Reset internal state
      // This keeps the ordering right!
      foreach ($this->types as $sign => $val) {
         $this->sums[$sign] = 0;
      }
      $this->gensums = ['+' => 0, '-' => 0, 'T' => 0];  // '+'=income '-'=expense 'T'=total
      $this->logs = [];
      $this->item_ids = [];
      
      Item::period_sum($this->DB, $dayfrom, $dayto, $timed, $debug,
         function($item, $v, $debug, $log, $log_header) {
            if($debug) {
               if(isset($this->logs[$item->get_ctype()])) {
                  $this->logs[$item->get_ctype()][] = $log;
                } else {
                  $this->logs[$item->get_ctype()] = [$log_header, $log];
               }
            }

            if($item->get_ctype() != 'EXC') { // exclude this type
               if($debug) { $this->item_ids[] = $item->id; }
               $this->gensums['T'] += $v;
               if ($v < 0 && $item->get_ctype() == 'X') { // income (ONLY TYPE X)
                  $this->gensums['+'] -= $v;
               } else {
                  $this->gensums['-'] += $v;
                  $this->sums[$item->get_ctype()] += $v;
               }
            }
         }
      );

      if($debug) {
         // Add sums
         /*
         foreach($this->sums as $label => $sum) {
            if(isset($this->logs[$label])) {
               $this->logs[$label][] = ["{$sum}"];
            } else {
               $this->logs[$label] = [$log_header, ["{$sum}"]];
            }
         }
         */
      }
      
      $saving = $this->gensums['-'] - $this->gensums['+'];
      if(floor($saving*100+.5) != floor($this->gensums['T']*100+.5)) {
         throw new Exception("CType::sum error for gensum total: inc={$this->gensums['+']} exp={$this->gensums['-']} exp-inc={$saving} total={$this->gensums['T']}"); 
      }
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
   
   public function get_logs_callback($callback) {
      foreach ($this->logs as $label => $data) { $callback($label, $data); }
   }

    public function get_saving() {
        return $this->gensums['-'] - $this->gensums['+'];
    }

   /** Retrun income/expense sums */
   public function get_gensums_corrected() {
      return [
         '+' => $this->gensums['+'], // income
         '-' => $this->gensums['-'], // expense
         'T' => $this->gensums['T']  // total = expense - income
      ];
   }

}

?>
