<?php
/*	
	Project Name: PIG Api - Php ImaGe Manager Api
	Author: Joey Albert Abano
	Open Source Resource: GITHub

	The MIT License (MIT)

	Copyright (c) 2015-2016 Joey Albert Abano		

	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.

	------------------------------------------------------------------------------------------------------------------


*/

define('_DBFILEPATH','db/pig.db'); 
define('_TARGETURL','http://localhost/pig/processed/'); 

/**
 *  @Classname: PigSqlite
 *  @Extends: SQLite3
 *  @Description:
 *    Performs database functions
 *
 */
class PigSqlite extends SQLite3 {

	/*
		@Method __construct
	    @Description
	      initially test for database connection, and perform initialization
	 */ 	
	function __construct() {
		$this->open(_DBFILEPATH);
		$this->busyTimeout(5000);
		$this->close();		
	}

	/*
		@Method initialize
		@Parameter
		  $config 
	    @Description
	      Initialized the database
	 */ 	
	function initialize($config) {
		$this->opendb();

		if( $config['droptables']) { // force table drop
			copy(_DBFILEPATH,_DBFILEPATH . '_' . filemtime(_DBFILEPATH) ); // create a backup			 
			$this->drop_tables(); // drop tables
		}

		$ret = $this->query('SELECT count(name) as count FROM sqlite_master WHERE type="table" AND name in ("TBL_IMAGE","TBL_METADATA")');
		$row = $ret->fetchArray(SQLITE3_ASSOC);
		if( $row['count'] == 0 ) { // create tables if non-existent
			$this->create_tables();
		}		

		$this->close();
	}	

	/*
		@Method opendb
	    @Description
	      Open database connection
	 */	
	function opendb() {
		$this->open(_DBFILEPATH);
	}

	
	/*
		@Method get
		@Parameter $org_filepath string, image original defined file path
	    @Description
	      retrieve a specified image information
	    @Return
	      $image array map
	 */	
	function get($org_filepath, $offset = 0, $limit = 1000) {
		$this->opendb();

		$total_counts = 0;
		$sql = 'TBL_IMAGE WHERE UPPER(ORG_FILEPATH) LIKE :ORG_FILEPATH';
		
		// retrieve total counts
		$stmt = $this->prepare('SELECT COUNT(*) AS COUNT FROM ' . $sql);
		$stmt->bindValue(':ORG_FILEPATH', strtoupper('%'.$org_filepath.'%'), SQLITE3_TEXT);
		$result = $stmt->execute();	
		$result = $stmt->execute();			
		while($row = $result->fetchArray(SQLITE3_ASSOC) ) {						
			$total_counts = $row['COUNT'];			
			break;
		}

		// retrieve results
		$stmt = $this->prepare('SELECT IMAGE_ID,DESCRIPTION,CODE_128,CODE_512,CODE_1024,FILEMTIME FROM ' . $sql);
		$stmt->bindValue(':ORG_FILEPATH', strtoupper('%'.$org_filepath.'%'), SQLITE3_TEXT);
		$sql = $sql . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
		$result = $stmt->execute();	

		$arr = array();		
		while($row = $result->fetchArray(SQLITE3_ASSOC) ) {		
			$row['ICON'] = _TARGETURL . $row['FILEMTIME'] . '/' . $row['CODE_128'];
			$row['THUMB'] = _TARGETURL . $row['FILEMTIME'] . '/' . $row['CODE_512'];
			$row['FULL'] = _TARGETURL . $row['FILEMTIME'] . '/' . $row['CODE_1024'];			
			unset($row['CODE_128']); unset($row['CODE_512']); unset($row['CODE_1024']); unset($row['FILEMTIME']);
			array_push($arr, $row);						
		}

		$this->close();

		return '{"total_rows":'.$total_counts.',"num_rows":'.$limit.',"current_page":'. (ceil($offset/$limit)+1) .',"data":'.json_encode($arr).'}';
	}

	
}	



/**
 *  @Inline: Controller
 *  @Description:
 *    Returns a json content data
 *
 */
header('Content-Type: application/json');

if( isset($_POST['r']) ) {
	$pigsqlite = new PigSqlite();	
	if( isset($_POST['o']) && isset($_POST['l']) ) {
		echo $pigsqlite->get( $_POST['r'], $_POST['o'], $_POST['l'] );	
	}
	else if( isset($_POST['o']) ) {
		echo $pigsqlite->get( $_POST['r'], $_POST['o'] );	
	}
	else {
		echo $pigsqlite->get( $_POST['r'] );	
	}
}
else {
	echo '{"access":"unavailable"}'; 	
}
?>
