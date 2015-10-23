<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
require_once 'modules/AutoNumberPrefix/AutoNumberPrefix.php';

//update Related POS and Related Mailbox fields after delete
class PrefixEvent extends VTEventHandler {
    
	public function handleEvent($eventName, $data) {
		global $adb,$log,$currentModule;
		
		if($eventName=='corebos.filter.ModuleSeqNumber.set'){
			$check = $adb->pquery("select current from vtiger_autonumberprefix where semodule=? and prefix = ?", array($data[1], $data[2]));
			if ($adb->num_rows($check) == 0) {
				$focus = new AutoNumberPrefix();
				$focus->id = '';
				$focus->mode = '';
				$focus->column_fields['prefix']   =$data[2];
				$focus->column_fields['semodule'] =$data[1];
				$focus->column_fields['format']   =$data[3];
				$focus->column_fields['active']   =1;
				$focus->column_fields['current']  =$data[3];
				$focus->column_fields['default1'] =1;
				$focus->column_fields['assigned_user_id']=1;
				$focus->save("AutoNumberPrefix");	
				return true;
			} else if ($adb->num_rows($check) != 0) {
				$num_check = $adb->query_result($check, 0, 'current');
				if ($req_no < $num_check) {
					return false;
				} else {
					$adb->pquery("UPDATE vtiger_autonumberprefix SET active=0 where active=1 and semodule=?", array($data[1]));
					$adb->pquery("UPDATE vtiger_autonumberprefix SET current=?, active = 1 where prefix=? and semodule=?", array($data[3], $data[2], $data[1]));
					return true;
				}
			}
			$data[5]=true;
		}
		else if($eventName=='corebos.filter.ModuleSeqNumber.increment'){
			$check = $adb->pquery("select current,prefix from vtiger_autonumberprefix where semodule=? and active = 1", array($data[1]));
			$prefix = $adb->query_result($check, 0, 'prefix');
			$curid = $adb->query_result($check, 0, 'current');
			$prev_inv_no = $prefix . $curid;
			$strip = strlen($curid) - strlen($curid + 1);
			if ($strip < 0)
				$strip = 0;
			$temp = str_repeat("0", $strip);
			$req_no.= $temp . ($curid + 1);
			$adb->pquery("UPDATE vtiger_autonumberprefix SET current=? where current=? and active=1 AND semodule=?", array($data[3], $curid, $data[1]));
			return decode_html($prev_inv_no);
			$data[5]=true;
			
		}
		else if($eventName=='corebos.filter.ModuleSeqNumber.get'){
			$data[0] = $adb->pquery("SELECT prefix, current from vtiger_autonumberprefix where semodule = ? and active=1",array($currentModule));
			$data[1] = $adb->query_result($data[0],0,'prefix');
			$data[2] = $adb->query_result($data[0],0,'current');
			$data[3]=false;
			return $data;
			
		}
return $data;
}
}
?>