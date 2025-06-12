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
      The account name is specified, 
      '+' means that the checked sum should also be added,
      '%' means that the sum should be included with the rate.
      '#' adds the sum and the timed sum.
      If an element starts with '(', list accounts to sum up.
      Example: A+,B;C+,D,#
   */
   
    public static function get_all_sum($DB, $nowday) {
        return ($DB->querysingle(<<<'EOQ'
            select 
                sum(
                    (accountnames.rate*1.0) * costs.value
                ) 
            from costs 
            join accountnames on costs.accountto = accountnames.accounttofrom 
            where 
                costs.accountfrom = '' 
                and costs.unixday <= ?
EOQ
            , 
            array($nowday)
        ) / 100);
   }
   
    public static function get_tail_sum($DB, $nowday) {
        // Tails of long entries (and beginnings of delayed long entries)
        // {TIMEDVALUE}*
        return ($DB->querysingle(<<<'EOQ'
            select 
                sum(
                    (accountnames.rate*1.0) * costs.value * (max(costs.unixdayto, costs.unixday) - 1 - ?) / abs(costs.timespan)
                ) 
            from costs 
            join accountnames on costs.accountto = accountnames.accounttofrom 
            where 
                costs.accountfrom = '' 
                and (
                    (costs.unixday <= ? and costs.unixdayto > (?+1))
                    or (costs.unixdayto <= ? and costs.unixday > (?+1))
                )
EOQ
            ,
            array($nowday,$nowday,$nowday,$nowday,$nowday)
        ) / 100);
   }
   
   private static function get_account_sum($DB, $acc, $nowday) {
        return (( $DB->querysingle('select sum(value) from costs where accountto = ? and unixday <= ?', array($acc, $nowday))
        -
        $DB->querysingle('select sum(value) from costs where accountfrom = ? and unixday <= ?', array($acc, $nowday))
        ) / 100);
   }
   
   private static function get_account_rate($DB, $acc) {
        return $DB->querysingle('select rate from accountnames where accounttofrom=?', array($acc));
   }

   
   public static function render($nowday){

      $SLD = Application::get()->solder();
      $DB = Application::get()->db();
      
      $sumconfig = $DB->querysingle('select value from costsmeta where key = ?', array('summaryconfig'));
      
      $Summary = '';

      foreach(explode(';', $sumconfig) as $column){ // split columns
      
         $SummaryColumn = '';
         
         foreach(explode(',', $column) as $sumacc){ // split items
  
            if($sumacc == '#'){ // sums -- DISABLED as meaningless unless everything is imported
            if(False) {

               $allsum = self::get_all_sum($DB, $nowday);               
               $outstanding = self::get_tail_sum($DB, $nowday);
               
               $SummaryColumn .= $SLD->fuse('summary_sums', array(
                  '$timedsum' => printsum($allsum - $outstanding),
                  '$sum' => printsum($allsum) // Index: costs_accfr_ud_|_v
               ));
               
            }
            } elseif(substr($sumacc, 0, 1) == '(') { // sums of accounts
            
                $part = 0;
                for($i=1; $i<strlen($sumacc); $i++){
                    $acc = substr($sumacc, $i, 1);
                    $part += self::get_account_sum($DB, $acc, $nowday) * self::get_account_rate($DB, $acc);
                }
            
                $allsum = self::get_all_sum($DB, $nowday);
            
                $SummaryColumn .= $SLD->fuse('summary_multiacc', array(
                    'names' => substr($sumacc, 1),
                    '$sum' => printsum($part),
                    'ratio' => floor($part/$allsum*100+.5)
                ));
               
            } else { // a specific account
            
                // TODO adjust for rates
               $acc = substr($sumacc, 0, 1);
               
                // DB index: costs_accto_ud_|_v
                // DB index: costs_accfr_ud_|_v
                $sum = self::get_account_sum($DB, $acc, $nowday);
                
                $checkedsum = '';
                if(substr($sumacc, 1, 1) == '+'){
                    // Index: costs_accto_c
                    $checked_to = $DB->querysingle('select sum(value) from costs where accountto = ? and checked>0', array($acc));
                    // Index: costs_accfrom_c
                    $checked_from = $DB->querysingle('select sum(value) from costs where accountfrom = ? and checked>0', array($acc));
                    $checkedsum .= $SLD->fuse('summary_account_checked', printsum(($checked_to-$checked_from)/100));
                }
                
                if(substr($sumacc,1,1)=='%'){
                    $checkedsum .= $SLD->fuse('summary_account_rate', printsum($sum * self::get_account_rate($DB, $acc)));
                }

               $SummaryColumn .= $SLD->fuse('summary_account', array(
                  'name' => $DB->querysingle('select shortname from accountnames where accounttofrom = ?', array($acc)),
                  '$sum' => printsum($sum),
                  '$checkedsum' => $checkedsum
               ));
            }
         }
         
         $Summary .= $SLD->fuse('summary_column', $SummaryColumn);
      }
      
      return $SLD->fuse('summary_wrap', $Summary);
      
   }
}

?>
