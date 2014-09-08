<?php

class commonTools{
	private static $_models = array();
	public static function model($className=__CLASS__)
	{
		if(isset(self::$_models[$className]))
			return self::$_models[$className];
		else
		{
			$model=self::$_models[$className]=new $className();
			return $model;
		}
	}
	public function getCurl($url)
	{

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:23.0) Gecko/20100101 Firefox/23.0');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_ENCODING, "gzip" ); 
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		$output = curl_exec($ch);
		if(curl_errno($ch)){ 
		  echo 'Curl error: ' . curl_error($ch)."curl error num".curl_errno($ch)."\r\n"; 
		}
		if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == '200') {
		    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		    $header = substr($output, 0, $headerSize);
		    $body = substr($output, $headerSize);
		    print_r($header);
		}
		curl_close($ch);
		return $output;
	}
	//得到所需的URL链接
	public function getVisitUrl($where)
	{
		$url = urlQueue::model()->flush($where);
		if(empty($url)){
			echo "未抓取的链接库已空，开始解析已抓取的链接\r\n";
			$url = urlResouce::model()->flush($where);
			urlResouce::model()->updateResouce(array('status' =>1),array('url'=>$url['url'],'target'=>$target));
			if(empty($url)){
				urlResouce::model()->updateResouce(array('status' =>0),array('target'=>$target));
				return false;
			}
		}
		return $url['url'];
	}
	public function getParseResult($rules =null,$outPut=null,$url=null,$target=null,$items =array())
	{
		if(!empty($outPut)){
			$pattern = preg_quote($rules);	
			preg_match_all('%'.$pattern.'%',$outPut,$preg_rs);		
			$links = array_unique($preg_rs[0]);	
			return $links;	
		}
		else{
			if((!empty($url)) && (!empty($target))){
				$doc = new spiderDoc($target);
				if($doc->spider($url,''))
				{
					return $doc->getItems($items);
				}
			}
		}
		return null;
	}
	public function insertArr($url,$target)
	{
		if(urlQueue::model()->count(array('url'=>$url,'target'=>$target)) > 0){
			echo "isExist in queue\r\n";
		}
		else{
			if(urlResouce::model()->hasExist(array('url'=>$url,'target'=>$target))){
				urlResouce::model()->updateResouce(array('is_exist' =>2),array('url'=>$url,'target'=>$target));
				echo "isExist in resource\r\n";
			}
			else{
				$data = array(
					'target' => $target,
					'url' => $url,
					'status' => 0,
					'dateline' => TIMESTAMP
				);
				urlQueue::model()->add($data);
			}
		}
	}
	public function getHouse_downSell($info){
		$url_arr = pathinfo($info['url']);
		$house_id = $url_arr['filename'];
		if(!empty($house_id)){
			echo $house_id."\r\n";
			$kuaizuApi = new serverExt('ikuaizu_spider');
			$req = $kuaizuApi->api()->spider_offSellHouse(array('house_id'=>$house_id));
			print_r($req);			
		}
	}
	public function getXuequHouseUrl($url = array()){
		$queue =  new queueDb();
		foreach ($url as $key => $value) {
			if($queue->isExist(array('url' => $value))){
				continue;
			}
			if(urlResouce::model()->hasExist(array('url'=>$value))){
				continue;
			}
			$data = array(
				'target' => 'xuequ_house',
				'url' => $value,
				'status' => 0,
				'dateline' => TIMESTAMP
			);
			print_r($data);
			$queue->put($data);			
		}
	}
	public function stringtr(){
		$var = '海淀';
		$num = strrpos($var,'区');
		if(!$num)
		echo $var.'区';
	}

}