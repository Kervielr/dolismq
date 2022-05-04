<?php
/* Copyright (C) 2007-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) ---Put here your own copyright and developer email---
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *   	\file       control_list.php
 *		\ingroup    dolismq
 *		\brief      List page for control
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if ( ! $res && file_exists("../../main.inc.php")) $res       = @include "../../main.inc.php";
if ( ! $res && file_exists("../../../main.inc.php")) $res    = @include "../../../main.inc.php";
if ( ! $res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';


require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/productlot.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';

// load dolismq libraries
require_once __DIR__.'/../../class/control.class.php';

// Load translation files required by the page
$langs->loadLangs(array("dolismq@dolismq", "other", "bills", "projects", "orders", "companies", "product", "productbatch", "task"));

$action     = GETPOST('action', 'aZ09') ?GETPOST('action', 'aZ09') : 'view'; // The action 'add', 'create', 'edit', 'update', 'view', ...
$massaction = GETPOST('massaction', 'alpha'); // The bulk action (combo box choice into lists)
$show_files = GETPOST('show_files', 'int'); // Show files area generated by bulk actions ?
$confirm    = GETPOST('confirm', 'alpha'); // Result of a confirmation
$cancel     = GETPOST('cancel', 'alpha'); // We click on a Cancel button
$toselect   = GETPOST('toselect', 'array'); // Array of ids of elements selected into a list
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'controllist'; // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha'); // Go back to a dedicated page
$optioncss  = GETPOST('optioncss', 'aZ'); // Option for the css output (always '' except when 'print')

$fromtype = GETPOST('fromtype', 'alpha'); // element type
$fromid = GETPOST('fromid', 'int'); //element id

// Load variable for pagination
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) { $page = 0; }     // If $page is not defined, or '' or -1 or if we click on clear filters
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

// Initialize technical objects
$object = new Control($db);

$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->dolismq->dir_output.'/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array('controllist')); // Note that conf->hooks_modules contains array

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);
//$extrafields->fetch_name_optionals_label($object->table_element_line);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Default sort order (if not yet defined by previous GETPOST)
if (!$sortfield) { reset($object->fields); $sortfield="t.".key($object->fields); }   // Set here default search field. By default 1st field in definition. Reset is required to avoid key() to return null.
if (!$sortorder) $sortorder = "ASC";


if (!empty($fromtype)) {
	switch ($fromtype) {
		case 'project' :
			$objectLinked = new Project($db);
			$prehead = 'project_prepare_head';
			break;
		case 'product' :
			$objectLinked = new Product($db);
			$prehead = 'product_prepare_head';
			break;
		case 'productbatch' :
			$objectLinked = new Productlot($db);
			$prehead = 'productlot_prepare_head';
			break;
		case 'project_task' :
			$objectLinked = new Task($db);
			$prehead = 'task_prepare_head';
			break;
		case 'societe' :
			$objectLinked = new Societe($db);
			$prehead = 'societe_prepare_head';
			break;
	}
	$objectLinked->fetch($fromid);
	$head = $prehead($objectLinked);
	$linkedObjectsArray = array('sheet', 'user');
}

$arrayfields['t.fk_product']    = array('type' => 'integer:Product:product/class/product.class.php', 'label' => 'Product', 'enabled' => '1', 'position' => 21, 'notnull' => 0, 'visible' => 5, 'foreignkey' => 'product.rowid', 'checked' => 1);
$arrayfields['t.fk_lot']        = array('type' => 'integer:Productlot:product/stock/class/productlot.class.php', 'label' => 'Batch', 'enabled' => '1', 'position' => 22, 'notnull' => 0, 'visible' => 5, 'foreignkey' => 'productlot.rowid', 'checked' => 1);
$arrayfields['t.fk_thirdparty'] = array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'enabled' => '1', 'position' => 25, 'notnull' => 0, 'visible' => 5, 'foreignkey' => 'societe.rowid', 'checked' => 1);
$arrayfields['t.fk_project']    = array('type' => 'integer:Project:projet/class/project.class.php', 'label' => 'Projet', 'enabled' => '1', 'position' => 26, 'notnull' => 0, 'visible' => 5, 'foreignkey' => 'project.rowid', 'checked' => 1);
$arrayfields['t.fk_task']       = array('type' => 'integer:Task:projet/class/task.class.php', 'label' => 'Task', 'enabled' => '1', 'position' => 27, 'notnull' => 0, 'visible' => 5, 'foreignkey' => 'task.rowid', 'checked' => 1);

$object->fields['fk_product']    = $arrayfields['t.fk_product'];
$object->fields['fk_lot']        = $arrayfields['t.fk_lot'];
$object->fields['fk_thirdparty'] = $arrayfields['t.fk_thirdparty'];
$object->fields['fk_project']    = $arrayfields['t.fk_project'];
$object->fields['fk_task']       = $arrayfields['t.fk_task'];

$element_element_fields = array(
	'fk_thirdparty' => 'societe',
	'fk_product' => 'product',
	'fk_lot' => 'productbatch',
	'fk_project' => 'project',
	'fk_task' => 'project_task'
);
// Initialize array of search criterias
$search_all = GETPOST('search_all', 'alphanohtml') ? GETPOST('search_all', 'alphanohtml') : GETPOST('sall', 'alphanohtml');
$search = array();
foreach ($object->fields as $key => $val)
{
	if (GETPOST('search_'.$key, 'alpha') !== '') $search[$key] = GETPOST('search_'.$key, 'alpha');
}

if(!empty($fromtype)) {
	switch ($fromtype) {
		case 'sheet':
			$search['sheet'] = $fromid;
			break;
		case 'user':
			$search['fk_user_controller'] = $fromid;
			break;
	}
}

// List of fields to search into when doing a "search in all"
$fieldstosearchall = array();
foreach ($object->fields as $key => $val)
{
	if ($val['searchall']) $fieldstosearchall['t.'.$key] = $val['label'];
}

// Definition of array of fields for columns
$arrayfields = array();
foreach ($object->fields as $key => $val) {
	// If $val['visible']==0, then we never show the field
	if (!empty($val['visible'])) {
		$visible = (int) dol_eval($val['visible'], 1);
		$arrayfields['t.'.$key] = array(
			'label'=>$val['label'],
			'checked'=>(($visible < 0) ? 0 : 1),
			'enabled'=>($visible != 3 && dol_eval($val['enabled'], 1)),
			'position'=>$val['position'],
			'help'=>$val['help']
		);
	}
}

$arrayfields['t.status']['checked'] = 0;

// Extra fields
include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_array_fields.tpl.php';

$object->fields = dol_sort_array($object->fields, 'position');
$arrayfields = dol_sort_array($arrayfields, 'position');

$permissiontoread = $user->rights->dolismq->control->read;
$permissiontoadd = $user->rights->dolismq->control->write;
$permissiontodelete = $user->rights->dolismq->control->delete;

// Security check
if (empty($conf->dolismq->enabled)) accessforbidden('Module not enabled');
$socid = 0;
if ($user->socid > 0)	// Protection if external user
{
	//$socid = $user->socid;
	accessforbidden();
}
//$result = restrictedArea($user, 'dolismq', $id, '');
//if (!$permissiontoread) accessforbidden();



/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) { $action = 'list'; $massaction = ''; }
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') { $massaction = ''; }

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook))
{
	// Selection of new fields
	include DOL_DOCUMENT_ROOT . '/core/actions_changeselectedfields.inc.php';

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) // All tests are required to be compatible with all browsers
	{
		foreach ($object->fields as $key => $val) {
			$search[$key] = '';
			$_POST[$key] = '';
		}
		$toselect = '';
		$search_array_options = array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha'))
	{
		$massaction = ''; // Protection to avoid mass action if we force a new search during a mass action confirmation
	}

	// Mass actions
	$objectclass = 'Control';
	$objectlabel = 'Control';
	$uploaddir = $conf->dolismq->dir_output;
	include DOL_DOCUMENT_ROOT . '/core/actions_massactions.inc.php';
}



/*
 * View
 */

$form = new Form($db);

$now = dol_now();

//$help_url="EN:Module_Control|FR:Module_Control_FR|ES:Módulo_Control";
$help_url = '';
$title = $langs->trans("ControlList");
$morejs   = array("/dolismq/js/dolismq.js.php");
$morecss  = array("/dolismq/css/dolismq.css");

llxHeader('', $title, $help_url, '', '', '', $morejs, $morecss);
if (!empty($fromtype)) {
	print dol_get_fiche_head($head, 'control', $langs->trans("Control"), -1, $objectLinked->picto);
	dol_banner_tab($objectLinked, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);
}

if ($fromid) {
	print '<div class="underbanner clearboth"></div>';
}

$newcardbutton = dolGetButtonTitle($langs->trans('NewControl'), '', 'fa fa-plus-circle', dol_buildpath('/dolismq/view/control/control_card.php', 1).'?action=create', '', $permissiontoadd);

include_once '../../core/tpl/dolismq_control_list.tpl.php';

// End of page
llxFooter();
$db->close();
