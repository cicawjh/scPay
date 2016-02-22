<?php
class ipvalidation{
	protected $v=array(
		'ip'=>'',
		'iplong'=>0,
		'iptype'=>'ipv4'
	);
	protected $ipvalid=false;
	
	public function __construct($ip){
		if(preg_match('/^\s*((2[0-4]\d|25[0-5]|[01]?\d\d?)\.){3}(2[0-4]\d|25[0-5]|[01]?\d\d?)\s*$/',$ip)){
			$this->ipvalid = true;
			$this->v['iptype'] = 'ipv4';
			$this->v['ip'] = preg_replace('/[ \s]+/','',$ip);
			$this->v['iplong'] = self::ipv4tolong($ip);
		}else{
			$this->ipvalid= false;	
		}
	}
	
	function __set($nm,$val){
		if($nm='ip'){
			$this->__constract($val);
		}
	}
	
	function __get($nm){
		if($nm=='ipvalid'){return $ipvalid;}
		elseif(isset($this->v[$nm])){
			return $this->v[$nm];
		}
	}
	/*
	 * 验证ip是否在给定的范围
	 * @params string $preview ip范围设置字符串
	 * @return boolean 是否在范给定范围内，如果是反回true,否则返回false.
	 */
	public function validate($preview){
		
		if(!$this->ipvalid){return ;}
		
		$preArr=self::analyzePreview($preview);
		switch($this->v['iptype']){
			case 'ipv4':
				return $this->_validateIpv4($preArr);
		}
		
	}
	
	/*
	 * 验证ipv4是否在给定范围内,在范围内返回ture,否则返回false
	 */
	private function _validateIpv4($preArr){
		
		foreach($preArr as $setitem){
			if(strpos($setitem,'-')!==false&&strpos($setitem,'*')!==false){
				
				$itemip=explode('-',$setitme);
				$minip=0;$maxip=0;
				foreach($itemip as $key => $ip){
					$ippurview=self::ipv4tolong($ip);
					if(strpos($ip,'*')!==false){
						if($ippurview[0]<=$this->v['iplong'] && $this->v['iplong'] <= $ippurview[1]){
							return true;
						}
						if($minip==0||$ippurview[0]<$minip){$minip=$ippurview[0];}
						if($maxip==0||$ippurview[1]>$maxip){$maxip=$ippurview[1];}
					}else{
						if($key<1){
							if($minip==0||$ippurview<$minip){$minip=$ippurview;}
						}else{
							if($maxip==0||$ippurview>$maxip){$maxip=$ippurview;}
						}
					}
					if($minip<=$this->v['iplong'] && $this->v['iplong'] <=$maxip){
						return true;
					}
				}
				
				
			}elseif(strpos($setitem,'-')!==false){
				
				$itemip=explode('-',$setitme);
				if(self::ipv4tolong($itemip[0])<$this->v['iplong'] && $this->v['iplong']< self::ipv4tolong($itemip[1])){
					return true;
				}
				
			}elseif(strpos($setitem,'*')!==false){
				$ippurview = self::ipv4tolong($setitem);
				if($ippurview[0]<=$this->v['iplong'] && $this->v['iplong'] <= $ippurview[1]){
					return true;
				}
			}else{
				$ippurview = self::ipv4tolong($setitem);
				if($ippurview==$this->v['iplong']){
					return true;
				}
			}
		}
		return false;
	}
	
	//反回ip的的整数，如果其中含*反回范围数组。
	public static function ipv4tolong($sectionip){
		if(preg_match('/^\s*((2[0-4]\d|25[0-5]|\*|[01]?\d\d?)\.){3}(2[0-4]\d|25[0-5]|\*|[01]?\d\d?)\s*$/',$sectionip)){
			if(strpos($sectionip,'*')!==false){
				$iparr = explode('.',$sectionip);
				$iparr = array_reverse($iparr);
				$minip=0;$maxip=0;
				foreach($iparr as $key=>$v){
					if($v=='*'){
						$minip+=0*pow(256,$key);
						$maxip+=255*pow(256,$key);
					}else{
						$minip+=$v*pow(256,$key);
						$maxip+=$v*pow(256,$key);
					}
				}
				return array($minip,$maxip);
			}else{
				return sprintf("%u", ip2long($sectionip));
			}
		}
		return 0;
	}
	
	//解析ip范围设置
	public static function analyzePreview($preview){
		
		$preArr=preg_split('/[, \n\r]+/',$preview,-1,PREG_SPLIT_NO_EMPTY);
		
		foreach($preArr as $key=>$val){
			$preArr[$key] = preg_replace('/[ \s]+/','',$val);
		}
		
		return $preArr;
	}
}
?>