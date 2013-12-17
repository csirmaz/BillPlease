<?php

class CType {

   private $DB = array();
   private $types = array();
   private $sums = array();

   public function __construct($DB) {

      $this->DB = $DB;

      $DB->query_callback('select * from ctypes order by chartorder', false, function ($r) {
         $this->types[$r['sign']] = array('name' => $r['name'], 'chartcolor' => $r['chartcolor'], 'addto' => $r['addto'], 'exceptlongto' => $r['exceptlongto'], 'chartorder' => $r['chartorder']);
      });
   }

   /** Sum items per category. If $timed, adjust the sum according to the timespan of the items. */
   public function sum($dayfrom, $dayto, $timed = false) {
      foreach ($this->types as $sign => $val) {
         $this->sums[$sign] = 0;
      }

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

         if ($t['exceptlongto']) {
            $long = $item->get_clong_as_num();
            $v-= $long;
            $this->sums[$t['exceptlongto']]+= $long;
         }
         $c = $r['ctype'];
         $this->sums[$t['addto'] ? $t['addto'] : $item->get_ctype() ]+= $v;
      });
   }

   public function get_sum($label) {
      return $this->sums[$label];
   }

   /** Call callback($label, $typedata, $sum) for each type */
   public function get_sum_callback($callback) {
      foreach ($this->sums as $label => $sum) {
         if ($this->types[$label]['chartorder'] == 0) {
            continue;
         }
         $callback($label, $this->types[$label], $sum);
      }
   }
}

?>