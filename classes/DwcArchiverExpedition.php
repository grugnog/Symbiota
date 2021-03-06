<?php
include_once($serverRoot.'/config/dbconnection.php');
include_once($serverRoot.'/classes/UuidFactory.php');
include_once($serverRoot.'/classes/DwcArchiverOccurrence.php');

class DwcArchiverExpedition{

	private $conn;
	private $ts;
	
	private $collArr;
	private $conditionSql;
	private $condAllowArr;

	private $targetPath;
	private $fileName;
	private $zipArchive;
	
	private $title;
	private $description;
	private $coverageTaxonomic;
	private $coverageGeographic;
	private $coverageTemporal;
	private $numberOfReplicates;
	
	private $coreFieldArr;
	private $stubFieldArr;

	private $securityArr = array();
	private $redactLocalities = true;

	private $logFH;
	private $silent = 0;

	public function __construct(){
		global $serverRoot, $userRights, $isAdmin;
		//ini_set('memory_limit','512M');
		set_time_limit(500);

		//Ensure that PHP DOMDocument class is installed
		if(!class_exists('DOMDocument')){
			exit('FATAL ERROR: PHP DOMDocument class is not installed, please contact your server admin');
		}

		$this->conn = MySQLiConnectionFactory::getCon('readonly');

		$this->ts = time();

		$this->condAllowArr = array('country','stateprovince','county','recordedby','family','sciname','processingstatus','ocr');

		$this->coreFieldArr = array(
			'id' => '',
			'accessURI' => 'http://rs.tdwg.org/ac/terms/accessURI',		//url 
			'providerManagedID' => 'http://rs.tdwg.org/ac/terms/providerManagedID',	//GUID
	 		'title' => 'http://purl.org/dc/terms/title',	//scientific name
	 		'comments' => 'http://rs.tdwg.org/ac/terms/comments',	//General notes	
			'Owner' => 'http://ns.adobe.com/xap/1.0/rights/Owner',	//Institution name
			'rights' => 'http://purl.org/dc/terms/rights',		//Copyright unknown
			'UsageTerms' => 'http://ns.adobe.com/xap/1.0/rights/UsageTerms',	//Creative Commons BY-SA 3.0 license
			'WebStatement' => 'http://ns.adobe.com/xap/1.0/rights/WebStatement',	//http://creativecommons.org/licenses/by-nc-sa/3.0/us/
			'MetadataDate' => 'http://ns.adobe.com/xap/1.0/MetadataDate',	//timestamp
			'associatedSpecimenReference' => 'http://rs.tdwg.org/ac/terms/associatedSpecimenReference',	//reference url in portal
			'type' => 'http://purl.org/dc/terms/type',		//StillImage
			'subtype' => 'http://rs.tdwg.org/ac/terms/subtype',		//Photograph
			'format' => 'http://purl.org/dc/terms/format',		//jpg
			'metadataLanguage' => 'http://rs.tdwg.org/ac/terms/metadataLanguage'	//en
		);

		$this->stubFieldArr = array(
			'coreid' => '',
			'institutionCode' => 'http://rs.tdwg.org/dwc/terms/institutionCode',
			'collectionCode' => 'http://rs.tdwg.org/dwc/terms/collectionCode',
			'occurrenceID' => 'http://rs.tdwg.org/dwc/terms/occurrenceID',
			'catalogNumber' => 'http://rs.tdwg.org/dwc/terms/catalogNumber',
			'otherCatalogNumbers' => 'http://rs.tdwg.org/dwc/terms/otherCatalogNumbers',
			'family' => 'http://rs.tdwg.org/dwc/terms/family',
			'scientificName' => 'http://rs.tdwg.org/dwc/terms/scientificName',
			'scientificNameAuthorship' => 'http://rs.tdwg.org/dwc/terms/scientificNameAuthorship',
			'genus' => 'http://rs.tdwg.org/dwc/terms/genus',
			'specificEpithet' => 'http://rs.tdwg.org/dwc/terms/specificEpithet',
			'taxonRank' => 'http://rs.tdwg.org/dwc/terms/taxonRank',
			'infraspecificEpithet' => 'http://rs.tdwg.org/dwc/terms/infraspecificEpithet',
 			'identifiedBy' => 'http://rs.tdwg.org/dwc/terms/identifiedBy',
 			'dateIdentified' => 'http://rs.tdwg.org/dwc/terms/dateIdentified',
 			'identificationReferences' => 'http://rs.tdwg.org/dwc/terms/identificationReferences',
 			'identificationRemarks' => 'http://rs.tdwg.org/dwc/terms/identificationRemarks',
 			'taxonRemarks' => 'http://rs.tdwg.org/dwc/terms/taxonRemarks',
			'identificationQualifier' => 'http://rs.tdwg.org/dwc/terms/identificationQualifier',
			'typeStatus' => 'http://rs.tdwg.org/dwc/terms/typeStatus',
			'recordedBy' => 'http://rs.tdwg.org/dwc/terms/recordedBy',
			'recordNumber' => 'http://rs.tdwg.org/dwc/terms/recordNumber',
			'eventDate' => 'http://rs.tdwg.org/dwc/terms/eventDate',
			'year' => 'http://rs.tdwg.org/dwc/terms/year',
			'month' => 'http://rs.tdwg.org/dwc/terms/month',
			'day' => 'http://rs.tdwg.org/dwc/terms/day',
			'startDayOfYear' => 'http://rs.tdwg.org/dwc/terms/startDayOfYear',
			'endDayOfYear' => 'http://rs.tdwg.org/dwc/terms/endDayOfYear',
 			'verbatimEventDate' => 'http://rs.tdwg.org/dwc/terms/verbatimEventDate',
 			'habitat' => 'http://rs.tdwg.org/dwc/terms/habitat',
 			'substrate' => '',
			'fieldNumber' => 'http://rs.tdwg.org/dwc/terms/fieldNumber',
 			'occurrenceRemarks' => 'http://rs.tdwg.org/dwc/terms/occurrenceRemarks',
			'informationWithheld' => 'http://rs.tdwg.org/dwc/terms/informationWithheld',
 			'dynamicProperties' => 'http://rs.tdwg.org/dwc/terms/dynamicProperties',
 			'associatedTaxa' => 'http://rs.tdwg.org/dwc/terms/associatedTaxa',
 			'reproductiveCondition' => 'http://rs.tdwg.org/dwc/terms/reproductiveCondition',
			'establishmentMeans' => 'http://rs.tdwg.org/dwc/terms/establishmentMeans',
			'lifeStage' => 'http://rs.tdwg.org/dwc/terms/lifeStage',
			'sex' => 'http://rs.tdwg.org/dwc/terms/sex',
 			'individualCount' => 'http://rs.tdwg.org/dwc/terms/individualCount',
 			'samplingProtocol' => 'http://rs.tdwg.org/dwc/terms/samplingProtocol',
 			'preparations' => 'http://rs.tdwg.org/dwc/terms/preparations',
 			'country' => 'http://rs.tdwg.org/dwc/terms/country',
 			'stateProvince' => 'http://rs.tdwg.org/dwc/terms/stateProvince',
 			'county' => 'http://rs.tdwg.org/dwc/terms/county',
 			'municipality' => 'http://rs.tdwg.org/dwc/terms/municipality',
 			'locality' => 'http://rs.tdwg.org/dwc/terms/locality',
 			'decimalLatitude' => 'http://rs.tdwg.org/dwc/terms/decimalLatitude',
 			'decimalLongitude' => 'http://rs.tdwg.org/dwc/terms/decimalLongitude',
	 		'geodeticDatum' => 'http://rs.tdwg.org/dwc/terms/geodeticDatum',
	 		'coordinateUncertaintyInMeters' => 'http://rs.tdwg.org/dwc/terms/coordinateUncertaintyInMeters',
	 		'footprintWKT' => 'http://rs.tdwg.org/dwc/terms/footprintWKT',
	 		'verbatimCoordinates' => 'http://rs.tdwg.org/dwc/terms/verbatimCoordinates',
			'georeferencedBy' => 'http://rs.tdwg.org/dwc/terms/georeferencedBy',
			'georeferenceProtocol' => 'http://rs.tdwg.org/dwc/terms/georeferenceProtocol',
			'georeferenceSources' => 'http://rs.tdwg.org/dwc/terms/georeferenceSources',
			'georeferenceVerificationStatus' => 'http://rs.tdwg.org/dwc/terms/georeferenceVerificationStatus',
			'georeferenceRemarks' => 'http://rs.tdwg.org/dwc/terms/georeferenceRemarks',
			'minimumElevationInMeters' => 'http://rs.tdwg.org/dwc/terms/minimumElevationInMeters',
			'maximumElevationInMeters' => 'http://rs.tdwg.org/dwc/terms/maximumElevationInMeters',
			'verbatimElevation' => 'http://rs.tdwg.org/dwc/terms/verbatimElevation',
	 		'ocrOutput' => '',
			'language' => 'http://purl.org/dc/terms/language',
	 		'recordId' => 'http://portal.idigbio.org/terms/recordId'
 		);

 		$this->securityArr = array('locality','minimumElevationInMeters','maximumElevationInMeters','verbatimElevation',
			'decimalLatitude','decimalLongitude','geodeticDatum','coordinateUncertaintyInMeters','footprintWKT',
			'verbatimCoordinates','georeferenceRemarks','georeferencedBy','georeferenceProtocol','georeferenceSources',
			'georeferenceVerificationStatus','habitat','informationWithheld');

	}

	public function __destruct(){
		if(!($this->conn === false)) $this->conn->close();
		if($this->logFH){
			fclose($this->logFH);
		}
	}

	public function setTargetPath($tp = ''){
		if($tp){
			$this->targetPath = $tp;
		}
		else{
			//Set to temp download path
			$tPath = $GLOBALS["tempDirRoot"];
			if(!$tPath){
				$tPath = ini_get('upload_tmp_dir');
			}
			if(!$tPath){
				$tPath = $GLOBALS["serverRoot"]."/temp";
			}
			if(file_exists($tPath."/downloads")){
				$tPath .= "/downloads";
			}
			if(substr($tPath,-1) != '/' && substr($tPath,-1) != '\\'){
				$tPath .= '/';
			}
			$this->targetPath = $tPath;
		}
	}

	public function setFileName($seed){
		$this->fileName = $this->conn->real_escape_string($seed).'_DWCA_expedition.zip';
	}

	public function setCollArr($collTarget){
		$collTarget = $this->cleanInStr($collTarget);
		unset($this->collArr);
		$this->collArr = array();
		$sqlWhere = '';
		if($collTarget){
			$sqlWhere .= ($sqlWhere?'AND ':'').'(c.collid IN('.$collTarget.')) ';
		}
		else{
			//Don't limit by collection id 
		}
		$sql = 'SELECT c.collid, c.institutioncode, c.collectioncode, c.collectionname, c.fulldescription, c.collectionguid, '.
			'IFNULL(c.homepage,i.url) AS url, IFNULL(c.contact,i.contact) AS contact, IFNULL(c.email,i.email) AS email, '.
			'c.guidtarget, c.latitudedecimal, c.longitudedecimal, c.icon, c.colltype, c.rights, c.rightsholder, c.usageterm, '.
			'i.address1, i.address2, i.city, i.stateprovince, i.postalcode, i.country, i.phone '.
			'FROM omcollections c LEFT JOIN institutions i ON c.iid = i.iid WHERE '.$sqlWhere;
		//echo $sql.'<br/>';
		$rs = $this->conn->query($sql);
		while($r = $rs->fetch_object()){
			$this->collArr[$r->collid]['instcode'] = $r->institutioncode;
			$this->collArr[$r->collid]['collcode'] = $r->collectioncode;
			$this->collArr[$r->collid]['collname'] = htmlspecialchars($r->collectionname);
			$this->collArr[$r->collid]['description'] = htmlspecialchars($r->fulldescription);
			$this->collArr[$r->collid]['collectionguid'] = $r->collectionguid;
			$this->collArr[$r->collid]['url'] = $r->url;
			$this->collArr[$r->collid]['contact'] = htmlspecialchars($r->contact);
			$this->collArr[$r->collid]['email'] = $r->email;
			$this->collArr[$r->collid]['guidtarget'] = $r->guidtarget;
			$this->collArr[$r->collid]['lat'] = $r->latitudedecimal;
			$this->collArr[$r->collid]['lng'] = $r->longitudedecimal;
			$this->collArr[$r->collid]['icon'] = $r->icon;
			$this->collArr[$r->collid]['colltype'] = $r->colltype;
			$this->collArr[$r->collid]['rights'] = $r->rights;
			$this->collArr[$r->collid]['rightsholder'] = $r->rightsholder;
			$this->collArr[$r->collid]['usageterm'] = $r->usageterm;
			$this->collArr[$r->collid]['address1'] = htmlspecialchars($r->address1);
			$this->collArr[$r->collid]['address2'] = htmlspecialchars($r->address2);
			$this->collArr[$r->collid]['city'] = $r->city;
			$this->collArr[$r->collid]['state'] = $r->stateprovince;
			$this->collArr[$r->collid]['postalcode'] = $r->postalcode;
			$this->collArr[$r->collid]['country'] = $r->country;
			$this->collArr[$r->collid]['phone'] = $r->phone;
		}
		$rs->free();
	}

	public function getCollArr(){
		return $this->collArr;
	}

	public function setConditionStr($condObj, $filter = 1){
		$condArr = array();
		if(is_array($condObj)){
			//Array of key/value pairs (e.g. array(country => USA,United States, stateprovince => Arizona,New Mexico)
			$condArr = $condObj;
		}
		elseif(is_string($condObj)){
			//String of key/value pairs (e.g. country:USA,United States;stateprovince:Arizona,New Mexico;county-start:Pima,Eddy
			$cArr = explode(';',$condObj);
			foreach($cArr as $rawV){
				$tok = explode(':',$rawV);
				if(count($tok) == 2){
					$condArr[$tok[0]] = $tok[1];
				}
			}
		}
		$this->conditionSql = '';
		if(array_key_exists('ocr',$condArr)){
			$ocrStr = $condArr['ocr'];
			unset($condArr['ocr']);
			$this->conditionSql .= 'AND (ocr.rawstr LIKE "%'.$ocrStr.'%") ';
		}
		//Occurrences search criteria 
		foreach($condArr as $k => $v){
			if(!$filter || in_array(strtolower($k),$this->condAllowArr)){
				$type = '';
				if($p = strpos($k,'-')){
					$type = strtolower(substr($k,0,$p));
					$k = substr($k,$p);
				}
				if($type == 'like'){
					$sqlFrag = '';
					$terms = explode(',',$v);
					foreach($terms as $t){
						$sqlFrag .= 'OR (o.'.$k.' LIKE "%'.$this->cleanInStr($t).'%") ';
					}
					$this->conditionSql .= 'AND ('.substr($sqlFrag,3).') ';
				}
				elseif($type == 'start'){
					$sqlFrag = '';
					$terms = explode(',',$v);
					foreach($terms as $t){
						$sqlFrag .= 'OR (o.'.$k.' LIKE "'.$this->cleanInStr($t).'%") ';
					}
					$this->conditionSql .= 'AND ('.substr($sqlFrag,3).') ';
				}
				elseif($type == 'null'){
					$this->conditionSql .= 'AND (o.'.$k.' IS NULL) ';
				}
				elseif($type == 'notnull'){
					$this->conditionSql .= 'AND (o.'.$k.' IS NOT NULL) ';
				}
				else{
					$this->conditionSql .= 'AND (o.'.$k.' IN("'.str_replace(',','","',$v).'")) ';
				}
			}
		}
		//echo $this->conditionSql;
	}

	public function createDwcArchive(){
		global $serverRoot;
		if(!$this->targetPath) $this->setTargetPath();
		$archiveFile = '';
		if($this->collArr){
			if(!$this->logFH && !$this->silent){
				$logFile = $serverRoot.(substr($serverRoot,-1)=='/'?'':'/')."temp/logs/DWCA_".date('Y-m-d').".log";
				$this->logFH = fopen($logFile, 'a');
			}
			$this->logOrEcho('Creating DwC-A file...'."\n");
			
			if(!class_exists('ZipArchive')){
				$this->logOrEcho("FATAL ERROR: PHP ZipArchive class is not installed, please contact your server admin\n");
				exit('FATAL ERROR: PHP ZipArchive class is not installed, please contact your server admin');
			}
	
			$archiveFile = $this->targetPath.$this->fileName;
			if(file_exists($archiveFile)) unlink($archiveFile);
			$this->zipArchive = new ZipArchive;
			$status = $this->zipArchive->open($archiveFile, ZipArchive::CREATE);
			if($status !== true){
				exit('FATAL ERROR: unable to create archive file: '.$status);
			}
			//$this->logOrEcho("DWCA created: ".$archiveFile."\n");
			
			$this->writeMetaFile();
			$this->writeEmlFile();
			$this->writeCoreFile();
			$this->writeStubFile();
			$this->zipArchive->close();
			
			//Clean up
			unlink($this->targetPath.$this->ts.'-meta.xml');
			unlink($this->targetPath.$this->ts.'-eml.xml');
			unlink($this->targetPath.$this->ts.'-occur.csv');
			unlink($this->targetPath.$this->ts.'-images.csv');
			unlink($this->targetPath.$this->ts.'-det.csv');
	
			$this->logOrEcho("\n-----------------------------------------------------\n");
		}
		else{
			echo 'ERROR: unable to create DwC-A for collection #'.implode(',',array_keys($this->collArr));
		}
		return $archiveFile;
	}
	
	private function writeMetaFile(){
		$this->logOrEcho("Creating meta.xml (".date('h:i:s A').")... ");

		//Create new DOM document 
		$newDoc = new DOMDocument('1.0','UTF-8');

		//Add root element 
		$rootElem = $newDoc->createElement('archive');
		$rootElem->setAttribute('metadata','eml.xml');
		$rootElem->setAttribute('xmlns','http://rs.tdwg.org/dwc/text/');
		$rootElem->setAttribute('xmlns:xsi','http://www.w3.org/2001/XMLSchema-instance');
		$rootElem->setAttribute('xsi:schemaLocation','http://rs.tdwg.org/dwc/text/   http://rs.tdwg.org/dwc/text/tdwg_dwc_text.xsd');
		$newDoc->appendChild($rootElem);

		//Core file definition
		$coreElem = $newDoc->createElement('core');
		$coreElem->setAttribute('encoding',$GLOBALS['charset']);
		$coreElem->setAttribute('fieldsTerminatedBy',',');
		$coreElem->setAttribute('linesTerminatedBy','\n');
		$coreElem->setAttribute('fieldsEnclosedBy','"');
		$coreElem->setAttribute('ignoreHeaderLines','1');
		$coreElem->setAttribute('rowType','http://rs.gbif.org/terms/1.0/Image');
		
		$filesElem = $newDoc->createElement('files');
		$filesElem->appendChild($newDoc->createElement('location','media.csv'));
		$coreElem->appendChild($filesElem);

		$idElem = $newDoc->createElement('id');
		$idElem->setAttribute('index','0');
		$coreElem->appendChild($idElem);

		//List image fields
		$imgCnt = 0;
		foreach($this->coreFieldArr as $k => $v){
			if($imgCnt){
				$fieldElem = $newDoc->createElement('field');
				$fieldElem->setAttribute('index',$imgCnt);
				$fieldElem->setAttribute('term',$v);
				$coreElem->appendChild($fieldElem);
			}
			$imgCnt++;
		}
		$rootElem->appendChild($coreElem);
		
		//Stub extension
		$stubElem1 = $newDoc->createElement('extension');
		$stubElem1->setAttribute('encoding',$GLOBALS['charset']);
		$stubElem1->setAttribute('fieldsTerminatedBy',',');
		$stubElem1->setAttribute('linesTerminatedBy','\n');
		$stubElem1->setAttribute('fieldsEnclosedBy','"');
		$stubElem1->setAttribute('ignoreHeaderLines','1');
		$stubElem1->setAttribute('rowType','http://rs.tdwg.org/dwc/terms/Occurrence');

		$filesElem1 = $newDoc->createElement('files');
		$filesElem1->appendChild($newDoc->createElement('location','occurrences.csv'));
		$stubElem1->appendChild($filesElem1);
		
		$coreIdElem1 = $newDoc->createElement('coreid');
		$coreIdElem1->setAttribute('index','0');
		$stubElem1->appendChild($coreIdElem1);
		
		$occCnt = 0;
		foreach($this->stubFieldArr as $k => $v){
			if($occCnt){
				$fieldElem = $newDoc->createElement('field');
				$fieldElem->setAttribute('index',$occCnt);
				$fieldElem->setAttribute('term',$v);
				$stubElem1->appendChild($fieldElem);
			}
			$occCnt++;
		}
		$rootElem->appendChild($stubElem1);
		
		$tempFileName = $this->targetPath.$this->ts.'-meta.xml';
		$newDoc->save($tempFileName);
		$this->zipArchive->addFile($tempFileName);
    	$this->zipArchive->renameName($tempFileName,'meta.xml');
		
    	$this->logOrEcho("Done!! (".date('h:i:s A').")\n");
	}

	public function writeEmlFile(){
		global $clientRoot, $defaultTitle, $adminEmail;
		
		$this->logOrEcho("Creating eml.xml (".date('h:i:s A').")... ");
		
		$urlPathPrefix = 'http://'.$_SERVER["SERVER_NAME"].$clientRoot.(substr($clientRoot,-1)=='/'?'':'/');
		
		$emlArr = array();
		$emlArr['alternateIdentifier'][] = UuidFactory::getUuidV4();
		$emlArr['title'] = $this->title;
		
		$emlArr['creator'][0]['organizationName'] = $defaultTitle;
		$emlArr['creator'][0]['electronicMailAddress'] = $adminEmail;
		$emlArr['creator'][0]['onlineUrl'] = $urlPathPrefix.'index.php';
		
		$emlArr['metadataProvider'][0]['organizationName'] = $defaultTitle;
		$emlArr['metadataProvider'][0]['electronicMailAddress'] = $adminEmail;
		$emlArr['metadataProvider'][0]['onlineUrl'] = $urlPathPrefix.'index.php';
		
		$emlArr['pubDate'] = date("Y-m-d");
		$emlArr['language'] = 'eng';
		$emlArr['description'] = $this->description;

		//Get EML string
		$dwcaHandler = new DwcArchiverOccurrence();
		$emlDoc = $dwcaHandler->getEmlDom($emlArr);
		
		$tempFileName = $this->targetPath.$this->ts.'-eml.xml';
		$emlDoc->save($tempFileName);

		$this->zipArchive->addFile($tempFileName);
    	$this->zipArchive->renameName($tempFileName,'eml.xml');

    	$this->logOrEcho("Done!! (".date('h:i:s A').")\n");
	}

	private function writeEmlFile_old(){
		global $clientRoot, $defaultTitle, $adminEmail;
		
		$this->logOrEcho("Creating eml.xml (".date('h:i:s A').")... ");

		$urlPathPrefix = 'http://'.$_SERVER["SERVER_NAME"].$clientRoot.(substr($clientRoot,-1)=='/'?'':'/');

		//Create new DOM document 
		$newDoc = new DOMDocument('1.0','UTF-8');

		//Add root element 
		$rootElem = $newDoc->createElement('eml:eml');
		$rootElem->setAttribute('xmlns:eml','eml://ecoinformatics.org/eml-2.1.1');
		$rootElem->setAttribute('xmlns:dc','http://purl.org/dc/terms/');
		$rootElem->setAttribute('xmlns:xsi','http://www.w3.org/2001/XMLSchema-instance');
		$rootElem->setAttribute('xsi:schemaLocation','eml://ecoinformatics.org/eml-2.1.1 http://rs.gbif.org/schema/eml-gbif-profile/1.0.1/eml.xsd');
		$rootElem->setAttribute('packageId',UuidFactory::getUuidV4());
		$rootElem->setAttribute('system','http://symbiota.org');
		$rootElem->setAttribute('scope','system');
		$rootElem->setAttribute('xml:lang','eng');
		
		$newDoc->appendChild($rootElem);

		$cArr = array();
		$datasetElem = $newDoc->createElement('dataset');
		$rootElem->appendChild($datasetElem);

		$datasetElem->appendChild($newDoc->createElement('alternateIdentifier',UuidFactory::getUuidV4()));
		
		$titleElem = $newDoc->createElement('title',$this->title);
		$titleElem->setAttribute('xml:lang','eng');
		$datasetElem->appendChild($titleElem);

		$creatorElem = $newDoc->createElement('creator');
		$creatorElem->appendChild($newDoc->createElement('organizationName',$defaultTitle));
		$creatorElem->appendChild($newDoc->createElement('electronicMailAddress',$adminEmail));
		$creatorElem->appendChild($newDoc->createElement('onlineUrl',$urlPathPrefix.'index.php'));
		$datasetElem->appendChild($creatorElem);

		$mdElem = $newDoc->createElement('metadataProvider');
		$mdElem->appendChild($newDoc->createElement('organizationName',$defaultTitle));
		$mdElem->appendChild($newDoc->createElement('electronicMailAddress',$adminEmail));
		$mdElem->appendChild($newDoc->createElement('onlineUrl',$urlPathPrefix.'index.php'));
		$datasetElem->appendChild($mdElem);
		
		$datasetElem->appendChild($newDoc->createElement('pubDate',date("Y-m-d")));
		$datasetElem->appendChild($newDoc->createElement('language','eng'));

		$abstractElem = $newDoc->createElement('abstract');
		$abstractElem->appendChild($newDoc->createElement('para',$this->description));
		$datasetElem->appendChild($abstractElem);

		$tempFileName = $this->targetPath.$this->ts.'-eml.xml';
		$newDoc->save($tempFileName);

		$this->zipArchive->addFile($tempFileName);
    	$this->zipArchive->renameName($tempFileName,'eml.xml');

    	$this->logOrEcho("Done!! (".date('h:i:s A').")\n");
	}

	private function writeCoreFile(){
		global $clientRoot,$imageDomain;

		$this->logOrEcho("Creating media.csv (".date('h:i:s A').")... ");
		if($this->collArr){
			$fh = fopen($this->targetPath.$this->ts.'-media.csv', 'w');
			
			//Output header
			fputcsv($fh, array_keys($this->coreFieldArr));
	
			//Output records
			$sql = 'SELECT g.guid, IFNULL(i.originalurl,i.url) as accessURI, g.guid AS providermanagedid, '. 
				'o.sciname AS title, IFNULL(i.caption,i.notes) as comments, '.
				'IFNULL(c.rightsholder,CONCAT(c.collectionname," (",CONCAT_WS("-",c.institutioncode,c.collectioncode),")")) AS owner, '.
				'c.rights, "" AS usageterms, c.accessrights AS webstatement, c.initialtimestamp AS metadatadate, o.occid '.
				'FROM images i INNER JOIN omoccurrences o ON i.occid = o.occid '.
				'INNER JOIN omcollections c ON o.collid = c.collid '.
				'INNER JOIN guidimages g ON i.imgid = g.imgid '.
				'INNER JOIN guidoccurrences og ON o.occid = og.occid ';
				if(strpos($this->conditionSql,'ocr.rawstr')) $sql .= 'INNER JOIN specprocessorrawlabels ocr ON i.imgid = ocr.imgid ';
				$sql .= 'WHERE c.collid IN('.implode(',',array_keys($this->collArr)).') ';
			if($this->redactLocalities){
				$sql .= 'AND (o.localitySecurity = 0 OR o.localitySecurity IS NULL) ';
			}
			if($this->conditionSql) {
				$sql .= $this->conditionSql;
			}
			//echo $sql;
			if($rs = $this->conn->query($sql,MYSQLI_USE_RESULT)){
				$referencePrefix = 'http://'.$_SERVER["SERVER_NAME"];
				if(isset($imageDomain) && $imageDomain) $referencePrefix = $imageDomain;
				while($r = $rs->fetch_assoc()){
					if(substr($r['accessURI'],0,1) == '/') $r['accessURI'] = $referencePrefix.$r['accessURI'];
					if(stripos($r['rights'],'http://creativecommons.org') === 0){
						$r['providermanagedid'] = 'urn:uuid:'.$_SERVER["SERVER_NAME"].':'.$r['providermanagedid'];
						$r['webstatement'] = $r['rights'];
						$r['rights'] = '';
						if(!$r['usageterms']){
							if($r['webstatement'] == 'http://creativecommons.org/publicdomain/zero/1.0/'){
								$r['usageterms'] = 'CC0 1.0 (Public-domain)';
							}
							elseif($r['webstatement'] == 'http://creativecommons.org/licenses/by/3.0/'){
								$r['usageterms'] = 'CC BY (Attribution)';
							}
							elseif($r['webstatement'] == 'http://creativecommons.org/licenses/by-sa/3.0/'){
								$r['usageterms'] = 'CC BY-SA (Attribution-ShareAlike)';
							}
							elseif($r['webstatement'] == 'http://creativecommons.org/licenses/by-nc/3.0/'){
								$r['usageterms'] = 'CC BY-NC (Attribution-Non-Commercial)';
							}
							elseif($r['webstatement'] == 'http://creativecommons.org/licenses/by-nc-sa/3.0/'){
								$r['usageterms'] = 'CC BY-NC-SA (Attribution-NonCommercial-ShareAlike)';
							}
						}
					}
					if(!$r['usageterms']) $r['usageterms'] = 'CC BY-NC-SA (Attribution-NonCommercial-ShareAlike)';
					$r['associatedSpecimenReference'] = 'http://'.$_SERVER["SERVER_NAME"].$clientRoot.'/collections/individual/index.php?occid='.$r['occid'];
					$r['type'] = 'StillImage';
					$r['subtype'] = 'Photograph';
					$extStr = strtolower(substr($r['accessURI'],strrpos($r['accessURI'],'.')+1));
					if($extStr == 'jpg' || $extStr == 'jpeg'){
						$r['format'] = 'image/jpeg';
					}
					elseif($extStr == 'gif'){
						$r['format'] = 'image/gif';
					}
					elseif($extStr == 'png'){
						$r['format'] = 'image/png';
					}
					elseif($extStr == 'tiff' || $extStr == 'tif'){
						$r['format'] = 'image/tiff';
					}
					else{
						$r['format'] = '';
					}
					$r['metadataLanguage'] = 'en';
					unset($r['occid']);
					//Load record array into output file
					fputcsv($fh, $this->addcslashesArr($r));
				}
				$rs->free();
			}
			else{
				$this->logOrEcho("ERROR creating media.csv file: ".$this->conn->error."\n");
				$this->logOrEcho("\tSQL: ".$sql."\n");
			}
			
			fclose($fh);
			$this->zipArchive->addFile($this->targetPath.$this->ts.'-media.csv');
			$this->zipArchive->renameName($this->targetPath.$this->ts.'-media.csv','media.csv');
		}
		else{
			$this->logOrEcho("ERROR: collections not defined; media.csv not created\n");
		}
		
    	$this->logOrEcho("Done!! (".date('h:i:s A').")\n");
	}

	private function writeStubFile(){
		global $clientRoot;
		$this->logOrEcho("Creating occurrences.csv (".date('h:i:s A').")... ");
		if($this->collArr){
			$fh = fopen($this->targetPath.$this->ts.'-occur.csv', 'w');
			
			//Output header
			fputcsv($fh, array_keys($this->stubFieldArr));
			
			//Output records
			$sql = 'SELECT ig.guid, IFNULL(o.institutionCode,c.institutionCode) AS institutionCode, IFNULL(o.collectionCode,c.collectionCode) AS collectionCode, '.
				'o.occurrenceID, o.catalogNumber, o.otherCatalogNumbers, '.
				'o.family, o.sciname AS scientificName, IFNULL(t.author,o.scientificNameAuthorship) AS scientificNameAuthorship, '.
				'IFNULL(CONCAT_WS(" ",t.unitind1,t.unitname1),o.genus) AS genus, IFNULL(CONCAT_WS(" ",t.unitind2,t.unitname2),o.specificEpithet) AS specificEpithet, '.
				'IFNULL(t.unitind3,o.taxonRank) AS taxonRank, IFNULL(t.unitname3,o.infraspecificEpithet) AS infraspecificEpithet, '.
				'o.identifiedBy, o.dateIdentified, o.identificationReferences, o.identificationRemarks, o.taxonRemarks, o.identificationQualifier, o.typeStatus, '.
				'CONCAT_WS("; ",o.recordedBy,o.associatedCollectors) AS recordedBy, o.recordNumber, o.eventDate, o.year, o.month, o.day, o.startDayOfYear, o.endDayOfYear, '.
				'o.verbatimEventDate, o.habitat, o.substrate, o.fieldNumber, '.
				'CONCAT_WS("; ",o.occurrenceRemarks,o.verbatimAttributes) AS occurrenceRemarks, o.informationWithheld, '.
				'o.dynamicProperties, o.associatedTaxa, o.reproductiveCondition, o.establishmentMeans, '.
				'o.lifeStage, o.sex, o.individualCount, o.samplingProtocol, o.preparations, '.
				'o.country, o.stateProvince, o.county, o.municipality, o.locality, o.decimalLatitude, o.decimalLongitude, '.
				'o.geodeticDatum, o.coordinateUncertaintyInMeters, o.footprintWKT, o.verbatimCoordinates, '.
				'o.georeferencedBy, o.georeferenceProtocol, o.georeferenceSources, o.georeferenceVerificationStatus, '.
				'o.georeferenceRemarks, o.minimumElevationInMeters, o.maximumElevationInMeters, o.verbatimElevation, ocr.rawstr, '.
				'o.language, g.guid AS recordId, o.localitySecurity, c.collid, o.occid '.
				'FROM (omcollections c INNER JOIN omoccurrences o ON c.collid = o.collid) '.
				'INNER JOIN guidoccurrences g ON o.occid = g.occid '.
				'INNER JOIN images i ON o.occid = i.occid '.
				'INNER JOIN guidimages ig ON i.imgid = ig.imgid '.
				'LEFT JOIN taxa t ON o.tidinterpreted = t.TID '.
				'LEFT JOIN specprocessorrawlabels ocr ON i.imgid = ocr.imgid '.
				'WHERE c.collid IN('.implode(',',array_keys($this->collArr)).') ';
			if($this->conditionSql) {
				$sql .= $this->conditionSql;
			}
			//echo $sql;
			if($rs = $this->conn->query($sql,MYSQLI_USE_RESULT)){
				while($r = $rs->fetch_assoc()){
					if($this->redactLocalities && $r["localitySecurity"] > 0){
						foreach($this->securityArr as $v){
							if(array_key_exists($v,$r)) $r[$v] = '[Redacted]';
						}
					}
					unset($r['localitySecurity']);
					$guidTarget = $this->collArr[$r['collid']]['guidtarget'];
					if($guidTarget == 'catalogNumber'){
						$r['occurrenceID'] = $r['catalogNumber'];
					}
					elseif($guidTarget == 'symbiotaUUID'){
						$r['occurrenceID'] = $r['recordId'];
					}
					$r['recordId'] = 'urn:uuid:'.$_SERVER["SERVER_NAME"].':'.$r['recordId'];
					unset($r['collid']);
					unset($r['occid']);
					fputcsv($fh, $this->addcslashesArr($r));
				}
				$rs->free();
			}
			else{
				$this->logOrEcho("ERROR creating occurrence.csv file: ".$this->conn->error."\n");
				$this->logOrEcho("\tSQL: ".$sql."\n");
			}
	
			fclose($fh);
			$this->zipArchive->addFile($this->targetPath.$this->ts.'-occur.csv');
			$this->zipArchive->renameName($this->targetPath.$this->ts.'-occur.csv','occurrences.csv');
		}
		else{
			$this->logOrEcho("ERROR: collections not defined; occurrences.csv not created\n");
		}
    	$this->logOrEcho("Done!! (".date('h:i:s A').")\n");
	}

	//Misc functions
	public function getCollectionList(){
		$retArr = array();
		$sql = 'SELECT collid, collectionname, CONCAT_WS("-",institutioncode,collectioncode) as instcode '.
			'FROM omcollections '.
			'WHERE colltype = "Preserved Specimens" '.
			'ORDER BY collectionname ';
		$rs = $this->conn->query($sql);
		while($r = $rs->fetch_object()){
			$retArr[$r->collid] = $r->collectionname.' ('.$r->instcode.')';
		}
		return $retArr;
	}

	public function setSilent($c){
		$this->silent = $c;
	}

	private function logOrEcho($str){
		if(!$this->silent){
			if($this->logFH){
				fwrite($this->logFH,$str);
			} 
			echo '<li>'.$str.'</li>';
			ob_flush();
			flush();
		}
	}

	private function encodeArr(&$inArr,$targetCharset){
		foreach($inArr as $k => $v){
			$inArr[$k] = $this->encodeString($v,$targetCharset);
		}
	}
	
	private function encodeString($inStr,$targetCharset){
		global $charset;
		$retStr = $inStr;
		
		$portalCharset = ''; 
		if(strtolower($charset) == 'utf-8' || strtolower($charset) == 'utf8'){
			$portalCharset = 'utf-8';
		}
		elseif(strtolower($charset) == 'iso-8859-1'){
			$portalCharset = 'iso-8859-1';
		}
		if($portalCharset){
			if($targetCharset == 'utf8' && $portalCharset == 'iso-8859-1'){
				if(mb_detect_encoding($inStr,'UTF-8,ISO-8859-1',true) == "ISO-8859-1"){
					$retStr = utf8_encode($inStr);
					//$retStr = iconv("ISO-8859-1//TRANSLIT","UTF-8",$inStr);
				}
			}
			elseif($targetCharset == "iso88591" && $portalCharset == 'utf-8'){
				if(mb_detect_encoding($inStr,'UTF-8,ISO-8859-1') == "UTF-8"){
					$retStr = utf8_decode($inStr);
					//$retStr = iconv("UTF-8","ISO-8859-1//TRANSLIT",$inStr);
				}
			}
		}
		return $retStr;
	}
	
	private function addcslashesArr($arr){
		$retArr = array();
		foreach($arr as $k => $v){
			$retArr[$k] = addcslashes($v,"\n\r\"\\");
		}
		return $retArr;
	}

	public function humanFilesize($filePath) {
		if(!file_exists($filePath)) return '';
		$decimals = 0;
		$bytes = filesize($filePath);
		$sz = 'BKMGTP';
		$factor = floor((strlen($bytes) - 1) / 3);
		return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
	}

	private function cleanInStr($inStr){
		$retStr = trim($inStr);
		$retStr = preg_replace('/\s\s+/', ' ',$retStr);
		$retStr = $this->conn->real_escape_string($retStr);
		return $retStr;
	}
}
?>