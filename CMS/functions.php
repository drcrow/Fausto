<?php
//add separated fields functions
require_once('fields.php');

//the rest of the functions
function isAdmin(){
	if($_SESSION['user'] == ADMIN_USER && $_SESSION['pass'] == ADMIN_PASS){
		return true;
	}else{
		return false;
	}
}

function login($user, $pass){
	if($user == ADMIN_USER && $pass == ADMIN_PASS){
		$_SESSION['user'] = $user;
		$_SESSION['pass'] = $pass;
		return true;
	}else{
		return false;
	}
}

function logout(){
	$_SESSION['user'] = '';
	$_SESSION['pass'] = '';
}

function getJsonFilesList($dir){
	if ($handle = opendir($dir)){
		$thelist = array();
	    while(false !== ($file = readdir($handle))){
	        if ($file != "." && $file != ".." && strtolower(substr($file, strrpos($file, '.') + 1)) == 'json'){
	            $thelist[] = $file;
	        }
	    }
	    closedir($handle);
	}else{
		return false;
	}
	return $thelist;
}

function getArrayFromJsonFile($file, $assoc=false, $where=array()){
	$res = @file_get_contents($file);
	$res = json_decode($res, $assoc);
	if(count($where)==0){
		return $res;
	}

	foreach($where as $id=>$val){
		if($val!=''){
			$res = getFilteredArray($res, $id, $val);
		}
	}

	return $res;
}

function getFilteredArray($data, $filterId, $filterValue){
	$res = array();
	foreach($data as $id=>$row){
		
		//echo '<br>'.$row['country'].' '.$row['state'].' '.$row['city'];

		if($row[$filterId]==$filterValue){
			$res[$id] = $data[$id];
		}
	}
	return $res;
}

function getContentTypes(){
	$files = getJsonFilesList(CONTENT_TYPES_DIR);
	sort($files);
	$result = array();
	if(count($files)>0){
		foreach($files as $file){
			$result[] = getArrayFromJsonFile(CONTENT_TYPES_DIR.$file);
		}
		return $result;
	}else{
		return false;
	}
}

function getContentType($type){
	$cts = getContentTypes();
	foreach ($cts as $ct){
		if($ct->type==$type){
			return $ct;
		}
	}
	return false;
}

function getLanguagesList(){
	return explode(',', LANGUAGES);
}

function getForm($ct, $lang, $isEdit=false){
	$html = '';
	if($isEdit){
		$dataFilePath = CONTENT_DATA_DIR.$ct->type.'-'.$lang.'.json';
		$data = getArrayFromJsonFile($dataFilePath, true);
		$data = $data[$isEdit];
		//echo '<pre>'.print_r($data, true).'</pre>';
	}
	foreach($ct->fields as $field){
		$enabled = true;
		if($isEdit && @$field->index==1){
			$enabled = false;
		}
		$required = false;
		if(getIndexId($ct->type) == $field->id){
			$required = true;
		}
		$html .= getFormField($field, $lang, $enabled, @$data[$field->id], $required);
	}
	$html .= '<div class="pull-right" style="overflow:auto"><button class="btn btn-primary" type="submit">Save</button></div>';
	return $html;
}

function getFormField($field, $lang, $enabled=true, $value='', $required=false){
	$func = 'field_'.$field->type;
	if(function_exists($func)){
		return $func($field, $lang, $enabled, $value, $required);
	}else{
		return 'Field type '.$field->type.' is undefined!';
	}
}

function saveData($ct, $data){
	$langs = getLanguagesList();
	foreach($langs as $lang){
		saveDataToFile($data['type'], $lang, $data[$lang]);
	}
	return true;
}

function saveDataToFile($type, $lang, $data){
	
	$dataFilePath = CONTENT_DATA_DIR.$type.'-'.$lang.'.json';
	$actualData = getArrayFromJsonFile($dataFilePath, true);
	
	if($actualData == ''){
		$actualData = array();
	}

	//echo '<pre>'.print_r($data, true).'</pre>';

	//gets the index field id (multi)
	$indexId = @getIndexId($type);
	$indexId = @$data[$indexId];
	//gets the index field id (single)
	if($indexId==''){
		$indexId = 1;
	}

	@$actualData[$indexId] = $data;
	$actualData = json_encode($actualData);
	file_put_contents($dataFilePath, $actualData);

}

function getIndexId($type){
	$ct = getContentType($type);
	foreach($ct->fields as $field){
		if(@$field->index==1){
			return $field->id;
		}
	}
	return '';
}

function getTable($ct, $lang){

	$dataFilePath = CONTENT_DATA_DIR.$ct->type.'-'.$lang.'.json';
	$actualData = getArrayFromJsonFile($dataFilePath);

	$html = '
	<table class="table table-bordered table-striped" id="fausto-table-'.$ct->type.'">
		<thead><tr>';
	//table headers
	foreach($ct->fields as $field){
		//if(@$field->list==1){
		if(isFieldListable($ct, $field->id)){
			$html .= '<th>'.$field->name.'</th>';
		}
	}
	$html .= '<th>&nbsp;</th>';
	$html .= '<tbody>';
	//table data
	if($actualData==''){
		$actualData = array();
	}
	foreach($actualData as $row){
		$html .= '<tr>';
		foreach($row as $id=>$value){
			if(isFieldListable($ct, $id)){
				$html .= '<td>'.$value.'</td>';
			}
		
		}
		//table options buttons
		$html .= '<td class="tableOptionsCell">';
		$html .= '<a type="button" class="btn btn-success" href="?content='.$_GET['content'].'&edit='.$row->{getIndexId($ct->type)}.'"><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>';

		$html .= '&nbsp;&nbsp;';
		$html .= '<a type="button" class="btn btn-danger" href="?content='.$_GET['content'].'&delete='.$row->{getIndexId($ct->type)}.'"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span></a>';
		$html .= '</td>';
		$html .= '</tr>';
	}
	$html .= '</tbody>';
	$html .= '</table>';

	$html .= '<script>
			$(document).ready( function () {
    			$("#fausto-table-'.$ct->type.'").DataTable();
			} );
			</script>';

	return $html;
}

function isFieldListable($ct, $fieldId){
	foreach($ct->fields as $field){
		if(@$field->list == 1 && @$field->id == $fieldId){
			return true;
		}
	}
	return false;
}

function deleteRowFromJson($type, $index){

	$langs = getLanguagesList();
	foreach($langs as $lang){
		$dataFilePath = CONTENT_DATA_DIR.$type.'-'.$lang.'.json';
		$actualData = getArrayFromJsonFile($dataFilePath, true);
		unset($actualData[$index]);
		$actualData = json_encode($actualData);
		file_put_contents($dataFilePath, $actualData);
		
	}
}

function getArrayOfRelatedFields($data, $fields){
	$result = array();
	
	foreach($data as $row){
		$tempCode = '$result';
		foreach($fields as $id=>$field){
			if(trim($row[$field])!=''){
				if($id!=(count($fields)-1)){//check if (not)last field of list
					$tempCode.='["'.$row[$field].'"]';
				}else{
					$tempCode = 'if(@!in_array("'.$row[$field].'", '.$tempCode.')){'.$tempCode.'[]="'.$row[$field].'";}';
					eval($tempCode);
					//$result[] = $tempCode;
				}
			}
		}

	}
	//$result = array_map("unserialize", array_unique(array_map("serialize", $result)));
	return $result;
}
?>