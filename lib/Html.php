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

class Html {

   // Returns header table row.
   // $unixday is calculated if it is FALSE.
   // $title defaults to the date if FALSE is given.
   // No click handlers are added if $year===FALSE
   public static function table_header_row($title, $year, $month, $day, $unixday = false, $adddate = false) {
      $newon = '';
      $piechart = '';
      if ($year !== false) {
         $idx = $year . ',' . $month . ',' . $day;
         $newon = 'onclick="newon(' . $idx . ')" title="Click to create a new entry on this day"';
         $piechart = 'onclick="piec(' . $idx . ')" title="Click to draw pie chart FROM this day"';
      }
      if ($unixday === false) {
         $unixday = CostsDB::date2unixday($year, $month, $day);
      }
      $ut = $unixday * 24 * 60 * 60;
      if ($title === false) {
         $title = 'Week ' . date('W', $ut) . ': <b>' . date('D d M Y', $ut) . '</b>';
      }
      return '<tr class="fejlec">' . '<td ' . $newon . ' ' . ($adddate ? 'colspan="2"' : '') . '>' //
       . $title . '</td>' . '<td align="right">GBP</td><td>recurrence</td><td title="Account & Check">acc</td>' //
       . '<td ' . $piechart . '>cat</td>' . '</tr>';
   }

   public static function table_footer_row($content, $adddate = false) {
      return '<tr class="ds"><td ' . ($adddate ? 'colspan="2"' : '') . '>' . $content //
       . '</td><td colspan=7 style="background-color:#999999;"></td></tr>' . "\n";
   }

   public static function esc($s) {
      return htmlspecialchars($s);
   }

   /* Returns a selector */
   public static function accountselector($DB, $curvalue, $singleonly = false) {
      $accountselector = '';
      // TODO Here we have a problem with SQLite comparing strings instead of numbers
      $DB->query_callback('select * from accountnames where length(accounttofrom) < (?+0) order by listorder', //
      array(($singleonly ? 2 : 3)), //
      function ($r) use (&$accountselector, $curvalue) {
         $accountselector.= '<option value="' . $r['accounttofrom'] . '" ' //
          . ($curvalue == $r['accounttofrom'] ? 'selected="selected"' : '') . '>' //
          . self::esc($r['name']) . '</option>';
      });
      return $accountselector;
   }

   /** Create a legend and JS code from the shortcuts table */
   public static function shortcuts($DB) {
      $javascript = '';
      $legend = '';
      $form = 'document.urlap';
      $DB->query_callback('select * from shortcuts', false, function ($r) use (&$javascript, &$legend, $form) {
         $legend.= $r['shortcut'] . ' - ' . $r['name'] . ' (' . $r['accountto'] . $r['accountfrom'] . ')<br/>';
         $javascript.= "if($form.name.value == '{$r['shortcut']}'){";
         if (!is_null($r['name'])) {
            $javascript.= "$form.name.value = '{$r['name']}';";
         }
         if (!is_null($r['value'])) {
            $javascript.= "$form.value.value = " . ($r['value'] / 100) . ";";
         }
         if (!is_null($r['timespan'])) {
            $javascript.= "jQuery($form.fortime).val('{$r['timespan']}');";
         }
         if (!is_null($r['accountto'])) {
            $javascript.= "jQuery($form.account).val('" . $r['accountto'] . $r['accountfrom'] . "');";
         }
         if (!is_null($r['checked'])) {
            $javascript.= "jQuery($form.checked).val('{$r['checked']}');";
         }
         if (!is_null($r['ctype'])) {
            $javascript.= "jQuery($form.type).val('{$r['ctype']}');";
         }
         if (!is_null($r['business'])) {
            $javascript.= "jQuery($form.business).val('{$r['business']}');";
         }
         if (!is_null($r['clong'])) {
            $javascript.= "jQuery($form.long).val('{$r['clong']}');";
         }
         $javascript.= "}\n";
      });
      return array('legend' => $legend, 'js' => $javascript);
   }

   public static function typeselector($DB, $current) {
      $typeselector = '';
      $DB->query_callback('select sign,name from ctypes order by listorder', false, //
      function ($r) use (&$typeselector, $current) {
         $typeselector.= '<option value="' . $r['sign'] . '" ' . ($current == $r['sign'] ? 'selected' : '') //
          . '>' . $r['sign'] . ' - ' . $r['name'] . '</option>';
      });
      return $typeselector;
   }

   public static function checkedselector($current) {
      $checkedselector = '';
      foreach (array(0 => 'no', 1 => 'green', 3 => 'blue', 2 => 'yellow') as $v => $d) {
         $checkedselector.= '<option value="' . $v . '" ' . ($current == $v ? 'selected' : '') . '>' //
          . $d . '</option>';
      }
      return $checkedselector;
   }
}

?>