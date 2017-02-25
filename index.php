<?php
/*
   This file is part of BillPlease, a single-user web app that keeps
   track of personal expenses.
   BillPlease is Copyright 2017 by Elod Csirmaz <http://www.github.com/csirmaz>

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

header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() - 60*60*24));
header("Pragma: no-cache");
header("Cache-Control: no-cache, must-revalidate");

// TODO Use OS-indep directory separator

$LIBPATH = dirname(__FILE__) . '/lib';

require $LIBPATH . '/sqlbrite/sqlbrite.php';
require $LIBPATH . '/solder/Solder.php';
require $LIBPATH . '/CostsDB.php';
require $LIBPATH . '/CostLock.php';
require $LIBPATH . '/UnixDay.php';
require $LIBPATH . '/Application.php';
require $LIBPATH . '/Texts.php';
require $LIBPATH . '/Request.php';
require $LIBPATH . '/Control.php';
require $LIBPATH . '/View.php';
require $LIBPATH . '/ItemData.php';
require $LIBPATH . '/Rates.php';
require $LIBPATH . '/Item.php';
require $LIBPATH . '/Day.php';
require $LIBPATH . '/Html.php';
require $LIBPATH . '/CType.php';
require $LIBPATH . '/FirstChecked.php';
require $LIBPATH . '/Summary.php';


?>