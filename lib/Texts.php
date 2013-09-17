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

/** Defines text elements used frequently */
class Texts {

   /** Pre-defined texts for timespans
    *
    * Where an entry ends with an asterisk, it means it should be
    * available as an option
    */
   private static $timespantexts = array( //
   1 => 'daily*', //
   7 => 'weekly*', //
   15 => 'biweekly*', //
   30 => 'monthly*', //
   31 => 'monthly', //
   91 => 'quarterly*', //
   182 => 'half a year*');

   /** Returns the name of the system */
   public static function systitle() {
      return 'BillPlease';
   }

   /** Returns a textual representation of a timespan */
   public static function timespan($days) {
      if (!isset(self::$timespantexts[$days])) {
         return $days . ' d';
      }
      $d = self::$timespantexts[$days];
      if (substr($d, -1) == '*') {
         return substr($d, 0, -1);
      }
      return $d;
   }

   /** Returns options for a selector of timespans */
   public static function timespanselector($current) {
      $opts = '';
      $found = false;
      foreach (self::$timespantexts as $v => $d) {
         if (substr($d, -1) == '*') {
            if ($current == $v) {
               $found = true;
            }
            $opts.= '<option value="' . $v . '" ' . ($current == $v ? 'selected="selected"' : '') . '>' . substr($d, 0, -1) . '</option>';
         }
      }
      if (!$found) {
         $opts.= '<option value="' . $current . '" selected="selected">' . self::timespan($current) . '</option>';
      }
      return $opts;
   }

}

?>