<?php
class AuthorizeResponse {
	var $ResponseArr=array();
    public function __construct($str='') {
    	if($str){
			$this->ResponseArr= $this ->parseAuthorizeResponse($str);
		}
    }
    //解析Authorize返回到数组
    public function parseAuthorizeResponse($ar){
            $regs = preg_split("/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/", $ar);
            foreach ($regs as $key => $value) {
                //$regs[$key] = substr($value, 1, -1); // remove double quotes
                $regs[$key] = str_replace('\"','',$value);
                $regs[$key] = str_replace('"','',$value);
            }
            return $regs;
    }
	
	//取得agtransid
	public function get_new_agtransid(){
		return $this->ResponseArr[6].'_'.date('ymd').mt_rand(100,999);
	}
}
?>