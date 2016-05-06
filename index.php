<?php 
/*	
	Project Name: PIG - Php ImaGe Manager
	Author: Joey Albert Abano
	Open Source Resource: GITHub

	The MIT License (MIT)

	Copyright (c) 2015 Joey Albert Abano		

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

	Simple Php ImaGe manager. 

	- images will be dump in a directory <original>, PIG will scan directory store information in an sqlite database
	- PIG will generate thumbnails and resized images on target directory


*/
define('_SOURCEPATH', 'raw/'); // source directory
define('_TARGETPATH','processed/'); // processed directory
define('_DBFILEPATH','db/pig.db'); // database location
define('_LIST_NUMROW',14); // number of thumbnails per page. 
define('_TARGETURL','http://localhost/pig/processed/'); // processed url path


/**
 *  PigProcess
 *
 *
 */
class PigProcess {

	private $source_path;
	private $processed_path;

	function __construct() {
	}

	// rebuild image reference between $source and $processed, check new files based on filename and date 
	function build($source_path, $processed_path, $config = array('droptables'=>FALSE)) {
		// check the list of files in the database
		// generate the list of new files in the raw directory. generate the list of missing files.
		// 

		// get the last build from database
		// perform scale_image using the last processed datetime as reference
		$this->source_path = $source_path;
		$this->processed_path = $processed_path;

		// create directory if not existing		
		if ( !is_dir($processed_path) ) {
			mkdir($processed_path, 0700, true);
		}


		// 1. run the process in the background. recommended that it is shot at an ajax request. check the status based on the db
		ignore_user_abort(true); 
		set_time_limit(0);

		$db = new PigSqlite();
		$db->initialize($config);
		$db->opendb();
		$this->scale_image_directory($db, $this->source_path); // YYYY-MM-DD strtotime("2002-12-30")
		$db->close();


	}

	// full build, removing all files in the $processed directory. warn: delete database reference and processed path. 
	function rebuild($source_path, $processed_path) {		
		$config = array('droptables'=>TRUE);

		if ( is_dir($processed_path) ) {
    		$this->rrmdir( $processed_path ); // single depth 
		}

		
		$this->build($source_path, $processed_path, $config);
	}

	function list_images($page_no=1) {
		// retrieve list in json format
		//{totalpages, currentpage, totalfiles, data:[{},] }

	}

	// $path = directory to be scanned , $processed_datetime = time format, to convert use strtotime
	private function scale_image_directory($db, $path, $processed_datetime = FALSE) {
		$sql = '';
		foreach (new DirectoryIterator($path) as $filename) {
		    if($filename->isDir() && !$filename->isDot()) {
		        $this->scale_image_directory($db, $filename->getPathname() . '/' , $processed_datetime);
		    }
		    else if($filename->isFile() && ( !$processed_datetime || $processed_datetime<=filemtime($filename->getPathname()) )  
		    	&& $imgsize = @getimagesize($filename->getPathname()) ) {		 
		    		$imagedb = $this->scale_image($db, $imgsize[0], $imgsize[1], $filename, $filename->getPathname());		    	
		    }
		}
	}


	private function scale_image($db, $imgwidth, $imgheight, $filename, $filepath) {
		
    	$uid = strtoupper(uniqid()); // generate unique identifier
    	$timg_uid = 'THB_'.$uid.'.JPG';
    	$wimg_uid = 'WEB_'.$uid.'.JPG';
    	$oimg_uid = 'IMG_'.$uid.'.JPG';
    	$filemtime = filemtime($filepath);

    	$imagedb = $db->save(array('IMAGE_ID'=>$uid,'DESCRIPTION'=>'','WIDTH'=>$imgwidth,'HEIGHT'=>$imgheight,'ORG_FILENAME'=>$filename,
    		'ORG_FILEPATH'=>$filepath,'CODE_128'=>$timg_uid,'CODE_512'=>$wimg_uid,'CODE_1024'=>$oimg_uid,'FILEMTIME'=>$filemtime));

    	if( $imagedb != FALSE ) { 
			if( $imgwidth > $imgheight ) { // landscape	    		
				$resource = imagescale( imagecreatefromjpeg($filepath) , 128);
					imagejpeg($resource , $this->processed_path . $timg_uid);	
					$resource = imagescale( imagecreatefromjpeg($filepath) , 512); //512x320
					imagejpeg($resource , $this->processed_path . $wimg_uid);			  			
					$resource = imagescale( imagecreatefromjpeg($filepath) , 1280);//1280x800
					imagejpeg($resource , $this->processed_path . $oimg_uid);	
			}
			else { // portrait
				$resource = imagescale( imagecreatefromjpeg($filepath) , 128);
					imagejpeg($resource , $this->processed_path . $timg_uid);	
					$resource = imagescale( imagecreatefromjpeg($filepath) , 320); 
					imagejpeg($resource , $this->processed_path . $wimg_uid);			  			
					$resource = imagescale( imagecreatefromjpeg($filepath) , 800);
					imagejpeg($resource , $this->processed_path . $oimg_uid);	
			}	
    	}

    	return $imagedb;
	}

	// remove directory
	function rrmdir($dir,$depth=FALSE) { 
		if (is_dir($dir)) { 
			$objects = scandir($dir);
			foreach ($objects as $object) { 
				if ($object != "." && $object != "..") { 
					if (filetype($dir."/".$object) == "dir" && $depth==TRUE) rrmdir($dir."/".$object); else unlink($dir."/".$object); 
				} 
			}
		} 
		reset($objects); 
		rmdir($dir); 
   	} 
	
	function fetch() {
		$db = new PigSqlite();
		$db->opendb();
		echo $db->fetch(array(), (intval($_POST['p'])-1) * _LIST_NUMROW );
		$db->close();
	}

	function modify() {
		$db = new PigSqlite();
		$db->opendb();
		$image = array();
		$image['IMAGE_ID'] = getpost('i',' ');
		$image['DESCRIPTION'] = getpost('d',' ');
		$db->modify($image);
		$db->close();
		
	}

	function upload($file) {
		$success = false;
		$target_file = _SOURCEPATH . basename($file["name"]);
		$imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);
    	$check = getimagesize($file["tmp_name"]);
    	if($check !== false && move_uploaded_file($file["tmp_name"], $target_file) ) {        
    		$success = true;
    	}
    	else {
    		$success = false;
    	} 
    	return $success;       	
	}
	
}

/**
 *
 *
 */
class PigSqlite extends SQLite3 {
	// image: image_id, original_filename, original_filepath, code_filename, 
	// metadata: image_id
	/*
	 * i. Initially test for database connection, and perform initialization
	 */
	function __construct() {
		$this->open(_DBFILEPATH);
		$this->busyTimeout(5000);
		$this->close();		
	}
	function create_tables () {
		$sql_create_tables =<<<EOF
			CREATE TABLE IF NOT EXISTS TBL_IMAGE (
			  IMAGE_ID        TEXT     	  NOT NULL,
			  DESCRIPTION     TEXT,
			  WIDTH           INT,
			  HEIGHT          INT,
			  ORG_FILENAME    TEXT,
			  ORG_FILEPATH    TEXT,
			  CODE_128        CHAR(20),
			  CODE_512        CHAR(20),
			  CODE_1024       CHAR(20),			  
			  FILEMTIME       CHAR(12),
			  CREATED_DTTM    DATETIME    NOT NULL   DEFAULT   CURRENT_TIMESTAMP);

			CREATE UNIQUE INDEX IF NOT EXISTS TBL_IMAGE_IMAGE_ID ON TBL_IMAGE (IMAGE_ID);
			CREATE UNIQUE INDEX IF NOT EXISTS TBL_IMAGE_ORG_FILEPATH ON TBL_IMAGE (ORG_FILEPATH);			

			CREATE TABLE IF NOT EXISTS TBL_METADATA (
			  IMAGE_ID        TEXT        NOT NULL,
			  METANAME        TEXT     	  NOT NULL,
			  VALUE           TEXT     	  NOT NULL );
			
			CREATE INDEX IF NOT EXISTS TBL_METADATA_IMAGE_ID ON TBL_METADATA (IMAGE_ID);
			CREATE INDEX IF NOT EXISTS TBL_METADATA_METANAME ON TBL_METADATA (METANAME);

EOF;

		$ret = $this->exec($sql_create_tables);		
	}
	function drop_tables () {
		$sql_drop_tables =<<<EOF
			PRAGMA writable_schema = 1;
			DELETE FROM sqlite_master WHERE TYPE IN ('table', 'index', 'trigger');
			PRAGMA writable_schema = 0;
			VACUUM;
			PRAGMA INTEGRITY_CHECK;
EOF;
		$ret = $this->exec($sql_drop_tables);		
	}

	function initialize($config) {
		$this->opendb();

		if( $config['droptables']) {
			$this->drop_tables();
		}

		$ret = $this->query('SELECT count(name) as count FROM sqlite_master WHERE type="table" AND name in ("TBL_IMAGE","TBL_METADATA")');
		$row = $ret->fetchArray(SQLITE3_ASSOC);
		if( $row['count'] == 0 ) {
			$this->create_tables();
		}		

		$this->close();
	}	

	function opendb() {
		$this->open(_DBFILEPATH);
	}

	function save($image = array()) {
		
		$imagedb = $this->get($image['ORG_FILEPATH']);

		if( count( $imagedb ) > 0  && $imagedb['FILEMTIME']!=$image['FILEMTIME'] ) {
			$sql = 'UPDATE TBL_IMAGE SET DESCRIPTION=:DESCRIPTION,WIDTH=:WIDTH,HEIGHT=:HEIGHT,FILEMTIME=:FILEMTIME WHERE ORG_FILEPATH=:ORG_FILEPATH';
			$stmt = $this->prepare($sql);
			$stmt->bindValue(':DESCRIPTION', $image['DESCRIPTION'], SQLITE3_TEXT);
			$stmt->bindValue(':WIDTH', $image['WIDTH'], SQLITE3_INTEGER);
			$stmt->bindValue(':HEIGHT', $image['HEIGHT'], SQLITE3_INTEGER);
			$stmt->bindValue(':FILEMTIME', $image['FILEMTIME'], SQLITE3_TEXT);
			$result = $stmt->execute();					
			return $this->get($image['ORG_FILEPATH']);		
		}
		else if( count( $imagedb ) == 0 ) {
			$sql = 'INSERT INTO TBL_IMAGE (IMAGE_ID,DESCRIPTION,WIDTH,HEIGHT,ORG_FILENAME,ORG_FILEPATH,CODE_128,CODE_512,CODE_1024,FILEMTIME)
			  VALUES(:IMAGE_ID,:DESCRIPTION,:WIDTH,:HEIGHT,:ORG_FILENAME,:ORG_FILEPATH,:CODE_128,:CODE_512,:CODE_1024,:FILEMTIME)';
			$stmt = $this->prepare($sql);
			$stmt->bindValue(':IMAGE_ID', $image['IMAGE_ID'], SQLITE3_TEXT);
			$stmt->bindValue(':DESCRIPTION', $image['DESCRIPTION'], SQLITE3_TEXT);
			$stmt->bindValue(':WIDTH', $image['WIDTH'], SQLITE3_INTEGER);
			$stmt->bindValue(':HEIGHT', $image['HEIGHT'], SQLITE3_INTEGER);
			$stmt->bindValue(':ORG_FILENAME', $image['ORG_FILENAME'], SQLITE3_TEXT);
			$stmt->bindValue(':ORG_FILEPATH', $image['ORG_FILEPATH'], SQLITE3_TEXT);
			$stmt->bindValue(':CODE_128', $image['CODE_128'], SQLITE3_TEXT);
			$stmt->bindValue(':CODE_512', $image['CODE_512'], SQLITE3_TEXT);
			$stmt->bindValue(':CODE_1024', $image['CODE_1024'], SQLITE3_TEXT);
			$stmt->bindValue(':FILEMTIME', $image['FILEMTIME'], SQLITE3_TEXT);
			$result = $stmt->execute();		
			return $this->get($image['ORG_FILEPATH']);
		}

		return FALSE;
	}

	function modify($image) {		
		$sql = 'UPDATE TBL_IMAGE SET DESCRIPTION=:DESCRIPTION WHERE IMAGE_ID=:IMAGE_ID'; 		
		$stmt = $this->prepare($sql);			
		$stmt->bindValue(':DESCRIPTION', $image['DESCRIPTION'], SQLITE3_TEXT);
		$stmt->bindValue(':IMAGE_ID', $image['IMAGE_ID'], SQLITE3_TEXT);
		$result = $stmt->execute();							
	}

	function get($org_filepath) {
		$sql = 'SELECT * FROM TBL_IMAGE WHERE ORG_FILEPATH=:ORG_FILEPATH';
		$stmt = $this->prepare($sql);
		$stmt->bindValue(':ORG_FILEPATH', $org_filepath, SQLITE3_TEXT);
		$result = $stmt->execute();	

		$arr = array();		
		while($row = $result->fetchArray(SQLITE3_ASSOC) ) {						
			foreach($row as $key=>$value) {
				$arr[$key] = $value;
			}
			return $arr;
		}
		return $arr ;		
	}

	function fetch($labels = array(), $offset = 0, $orderby = 'CREATED_DTTM', $orderdir = 'DESC') {

		$sql = '';
		$total_counts = '0';

		if( count($labels) > 0 ) {
			$label_param = '';
			foreach ($labels as $key => $value) {
				$label_param = $label_param . '"' . $value . '",';
			}
			$label_param = trim($label_param, ",");
			$sql = $sql . 'TBL_IMAGE A,TBL_METADATA B WHERE A.IMAGE_ID=B.IMAGE_ID WHERE B.METANAME="LABELS" AND B.VALUE IN (' . $label_param . ') ORDER BY CREATED_DTTM DESC';
		}
		else {
			$sql = $sql . 'TBL_IMAGE ORDER BY CREATED_DTTM DESC';
		}

		$stmt = $this->prepare( 'SELECT COUNT(*) AS COUNT FROM ' . $sql);
		$result = $stmt->execute();			
		while($row = $result->fetchArray(SQLITE3_ASSOC) ) {						
			$total_counts = $row['COUNT'];			
			break;
		}

		$sql = $sql . ' LIMIT ' . _LIST_NUMROW . ' OFFSET ' . $offset;

		$stmt = $this->prepare( 'SELECT * FROM ' . $sql);
		$result = $stmt->execute();			

		$arr = array();		
		while($row = $result->fetchArray(SQLITE3_ASSOC) ) {						
			array_push($arr, $row);			
		}

		return '{"total_rows":'.$total_counts.',"num_rows":'._LIST_NUMROW.',"current_page":'. (ceil($offset/_LIST_NUMROW)+1) .',"data":'.json_encode($arr).'}';
	}

}	



// controller section

$pig = new PigProcess();
switch ( getpost( 'a' ) ) {
	case 'b': // build
		$pig->build(_SOURCEPATH,_TARGETPATH);
		echo '{"response":"success"}';	
		exit(1);
	case 'r': // rebuild
		$pig->rebuild(_SOURCEPATH,_TARGETPATH);
		echo '{"response":"success"}';	
		exit(1);
	case 'j': // request for the json list
		$pig->fetch();
		exit(1);
	case 'u': // update image details information
		$pig->modify();
		echo '{"response":"success"}';	
		exit(1);
	case 'p': // upload images
		//
		$fc = 0;
		$mg = '';
		while ( isset( $_FILES["file".$fc] ) ) {
			if ( $pig->upload( $_FILES["file".$fc] ) ) {
				$mg = $mg . '"'.$fc.'":"success",';
			}
			else {
				$mg = $mg . '"'.$fc.'":"failed",';
			}			
			$fc++;

		}
		echo '{"response":{'.trim($mg, ",").'}';	
		exit(1);
	default:
		# code...
		break;
}




function getpost( $str , $ret = FALSE) {
	return (isset($_POST[$str])) ?  $_POST[$str] : $ret;
}


?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="PIG Manager - Php ImaGe Manager">
    <meta name="author" content="Joey Albert Abano">

    <title>PIG Manager - Php ImaGe Manager</title>

    <link rel="icon" href="//getbootstrap.com/favicon.ico">
	<link href="//ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css" rel="stylesheet" type='text/css'>
	<link href="//fonts.googleapis.com/css?family=Inconsolata:400,700" rel='stylesheet' type='text/css'>	
	<link href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css" integrity="sha512-dTfge/zgoMYpP7QbHy4gWMEGsbsdZeCXz7irItjcC3sPUFtf0kuFbDz/ixG7ArTxmDjLXDmezHubeNikyKGVyQ==" rel="stylesheet" crossorigin="anonymous">
	<link href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap-theme.min.css" integrity="sha384-aUGj/X2zp5rLCbBxumKTCw2Z50WgIr1vs/PFN4praOTvYXWlVyh2UtNUU0KAUhAX" rel="stylesheet" crossorigin="anonymous">

	<script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js" type="text/javascript"></script>		
	<script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js" type="text/javascript"></script>  	
  	<script src="//cdn.ckeditor.com/4.5.5/standard/ckeditor.js"></script>
	<script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js" type="text/javascript" integrity="sha512-K1qjQ+NcF2TYO/eI3M6v8EiNYZfA95pQumfvcVrTHtwQVDG+aHRqLi/ETn2uB+1JqwYqVG3LIvdm9lj6imS/pQ==" crossorigin="anonymous"></script>

	<style type="text/css">
		body {background: #222; color: #eee; }
		p.ui-img-description{ font-size:11px; margin:4px 0px 0px 0px; }
		div.image-content-modal .img-responsive { display:block; text-align:center; margin: 0 auto; }
		textarea.ui-label-inputs { background: #222; border:none; width:100%; font-size:11px; }
		p.ui-url-link { font-size:11px; }

		div.modal-content{ background: #222; }

		div.ui-item-photo{ border: solid 1px #888; display: inline-block; height: 178px; margin: 4px; width: 138px; 
			 vertical-align: top; padding: 4px; border-radius: 4px;
		}
					
			.btn-file {
			    position: relative;
			    overflow: hidden;
			}
			.btn-file input[type=file] {
			    position: absolute;
			    top: 0;
			    right: 0;
			    min-width: 100%;
			    min-height: 100%;
			    font-size: 100px;
			    text-align: right;
			    filter: alpha(opacity=0);
			    opacity: 0;
			    outline: none;
			    background: white;
			    cursor: inherit;
			    display: block;
			}
	</style>

	<script type="text/javascript">
		var URLP = '<?php echo _TARGETURL; ?>';

		$(document).ready(function(){
			list_images(1);
			$('#btn-build').click( ajax_build );
			$('#btn-rebuild').click( ajax_rebuild );

		});

		function ajax_loading(valeur) {

			
			
			if ( valeur < 0) {
				$('.progress-bar').addClass('progress-bar-danger');				
				$('.progress-bar').removeClass('progress-bar-success');					
				$('.progress-value').text(valeur + '% Process Aborted (Failed)');
			}
			else if( valeur > 95) {
				$('.progress-bar').addClass('progress-bar-success');
				$('.progress-value').text(valeur + '% Complete (Success)');
				$('.progress-bar').css('width', valeur+'%').attr('aria-valuenow', valeur);  
			}			
			else {
				$('.progress-bar').removeClass('progress-bar-success');					
				$('.progress-value').text(valeur + '% Loading...');
				$('.progress-bar').css('width', valeur+'%').attr('aria-valuenow', valeur);  
			}

			return valeur;
		}

		function ajax_build(e) {

			$('#loading-view-modal').modal({backdrop: 'static', keyboard: false, show:true});

			var ld,tmr=10;
			ajax_loading(tmr);
			
			ld = setInterval(function(){
				tmr = tmr <= 90 ? ajax_loading(tmr)+1 : tmr;
			},60+tmr);
			
			$.ajax({ url: "index.php", dataType:'json', method:'POST', data:'a=b', cache:false, context: document.body })
			.done(function(rs) { 
				setTimeout(function(){ 
					clearInterval(ld);
					ajax_loading(90);
					setTimeout(function(){ 
						ajax_loading(100);
						list_images();
						$('#loading-view-modal').modal('toggle');
					}, 600);
				}, 2000);
			})
			.fail(function(ex){ 
				console.error(ex);
				ajax_loading(-1);
			});
		}

		function ajax_rebuild() {

			$('#loading-view-modal').modal({backdrop: 'static', keyboard: false, show:true});

			var ld,tmr=1;
			ajax_loading(tmr);

			ld = setInterval(function(){
				tmr = tmr <= 90 ? ajax_loading(tmr)+1 : tmr;
			},300+(tmr*2));

			$.ajax({ url: "index.php", dataType:'json', method:'POST', data:('a=r'), cache:false, context: document.body })
			.done(function(rs) { 
				setTimeout(function(){ 
					clearTimeout(ld);
					ajax_loading(90);
					setTimeout(function(){ 
						ajax_loading(100);
						list_images();
						$('#loading-view-modal').modal('toggle');
					}, 600);	
				}, 2000);			
			})
			.fail(function(ex){ 
				console.error(ex);
				ajax_loading(-1);
			});
		}

		// list images
		function list_images(page) {

			page = parseInt(page);

			$.ajax({ url: "index.php", dataType:'json', method:'POST', data:('a=j&p='+page), cache:false, context: document.body })
			.done(function(rs) { 
				console.info(rs); 

				// clear
				$('#ui-container-photo').empty();
				$('.pagination').empty();

				// generate image list
				var cnt = document.getElementById('ui-container-photo');
				for(i=0;i<rs.data.length;i++) {
					var d = document.createElement('div')
					var m = document.createElement('img')
					var p = document.createElement('p')	
					d.setAttribute('class','ui-item-photo');
					m.setAttribute('class','img-rounded');
					m.setAttribute('src',URLP+rs.data[i].CODE_128);
					p.textContent=rs.data[i].DESCRIPTION;
					p.setAttribute('class','ui-img-description');					

					$.data(m,rs.data[i]); // store date in the element

					d.appendChild(m);
					d.appendChild(p);
					cnt.appendChild(d);
				}
				
				// generate pagination
				//$('ul.pagination').hide();
				for(i=1;i<=Math.ceil(rs.total_rows/rs.num_rows);i++) {
					var l = document.createElement('li');
					var a = document.createElement('a');
					if( i == rs.current_page ) { l.setAttribute('class','active');}
					a.setAttribute('href','#');
					a.textContent=i;
					l.appendChild(a);
					$('ul.pagination').append(l);					
				}
				
				$('div.ui-item-photo img').click(function(e){
					e.stopPropagation();

					var d = $.data(e.target);

					$('#image-view-modal').modal('show');
					$('#image-content-modal').empty();

					var g = document.createElement('img');
					g.setAttribute('src',URLP+d.CODE_1024);
					g.setAttribute('class','img-responsive');
					$('#image-content-modal').append(g);

					var aimg = $('#image-description-modal p.ui-url-link a');
					console.debug(aimg);
					aimg.attr('href',URLP+d.CODE_1024);
					aimg.text(URLP+d.CODE_1024);		


					var desc = $('#image-description-modal textarea[name="image-description"]');
					desc.val(d.DESCRIPTION);
					//$.data(desc.clone(true),d);


					var imgt;
					desc.unbind('keyup');
					desc.keyup(function(e){
						e.stopPropagation();
						clearTimeout(imgt);
						imgt = setTimeout(function(){
						console.debug('target value:',e.target.value);
						
						$.ajax({ url: "index.php", dataType:'json', method:'POST', data:('a=u&i='+d.IMAGE_ID+'&d='+e.target.value), cache:false, context: document.body })
							.done(function(rs) { 
								console.info(rs);
								list_images(page);
							})
							.fail(function(ex) { 
								console.debug(ex);
							});

					},1000);

					
						
					

				});


					//console.debug(d);
					


				});

				$('div.ui-item-photo img').hide().load(function () {
					var imgt = $(this);
					setTimeout( function(){ imgt.fadeIn(1000); }, 500 );					
				});


				$('ul.pagination a').click(function(e){
					e.stopPropagation();
					list_images(e.target.text);
				});

				
				


				// lazy load images
				$.getScript('//cdnjs.cloudflare.com/ajax/libs/jquery.lazyload/1.9.1/jquery.lazyload.min.js',function(){
					$('img.img-responsive').lazyload({});
				});

			})
			.fail(function(ex){ 
				console.error(ex);				
			});

		}
		


	</script>

</head>
<body>
<div class="container">
	<div style="margin:12px 0px 0px 0px;">		

	</div>
	<div class="container">		
		<form class="form-inline" role="form">
			<div class="form-group">
				<label for="btn-build" class="sr-only">Build:</label>
				<a id="btn-build" href="#" class="btn btn-primary btn-sm" role="button">Build</a>
			</div>
			<div class="form-group">
				<label for="btn-rebuild" class="sr-only">Rebuild:</label>
				<a id="btn-rebuild" href="#" class="btn btn-primary btn-sm" role="button">Rebuild</a>
			</div>
			<div class="form-group">
				<span class="btn btn-primary btn-file btn-sm"> Browse <input id="file-upload" type="file" multiple></span>
			</div>
		</form>

	</div>
	<script type="text/javascript">

	$('#file-upload').change(function(e){
		
		var fd = new FormData();
		for(i=0;i<this.files.length;i++){
			fd.append("file"+i, this.files[i]);	
		}
		
		fd.append("a", "p");		

		$.ajax({
	     url: 'index.php',
	     type: 'POST',
	     data: fd,
	     async: false,
	     cache: false,
	     processData: false,
	     contentType: false,	     
	     enctype: 'multipart/form-data'	     
	   }).done(function(response){
	   	console.debug('response:',response);
	   }).error(function(ex){
	   	console.error(ex);
	   });

	});

	</script>





	<div id="ui-container-photo" class="container"></div>
	<div class="container"><ul class="pagination bottom-pagination"></ul></div>
	
</div>


<div id="image-view-modal" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-body">
      	<div id="image-content-modal"></div>
        <div id="image-description-modal">
        	<p class="ui-description"><textarea name="image-description" type="text" class="ui-label-inputs" placeholder="Image Description"></textarea></p>
        	<p class="ui-url-link">Permanent Image Reference: <br><a href="#" target="_blank">perm-image-link</a></p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" class="ui-close" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div id="loading-view-modal" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
      	<h3>Processing...</h3>
      </div>
      <div class="modal-body">      
		<div class="progress">
			<div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="10" aria-valuemin="0" aria-valuemax="100" style="width: 10%">
			  <span><span class="progress-value">10</span></span>
			</div>
		</div>
      </div>      
    </div>
  </div>
</div>



</body>
</html>

