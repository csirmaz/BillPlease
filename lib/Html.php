<?php
/*
   This file is part of BillPlease, a single-user web app that keeps
   track of personal expenses.
   BillPlease is Copyright 2014 by Elod Csirmaz <http://www.github.com/csirmaz>

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
   public static function table_header_row($title, $year, $month, $day, $unixday = false, $adddate = false) {

      /* TODO Re-implement
      // No click handlers are added if $year===FALSE
      $piechart = '';
      if ($year !== false) {
         $idx = $year . ',' . $month . ',' . $day;
         $piechart = 'onclick="piec(' . $idx . ')" title="Click to draw pie chart FROM this day"';
      }
      */

      if ($unixday === false) {
         $unixday = CostsDB::date2unixday($year, $month, $day);
      }

      if ($title === false) {
         $ut = $unixday * 24 * 60 * 60;
         $title = date('D d M Y', $ut) . ' (Week ' . date('W', $ut) . ')';
      }

      return Application::get()->solder()->fuse('item_header', array('title' => $title, 'uday' => $unixday));
   }

   public static function table_footer_row($content) {
      return Application::get()->solder()->fuse('item_footer', array('$content' => $content));
   }

   public static function esc($s) {
      return htmlspecialchars($s);
   }

   /* Returns a selector */
   public static function accountselector($DB, $current, $singleonly = false) {
      $SLD = Application::get()->solder();
      $accountselector = '';
      // TODO Here we have a problem with SQLite comparing strings instead of numbers
      $DB->query_callback(
         'select * from accountnames where length(accounttofrom) < (?+0) order by listorder',
         array(($singleonly ? 2 : 3)),
         function ($r) use (&$accountselector, $current, $SLD) {
            $accountselector .= $SLD->fuse(
               'option',
               array(
                  'value' => $r['accounttofrom'],
                  '$selected' => ($current == $r['accounttofrom'] ? 'selected="selected"' : ''),
                  'display' => $r['name']
               )
            );
         }
      );
      return $accountselector;
   }

   public static function typeselector($DB, $current) {
      $SLD = Application::get()->solder();
      $typeselector = '';
      $DB->query_callback(
         'select sign,name from ctypes order by listorder',
         false, //
         function ($r) use (&$typeselector, $current, $SLD) {
            $typeselector .= $SLD->fuse(
               'option',
               array(
                  'value' => $r['sign'],
                  '$selected' => ($current == $r['sign'] ? 'selected="selected"' : ''),
                  'display' => $r['sign'] . ' - ' . $r['name']
               )
            );
         }
      );
      return $typeselector;
   }

   public static function checkedselector($current) {
      $SLD = Application::get()->solder();
      $checkedselector = '';
      foreach (array(0 => 'no', 1 => 'green', 3 => 'blue', 2 => 'yellow') as $v => $d) {
         $checkedselector .= $SLD->fuse(
            'option',
            array(
               'value' => $v,
               '$selected' => ($current == $v ? 'selected="selected"' : ''),
               'display' => $d
            )
         );
      }
      return $checkedselector;
   }

   /** Create a legend and JS code from the shortcuts table */
   public static function shortcuts($DB) {
      $javascript = '';
      $legend = '';
      $SLD = Application::get()->solder();
      $form = 'document.urlap';
      $DB->query_callback(
         'select * from shortcuts',
         false,
         function ($r) use (&$javascript, &$legend, $form, $SLD) {
            $legend .= $SLD->fuse('shortcut_legend', $r);

            $javascript .= $SLD->fuse('shortcut_if', array('$form' => $form, 'shortcut' => $r['shortcut'])) . '{';

            foreach (array(
               'name' => 'name',
               'timespan' => 'fortime',
               'checked' => 'checked',
               'ctype' => 'type',
               'business' => 'business',
               'clong' => 'long'
            ) as $d => $h) {
               if (!is_null($r[$d])) {
                  $javascript .= $SLD->fuse('shortcut_assign', array('$form' => $form, 'name' => $h, 'value' => $r[$d]));
               }
            }

            if (!is_null($r['value'])) {
               $javascript .= $SLD->fuse(
                  'shortcut_assign',
                  array('$form' => $form, 'name' => 'value', 'value' => ($r['value'] / 100))
               );
            }
            if (!is_null($r['accountto'])) {
               $javascript .= $SLD->fuse(
                  'shortcut_assign',
                  array(
                     '$form' => $form,
                     'name' => 'account',
                     'value' => $r['accountto'] . $r['accountfrom']
                  )
               );
            }

            $javascript .= "}\n";
         }
      );
      return array('legend' => $legend, 'js' => $javascript);
   }

   /** Returns CSS rules to style the item types based on the configuration in the database */
   public static function css_types() {
      $typescss = '';
      Application::get()->db()->query_callback(
         'select sign, css from ctypes',
         false,
         function ($r) use (&$typescss) {
            if (!is_null($r['css'])) {
               $typescss .= '.bpitem .bptype_' . strtolower($r['sign']) . '{' . $r['css'] . "}\n";
            }
         }
      );
      return $typescss;
   }

   /** Returns CSS rules to style the account based on the configuration in the database */
   /** checkedcss is applied all the time; checkednotcss is applied when the item is not checked */
   public static function css_accounts() {
      $acccss = '';
      Application::get()->db()->query_callback(
         'select accounttofrom, checkedcss, checkednotcss from accountnames where checkedcss is not null or checkednotcss is not null',
         false,
         function ($r) use (&$acccss) {
            if (!is_null($r['checkedcss'])) {
               $acccss .= '.bpitem .bpacc_' . strtolower($r['accounttofrom']) . '.bpchkd input {' . $r['checkedcss'] . "}\n";
            }
            if (!is_null($r['checkednotcss'])) {
               $acccss .= '.bpitem .bpacc_' . strtolower($r['accounttofrom']) . '.bpchkd_0 input {' . $r['checkednotcss'] . "}\n";
            }
         }
      );
      return $acccss;
   }

}

?>