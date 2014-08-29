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

/** A singleton object that stores application-level resources */
class Application {

   private static $me;
   private $db;
   private $solder;
   private $lock;
   private $first_checked;

   /** Returns the singleton Application object */
   public static function get() {
      if (isset(self::$me)) {
         return self::$me;
      }
      throw new Exception('The Application is not set up yet');
   }

   /** Sets up the Application object with the configuration. For the keys expected in the config array, see the constructor */
   public static function setup($config) {
      if (isset(self::$me)) {
         throw new Exception('The Application has already been set up');
      }
      self::$me = new self($config);
      return self::$me;
   }

   private function __construct($config) {
      $this->db = new CostsDB(new SQLite3($config['database_file']));
      $this->db->exec('PRAGMA case_sensitive_like=OFF');

      $this->solder = new Solder($config['template_file']);

      $this->lock = new CostLock($config['lock_file']);

      $this->first_checked = new FirstChecked($this->db, $this->solder);
   }

   public function db() {
      return $this->db;
   }

   public function solder() {
      return $this->solder;
   }

   public function lock() {
      return $this->lock;
   }

   public function first_checked() {
      return $this->first_checked;
   }

}

?>