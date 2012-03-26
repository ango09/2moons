<?php

/**
 *  2Moons
 *  Copyright (C) 2011  Slaver
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package 2Moons
 * @author Slaver <slaver7@gmail.com>
 * @copyright 2009 Lucky <lucky@xgproyect.net> (XGProyecto)
 * @copyright 2011 Slaver <slaver7@gmail.com> (Fork/2Moons)
 * @license http://www.gnu.org/licenses/gpl.html GNU GPLv3 License
 * @version 1.3.5 (2011-04-22)
 * @info $Id$
 * @link http://code.google.com/p/2moons/
 */

if(!defined('INSIDE')) die('Hacking attempt!');

	function IsTechnologieAccessible($CurrentUser, $CurrentPlanet, $Element)
	{
		global $requeriments, $resource, $reslist;
		
		if(!isset($requeriments[$Element]))
			return true;		

		foreach($requeriments[$Element] as $ReqElement => $EleLevel)
		{
			if ((isset($CurrentPlanet[$resource[$ReqElement]]) && $CurrentPlanet[$resource[$ReqElement]] < $EleLevel) || (isset($CurrentUser[$resource[$ReqElement]]) && $CurrentUser[$resource[$ReqElement]] < $EleLevel))
				return false;
		}
		return true;
	}
?>