<?php
	# -s	HTTP headers
	# -v	URL
	# -l	Loggind ORM actions
	# -e	Shows all errors

	class OData
		{
		private static $entities;
	
		private function __construct()
			{
			}
	
		public static function __request(string $url, $data = [], $HTTPMethod='GET')
			{
			if(!$HTTPMethod || !in_array($HTTPMethod,['GET','POST','PUT','DELETE','PATCH']))
				{
				throw new Exception('HTTP method not supported: '.$HTTPMethod);
				}
			$url = str_replace('&&','&',str_replace(' ','%20',$url));
			Console::isKey('v') ? Console::Write(['[purple]'.$HTTPMethod.'[/purple]','[yellow]'.explode(self::getURL(),rawurldecode($url))[1].'[/yellow]']) : null;
			$curl = curl_init($url);
			$curlOptions = [
				CURLOPT_HTTPHEADER						=>	['Content-Type: application/json;odata.metadata=minimal','Accept: application/json'],
				CURLOPT_RETURNTRANSFER					=>	true,
				CURLINFO_HEADER_OUT						=>	true,
				CURLOPT_CUSTOMREQUEST					=>	$HTTPMethod,
				CURLOPT_TIMEOUT							=>	100,
				CURLOPT_POSTFIELDS						=>	json_encode($data,JSON_UNESCAPED_SLASHES + JSON_PARTIAL_OUTPUT_ON_ERROR + JSON_HEX_QUOT + JSON_UNESCAPED_UNICODE + JSON_PARTIAL_OUTPUT_ON_ERROR + JSON_THROW_ON_ERROR),
				];
			curl_setopt_array($curl,$curlOptions);
			$response = curl_exec($curl);
			$httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
			$json = @json_decode($response,true);
			if(Console::isKey('s') || $httpCode == 400)
				{
				#echo'<pre>';print_r($data);echo'</pre>';
				Console::Write(curl_getinfo($curl, CURLINFO_HEADER_OUT));
				Console::Write(json_encode($data,JSON_PRETTY_PRINT));
				}
			if($httpCode == 0)
				{
				throw new Exception('Service unavailable');
				}
			if($httpCode == 404 || ($httpCode == 204 && $HTTPMethod == 'GET'))
				{
				throw new ODataNotFoundException;
				}
			if($httpCode < 200 || $httpCode >= 300)
				{
				if(is_array($json) && array_key_exists('error',$json))
					{
					$errorText = Router::Template([$httpCode,$json['error']['message'],$json['error']['code'] ? $json['error']['code'] : '-'],'HTTP code: $1 (message: $2, code: $3)');
					throw new Exception($errorText);
					}
				throw new Exception(Router::Template([$httpCode,$HTTPMethod,$url],'Method: $2, HTTP code: $1, URL: $3'));
				}
			return is_array($json) ? $json : $httpCode;
			}
	
		public static function getURL()
			{
			return SHERP_API;
			}

		public static function getUser()
			{
			return SHERP_API_USER;
			}

		public static function getEntities(): array
			{
			if(!self::$entities)
				{
				self::$entities = Heap::Get(__CLASS__,'getEntities');
				if(!self::$entities)
					{
					self::$entities = [];
					$entsData = self::__request(self::getURL().(new ODataQuery));
					foreach($entsData && isset($entsData['value']) ? $entsData['value'] : [] as $item)
						{
						self::$entities[] = $item['name'];
						}
					Heap::Set(__CLASS__,'getEntities',self::$entities,86400,['odata']);
					}
				}
			return self::$entities;
			}
	
		public static function __callStatic($method, $arguments = []): ODataSet
			{
			if(self::getEntities() && !in_array($method, self::$entities))
				{
				throw new Exception('Method does not exists: '.$method);
				}
			return new ODataSet($method,$arguments);
			}
	
		# prepare properties for Create/Update
		private static function recPrepareDate($data)
			{
			foreach($data as $i=>$val)
				{
				$data[$i] = is_array($val) ? self::recPrepareDate($val) : self::processValue($val);
				}
			return $data;
			}
	
		private static function processValue($val)
			{
			return is_object($val) ? $val->getReference() : $val;
			}
	
		public static function Create(string $entity,array $data) : ODataObject
			{
			if (!in_array($entity,self::getEntities()))
				{
				throw new Exception('Entity does not exists: '.$entity);
				}
			$data = self::recPrepareDate($data);
			$url = self::getURL().'/'.$entity;
			$response = self::__request($url,$data,'POST');
			$classname = ODataSet::entitypref.$entity;
			if(!$response || !is_array($response) || !array_key_exists($classname::pk,$response))
				{
				throw new Exception('Can not create object');
				}
			return self::$entity([$classname::pk=>$response[$classname::pk]])->shift();
			}
	
		public static function Update(ODataObject $object,array $data) : bool
			{
			$url = self::getURL().'/'.$object->getObjectLink();
			$data = self::recPrepareDate($data);
			return self::__request($url, $data, 'PATCH') == 204;
			}
	
		public static function Delete(ODataObject $object) : bool
			{
			return false;
			$url = self::getURL().'/'.$object->getObjectLink();
			self::__request($url, [], 'DELETE');
			return true;
			}
		}