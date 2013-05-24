<?php
/**
 * Toolserver API wrapper
 *
 * Copyright (C) 2011 Merlijn van Deen <valhallasw@gmail.com>
 *
 * Although my code is normally MIT licensed, this is essentially MediaWiki-based
 * code, so I'm releasing it under the GPLv2.
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */ 

//error_reporting(E_ALL | E_STRICT);
//ini_set("display_errors", 1);

$MEDIAWIKI_BASE_DIR='/home/valhallasw/src/phase3';
$MYCNF_PATH='/home/valhallasw/.my.cnf';

/* do basic initialization */
chdir($MEDIAWIKI_BASE_DIR);
require('includes/WebStart.php');

/* do mediawiki configuration */
/* first retrieve wiki information based on domain */
$dom = $wgRequest->getText('wiki_domain');

$db = new DatabaseMysql($wgCacheDBserver, $wgCacheDBuser, $wgCacheDBpassword, 'toolserver');
$row = $db->selectRow('wiki', array('dbname', 'lang', 'is_sensitive', 'domain'), array('domain'=>$dom, 'is_closed'=>0), __METHOD__);

if (!$row) {
  wfHttpError(500, 'Server error', 'No wiki selected. Please use api.php?wiki_domain=en.wikipedia.org&...');
  return;
}

$dbname = $row->dbname;
$wgLanguageCode = $row->lang;
$wgVersion .= $dbname;
$wgSitename .= $dbname;
$wgDBname = $dbname;
$wgDBserver = str_replace('_', '-', $dbname) . '.rrdb';

/* then determine get non-standard namespaces */
$nsinfo = $db->select('namespacename', array('ns_id', 'ns_name', 'ns_type'), array("dbname" => $dbname, "((ns_id=4 OR ns_id=5) AND ns_type='primary') OR ns_id>=100 OR ns_type='alias'"), __METHOD__);

while($row = $nsinfo->fetchObject()) {
        $row->ns_name = str_replace(" ", "_", $row->ns_name);
	if ($row->ns_type == 'alias') {
		$wgNamespaceAliases[$row->ns_name] = $row->ns_id;
	} elseif ($row->ns_id >= 100) {
		$wgExtraNamespaces[$row->ns_id] = $row->ns_name;
	} elseif ($row->ns_id == 4) {
		$wgMetaNamespace = $row->ns_name;
	} elseif ($row->ns_id == 5) {
		$wgMetaNamespaceTalk = $row->ns_name;
	} else {
		wfHttpError(500, 'Server error', 'Error in namespace resolution');
		return;
	}
}
$db->close();

/* and chainload the real api */
require('api.php');
