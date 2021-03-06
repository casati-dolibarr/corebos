<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
require_once('data/CRMEntity.php');
require_once('data/Tracker.php');

class Faq extends CRMEntity {
	var $db, $log; // Used in class functions of CRMEntity
	var $table_name = 'vtiger_faq';
	var $table_index= 'id';
	var $tab_name = Array('vtiger_crmentity','vtiger_faq','vtiger_faqcf');
	var $tab_name_index = Array('vtiger_crmentity'=>'crmid','vtiger_faq'=>'id','vtiger_faqcomments'=>'faqid','vtiger_faqcf'=>'faqid');
	var $customFieldTable = Array('vtiger_faqcf', 'faqid');

	var $entity_table = 'vtiger_crmentity';

	var $column_fields = Array();

	/** Indicator if this is a custom module or standard module */
	var $IsCustomModule = false;
	var $sortby_fields = Array('question','category','id');

	// This is the list of vtiger_fields that are in the lists.
	var $list_fields = Array(
		'FAQ Id'=>Array('faq'=>'id'),
		'Question'=>Array('faq'=>'question'),
		'Category'=>Array('faq'=>'category'),
		'Product Name'=>Array('faq'=>'product_id'),
		'Created Time'=>Array('crmentity'=>'createdtime'),
		'Modified Time'=>Array('crmentity'=>'modifiedtime')
	);

	var $list_fields_name = Array(
		'FAQ Id'=>'',
		'Question'=>'question',
		'Category'=>'faqcategories',
		'Product Name'=>'product_id',
		'Created Time'=>'createdtime',
		'Modified Time'=>'modifiedtime'
	);
	var $list_link_field= 'question';

	var $search_fields = Array(
		'Account Name'=>Array('account'=>'accountname'),
		'City'=>Array('accountbillads'=>'bill_city'),
	);
	var $search_fields_name = Array(
		'Account Name'=>'accountname',
		'City'=>'bill_city',
	);

	// Column value to use on detail view record text display
	var $def_detailview_recname = 'question';

	var $default_order_by = 'id';
	var $default_sort_order = 'DESC';

	var $mandatory_fields = Array('question','faq_answer','createdtime' ,'modifiedtime');

	// For Alphabetical search
	var $def_basicsearch_col = 'question';

	function __construct() {
		global $log;
		$this_module = get_class($this);
		$this->column_fields = getColumnFields($this_module);
		$this->db = PearDatabase::getInstance();
		$this->log = $log;
		$sql = 'SELECT 1 FROM vtiger_field WHERE uitype=69 and tabid = ? limit 1';
		$tabid = getTabid($this_module);
		$result = $this->db->pquery($sql, array($tabid));
		if ($result and $this->db->num_rows($result)==1) {
			$this->HasDirectImageField = true;
		}
	}

	function save_module($module) {
		if ($this->HasDirectImageField) {
			$this->insertIntoAttachment($this->id,$module);
		}
		//Inserting into Faq comment table
		$this->insertIntoFAQCommentTable('vtiger_faqcomments', $module);
	}


	/** Function to insert values in vtiger_faqcomments table for the specified module,
	 * @param $table_name -- table name:: Type varchar
	 * @param $module -- module:: Type varchar
	 */
	function insertIntoFAQCommentTable($table_name, $module)
	{
		global $log, $adb;
		$log->info("in insertIntoFAQCommentTable ".$table_name." module is ".$module);

		$current_time = $adb->formatDate(date('Y-m-d H:i:s'), true);

		if($this->column_fields['comments'] != '')
			$comment = $this->column_fields['comments'];
		else
			$comment = $_REQUEST['comments'];

		if($comment != '')
		{
			$params = array('', $this->id, from_html($comment), $current_time);
			$sql = "insert into vtiger_faqcomments values(?, ?, ?, ?)";
			$adb->pquery($sql, $params);
		}
	}

	/** Function to get the list of comments for the given FAQ id
	 * @param  int  $faqid - FAQ id
	 * @return list $list - return the list of comments and comment informations as a html output where as these comments and comments informations will be formed in div tag.
	 **/
	function getFAQComments($faqid)
	{
		global $log, $default_charset;
		$log->debug("Entering getFAQComments(".$faqid.") method ...");
		global $mod_strings;
		$sql = "select * from vtiger_faqcomments where faqid=?";
		$result = $this->db->pquery($sql, array($faqid));
		$noofrows = $this->db->num_rows($result);

		//In ajax save we should not add this div
		if($_REQUEST['action'] != 'FaqAjax')
		{
			$list .= '<div id="comments_div" style="overflow: auto;height:200px;width:100%;">';
			$enddiv = '</div>';
		}

		for($i=0;$i<$noofrows;$i++)
		{
			$comment = $this->db->query_result($result,$i,'comments');
			$date = new DateTimeField($this->db->query_result($result,$i,'createdtime'));
			$createdtime = $date->getDisplayDateTimeValue();
			if($comment != '')
			{
				//this div is to display the comment
				if($_REQUEST['action'] == 'FaqAjax') {
					$comment = htmlentities($comment, ENT_QUOTES, $default_charset);
				}
				$list .= '<div valign="top" style="width:99%;padding-top:10px;" class="dataField">'.make_clickable(nl2br($comment)).'</div>';
				//this div is to display the created time
				$list .= '<div valign="top" style="width:99%;border-bottom:1px dotted #CCCCCC;padding-bottom:5px;" class="dataLabel"><font color=darkred>'.$mod_strings['Created Time'];
				$list .= ' : '.$createdtime.'</font></div>';
			}
		}
		$list .= $enddiv;
		$log->debug("Exiting getFAQComments method ...");
		return $list;
	}

	/*
	 * Function to get the primary query part of a report
	 * @param - $module Primary module name
	 * returns the query string formed on fetching the related data for report for primary module
	 */
	function generateReportsQuery($module){
		$moduletable = $this->table_name;
		$moduleindex = $this->table_index;
		$query = "from $moduletable
			inner join vtiger_crmentity on vtiger_crmentity.crmid=$moduletable.$moduleindex
			left join vtiger_products as vtiger_products$module on vtiger_products$module.productid = vtiger_faq.product_id
			left join vtiger_groups as vtiger_groups$module on vtiger_groups$module.groupid = vtiger_crmentity.smownerid
			left join vtiger_users as vtiger_users$module on vtiger_users$module.id = vtiger_crmentity.smownerid
			left join vtiger_groups on vtiger_groups.groupid = vtiger_crmentity.smownerid
			left join vtiger_users on vtiger_users.id = vtiger_crmentity.smownerid
			left join vtiger_users as vtiger_lastModifiedBy".$module." on vtiger_lastModifiedBy".$module.".id = vtiger_crmentity.modifiedby";
		return $query;
	}

	/*
	 * Function to get the relation tables for related modules
	 * @param - $secmodule secondary module name
	 * returns the array with table names and fieldnames storing relations between module and this module
	 */
	function setRelationTables($secmodule){
		$rel_tables = array (
			"Documents" => array("vtiger_senotesrel"=>array("crmid","notesid"),"vtiger_faq"=>"id"),
		);
		return $rel_tables[$secmodule];
	}

	function clearSingletonSaveFields() {
		$this->column_fields['comments'] = '';
	}
}
?>
