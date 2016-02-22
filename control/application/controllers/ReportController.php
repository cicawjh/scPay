<?php
class ReportController extends Zend_Controller_Action {
	
	function init(){
		$request = Zend_Controller_Front::getInstance()->getRequest(); 
		$base_url = $request->getBaseUrl();
		$base_url .= '/public';
		$this->view->headScript()->appendFile($base_url.'/js/datepicker/jquery.datepick.js');
		$this->view->headLink()->appendStylesheet($base_url.'/js/datepicker/redmond.datepick.css');	
		
		$scnsp = Zend_Registry::get('scnsp');
		$user_id = $scnsp->user_id;
		if ($user_id == null) {
			$this->_redirect('/Login');
		}

	}
	
	function indexAction(){
	
		$request=$this->_getRequest();
		
		$this->view->Report=Merchants::getReport1Data($request['begin_date'],$request['end_date'],$request['merchant']);
		$this->view->params=$request;
		
		$response = $this->getResponse(); 
		
		$response->insert('header', $this->view->render('header2.phtml')); 
		$response->insert('footer', $this->view->render('footer.phtml')); 
	}
	
	function chartreportAction(){
	
		$request = Zend_Controller_Front::getInstance()->getRequest(); 
		$base_url = $request->getBaseUrl();
		$base_url .= '/public';
		
		$this->view->headScript()->appendFile($base_url.'/js/FusionCharts/JSClass/FusionCharts.js');
		
		$params=$this->_getRequest(true);
		$this->view->params=$params;
		$response = $this->getResponse();
		
		$response->insert('header', $this->view->render('header2.phtml')); 
		$response->insert('footer', $this->view->render('footer.phtml')); 
	
	}
	
	function chartreportxmldataAction(){
	
		$db = Zend_Registry::get('db');
		
		$params=$this->_getRequest(true);
		
		if(!is_array($params['showline'])){
			$params['showline']=array('Received','Refund','Net Fee');
		}
		

		$chartReports=Merchants::getReport2Data($params);
		$begintimestamp=strtotime($params['begin_date']);
		
		
		$XMLDocument=<<<BOD
<chart caption='Daily Visits' subcaption='(from {$params['begin_date']} to {$params['end_date']})' lineThickness='1' showValues='0' formatNumberScale='0' anchorRadius='2'   divLineAlpha='20' divLineColor='CC3300' divLineIsDashed='1' showAlternateHGridColor='1' alternateHGridAlpha='5' alternateHGridColor='CC3300' shadowAlpha='40' labelStep="2" numvdivlines='5' chartRightMargin="35" bgColor='FFFFFF,CC3300' bgAngle='270' bgAlpha='10,10'>
<categories >
BOD;

$startdate=strtotime($params['begin_date']);
$enddate=strtotime($params['end_date']);
while($startdate<=$enddate){
	$tempdate=date('m/d/Y',$startdate);
	$dateArr[]=$tempdate;
	$category.="<category label='{$tempdate}' />\n";
	$startdate+=3600*24;
}

$colors=array('1D8BD1','F1683C','2AD62A','DBDC25');

if($chartReports){
	foreach($chartReports as $key=>$chartReport){
		$datasets.="<dataset seriesName='".$chartReport['label']."' color='{$colors[$key]}' anchorBorderColor='{$colors[$key]}' anchorBgColor='{$colors[$key]}'>\n";
		foreach($dateArr as $date){
			if(array_key_exists($date,$chartReport['data'])){
				$datasets.="<set value='".$chartReport['data'][$date]['amount']."' />\n";
			}else{
				$datasets.="<set value='0' />\n";
			}
		}
		$datasets.="</dataset>";
	}
}

$XMLDocument.=<<<BOD

{$category}

</categories>
{$datasets}
	<styles>                
		<definition>
                         
			<style name='CaptionFont' type='font' size='12'/>
		</definition>
		<application>
			<apply toObject='CAPTION' styles='CaptionFont' />
			<apply toObject='SUBCAPTION' styles='CaptionFont' />
		</application>
	</styles>

</chart>
BOD;
		
		header("Cache-Control: no-store, no-cache, must-revalidate");
		echo $XMLDocument;
		
		die();
		
	}
	
	function _getRequest($mustDate=false){
			
			$request=$this->getRequest()->getParams();
					
			$dateRegex='#^(?:(?:1[6-9]|[2-9][0-9])[0-9]{2}([-/.]?)(?:(?:0?[1-9]|1[0-2])\1(?:0?[1-9]|1[0-9]|2[0-8])|(?:0?[13-9]|1[0-2])\1(?:29|30)|(?:0?[13578]|1[02])\1(?:31))|(?:(?:1[6-9]|[2-9][0-9])(?:0[48]|[2468][048]|[13579][26])|(?:16|[2468][048]|[3579][26])00)([-/.]?)0?2\2(?:29))$#';
			
			if(!$this->getRequest()->isPost()||$mustDate){
			
				if(!preg_match($dateRegex,$request['begin_date'])&&!preg_match($dateRegex,$request['end_date'])){
					$request['begin_date']=date('Y-m-d',mktime(0,0,0,date('n')-1,date('d'),date('Y')));
					$request['end_date']=date('Y-m-d');
				}elseif(!preg_match($dateRegex,$request['begin_date'])){
					$timestamp=strtotime($request['end_date']);
					$request['begin_date']=date('Y-m-d',mktime(0,0,0,date('n',$timestamp)-1,date('d',$timestamp),date('Y',$timestamp)));
				}elseif(!preg_match($dateRegex,$request['end_date'])){
					$timestamp=strtotime($request['begin_date']);
					$request['end_date']=date('Y-m-d',mktime(0,0,0,date('n',$timestamp)+1,date('d',$timestamp),date('Y',$timestamp)));
				}
			}
			
			return $request;
	}
}
?>