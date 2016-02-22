<?php
include(APPLICATION_PATH."geoipapi/geoipcity.inc");
include(APPLICATION_PATH."geoipapi/geoipregionvars.php");

class ipvalidationdb extends ipvalidation{
	
	public function __construct($ip){
		parent::__construct($ip);
		
		if(IP_BIN){
			$gi = geoip_open(APPLICATION_PATH."geoipapi/GeoLiteCity.dat",GEOIP_STANDARD);
			
			if($record = geoip_record_by_addr($gi,$ip)){

				$this->v=array_merge(array(
					'locId' => 1,
					'country_code' =>$record->country_code,
					'country_code3' =>$record->country_code3,
					'country_name' =>$record->country_name,
					'region' =>$record->region,
					'region_name' =>$GEOIP_REGION_NAME[$record->country_code][$record->region],
					'city' =>$record->city,
					'postal_code' =>$record->postal_code,
					'latitude' =>$record->latitude,
					'longitude' =>$record->longitude,
					'metro_code' =>$record->metro_code,
					'area_code' =>$record->area_code
				),$this->v);
			}
		}else{
		
			$db = Zend_Registry::get('db');
			
			$rs=$db->prepare("select ipl.* from ".TABLE_IPLIB." ip,".TABLE_IPLIB_LOC." ipl
			 where ip.addressid=ipl.locId and ipstart<=".$this->v['iplong']." and ".$this->v['iplong']."<=ipend");
			$rs->execute();
			 
			if($rs->rowCount()>0){
				$this->v=array_merge($rs->fetch(),$this->v);
			}
		}
		
	}
	
}
?>