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

/** Implements a lock to prevent concurrent updates */
class CostLock {
   private $file;
   private $acquired = false;

   public function __construct($file) {
      $this->file = $file;
      if (file_exists($this->file)) {
         print ('The system is currently in use by someone else!');
         exit(0);
      }
      if (!touch($this->file)) {
         print ('Sorry, could not get lock at ' . $this->file);
         exit(0);
      }
      $this->acquired = true;
   }

   public function __destruct() {
      if ($this->acquired && !unlink($this->file)) {
         print ('Error while releasing lock at ' . $this->file);
      }
   }
}

?>