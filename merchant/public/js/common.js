/* 
	信用卡基本验证类
*/
function cc_validation(){
	var cc_type='', cc_number='', cc_cvv2='', cc_expiry_month=0, cc_expiry_year=0;
	this.validate=function(number, cvv2, expiry_m, expiry_y){
		cc_number=number.replace(/[^0-9]/g,'');
		
		//信用卡类型验证
		if(cc_number.match(/^4[0-9]{12}([0-9]{3})?$/)){
			cc_type='Visa';
		}else if(cc_number.match(/^5[1-5][0-9]{14}$/)){
			cc_type='Master Card';	
		}else if(cc_number.match(/^3[47][0-9]{13}$/)){
			cc_type='American Express';
		}else if(cc_number.match(/^3(0[0-5]|[68][0-9])[0-9]{11}$/)){
			cc_type='Diners Club'
		}else if(cc_number.match(/^6011[0-9]{12}$/)){
			cc_type='Discover';
		}else if(cc_number.match(/^(3[0-9]{4}|2131|1800)[0-9]{11}$/)){
			cc_type='JCB';
		}else if(cc_number.match(/^5610[0-9]{12}$/)){
			cc_type='Australian BankCard';
		}else{return -1;}
		
		//验证cvv2输入是否正确
		cc_cvv2 =cvv2.replace(/[^0-9]/,'');
		if(cc_type=='Visa'){
			if(!cc_cvv2.match(/^[0-9]{3}$/)){return -5;}
		}else if(cc_type == 'American Express'){
			if(!cc_cvv2.match(/^[0-9]{4}$/)){return -5;}
		}else{
			if(!cc_cvv2.match(/^[0-9]{3,4}$/)){return -5;}
		}
		
		//验证过期月份输入是否正确
		if(!isNaN(expiry_m) && expiry_m >0 && expiry_m <13){
			cc_expiry_month=expiry_m;
		}else{return -2;}
		
		date=new Date();
		current_year =date.getFullYear();
		
		if(current_year.toString().length>2){
			expiry_y=parseInt(current_year.toString().substr(0,2)+expiry_y.toString());
		}
		
		if(!isNaN(expiry_y) && expiry_y >= current_year && expiry_y < current_year+10){
			cc_expiry_year=expiry_y;	
		}else{
			return -3;
		}
		
		if(expiry_y == current_year){
			if(expiry_m < date.getMonth()){return -4;}
		}
		//信用卡号验证
		return this.is_valid();
		
	};
	
	this.is_valid = function(){
		var cardNumber='',numSum=0,currentNum=0;
		for(var i=cc_number.length-1;i>=0;i--){
			cardNumber +=cc_number.substr(i,1);
		}
		
		for(var i=0;i<cardNumber.length;i++){
			currentNum = parseInt(cardNumber.substr(i,1));
			// Double every second digit
			if(i % 2 ==1){currentNum*=2;}
			// Add digits of 2-digit numbers together
			if(currentNum>9){
				firstNum =currentNum % 10;
				secondNum = (currentNum -firstNum) /10;
				currentNum = firstNum +secondNum;
			}
			
			numSum += currentNum;
		}
		// If the total has no remainder it's OK
		return (numSum % 10 == 0);
	};
	
	this.get_cc_type=function(){
		return cc_type;
	}
}