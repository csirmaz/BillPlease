<?php
/*
   This file is part of BillPlease, a single-user web app that keeps
   track of personal expenses.
   BillPlease is Copyright 2015 by Elod Csirmaz <http://www.github.com/csirmaz>

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

/** This class provides a function to render the account summaries */
class Summary {

   /*
      Summary config
      --------------
      The configuration is stored in the costsmeta table as a string using the key 'summaryconfig'.
      Entries are separated by commas; columns by semicolons.
      The account name is specified, '+' means that the checked sum should also be added.
      '#' adds the sum and the timed sum.
      Example: A+;B+,C,#
   */
   
   public static function render($nowday){

      $SLD = Application::get()->solder();
      $DB = Application::get()->db();
      
      $sumconfig = $DB->querysingle('select value from costsmeta where key = ?', array('summaryconfig'));
      
      $Summary = '';

      foreach(explode(';', $sumconfig) as $column){ // split columns
      
         $SummaryColumn = '';
         
         foreach(explode(',', $column) as $sumacc){ // split items
  
            if($sumacc == '#'){ // sums

               $allsum = $DB->querysingle('select sum(value) from costs where accountfrom = \'\' and unixday <= ?', array($nowday));
               // Tails of long entries
               $outstanding = $DB->querysingle(
                  'select sum((value*1.0)*(unixdayto-1-?)/timespan) from costs where accountfrom = \'\' and unixday <= ? and unixdayto > (?+1)',
                  array($nowday,$nowday,$nowday)
               );
               
               $SummaryColumn .= $SLD->fuse('summary_sums', array(
                  '$timedsum' => printsum(($allsum - $outstanding) / 100),
                  '$sum' => printsum($allsum / 100) // Index: costs_accfr_ud_|_v
               ));
               
            } else { // a specific account
            
               $thisacc = substr($sumacc, 0, 1);
               $SummaryColumn .= $SLD->fuse('summary_account', array(
                  'name' => $DB->querysingle('select shortname from accountnames where accounttofrom = ?', array($thisacc)),
                  '$sum' => printsum($DB->accountsum($thisacc, $nowday)),
                  '$checkedsum' => substr($sumacc, 1, 1) != '+' ? '' :
                     $SLD->fuse('summary_account_checked', printsum($DB->accountsum_checked($thisacc)))
               ));
            }
         }
         
         $Summary .= $SLD->fuse('summary_column', $SummaryColumn);
      }
      
      return $SLD->fuse('summary_wrap', $Summary);
      
   }
}

?>