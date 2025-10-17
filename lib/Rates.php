<?php
/*
   This file is part of BillPlease, a single-user web app that keeps
   track of personal expenses.
   BillPlease is Copyright 2016 by Elod Csirmaz <http://www.github.com/csirmaz>

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

class Rates {
    protected $rates = array();

    public function __construct($DB) {
        $DB->query_callback("select accounttofrom, rate from accountnames where length(accounttofrom) = 1",
        FALSE,
        function($r){
            $this->rates[$r['accounttofrom']] = $r['rate'];
        });
    }

    public function get($account) {
        return (isset($this->rates[$account]) ? $this->rates[$account] : 1.0);
    }

}

?>
