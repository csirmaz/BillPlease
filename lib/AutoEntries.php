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

function addautoentries($nowtime, $DB) {
   /** Automatically add entries scheduled for every week or every month */
   
   $PREVSTAMP = $DB->querysingle('select value from costsmeta where key = ?', array('lasttime'));

   if(is_null($PREVSTAMP)){
      $DB->exec('insert into costsmeta (key,value) values (?,?)',array('lasttime',time()));
      return;
   }

   $prevday = floor($PREVSTAMP/60/60/24)*60*60*24; // day of previous check
   $curday = floor($nowtime/60/60/24)*60*60*24;

   if($prevday != $curday){ // last run not today
      $DB->query_callback('select * from autoentries', false,
         function($r)use($DB, $prevday, $curday){

            $at = $prevday + 60*60*24; // We have already checked autoentries at $prevday, so move to the next day

            while(1) { // Step through each day to now
               if($at > $curday) { break; }

               $doadd = false;
               if($r['recurs'] == 'm') {  // monthly recurrence
                  if(date('j', $at) == $r['whatday']) { $doadd = true; }
               } else {  // weekly recurrence
                  if(date('w', $at) == $r['whatday']) { $doadd = true; }
               }

               if($doadd) {
                   $item = Item::from_raw(array(
                     'year' => date('Y', $at),
                     'month' => date('n', $at),
                     'day' => date('j', $at),
                     'name' => $r['name'] . ' <auto>',
                     'value' => $r['value'] / 100,
                     'timespan' => $r['timespan'],
                     'accountto' => $r['accountto'],
                     'accountfrom' => $r['accountfrom'],
                     'checked' => $r['checked'],
                     'ctype' => $r['ctype'],
                     'business' => $r['business'],
                     'clong' => $r['clong'],
                     'istransfer' => $r['istransfer']
                   ));

                   $item->store($DB);
               }

               $at += 24*60*60;
            }
         }
      );
   }

   $DB->exec_assert_change(
      'update costsmeta set value = ? where key = ?',
      array($nowtime, 'lasttime'),
      1
   );

   return;
}


?>
