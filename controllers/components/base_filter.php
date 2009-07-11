<?php
/*
 * phtagr.
 * 
 * Multi-user image gallery.
 * 
 * Copyright (C) 2006-2009 Sebastian Felis, sebastian@phtagr.org
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2 of the 
 * License.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

class BaseFilterComponent extends Object {

  var $components = array();
  var $controller = null;

  var $Manager = null;
  var $Media = null;
  var $MyFile = null;

  function startup(&$controller) {
    $this->controller =& $controller;
  }

  function init(&$manager) {
    if ($manager->controller) {
      $this->controller =& $manager->controller;
    }
    $this->Manager =& $manager;
    return true;
  }

  function getName() {
    return false;
  }

  function getExtensions() {
    return false;
  }
  
  function read($file, $media = false, $options = array()) {
    return false;
  }

  function write($file, $media = false, $options = array()) {
    return false;
  }
}

?>
