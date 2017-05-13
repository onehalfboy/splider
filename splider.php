<?php

class splider
{
	private $urlPrefix = ""; 		//相对地址的前缀
	private $defaultIndex = ""; 	//默认采集文件
	private $baseDir = ""; 		//本地化的根目录
	private $urlIncA = array(); 	//url记录数组, url=>count
	private $startTime = 0;
	private $rulesA = array( 	//提取url地址的正则
			'/href="(.*?)"/is',
			'/src="(.*?)"/is',
			'/url\((.*?)\)/is',
		);

	public function __construct($urlPrefix = "http://down.admin5.com/", $defaultIndex = "index.html")
	{
		$this->urlPrefix = $urlPrefix;
		$this->defaultIndex = $defaultIndex;
		$this->baseDir = realpath(__DIR__ . "/../") . "/"; 
		$this->mkAllDir($this->baseDir);
		$this->startTime = microtime(true);
	}

	public function config($configA)
	{
		foreach ($configA as $name => $value)	
		{
			$this->{$name} = $value;
		}
	}

	public function getPage($url = "", $params = "", $parentUrl = "")
	{
		$url = $this->urlParamsDeal($url, $params, $parentUrl);
		if (empty($url))
		{
			return false;
		}
		$pageHtml = $this->http_get($url);
		if (empty($pageHtml) || !in_array($this->urlIncA[$url]["info"]["http_code"], array(200, 302, 303)))
		{
			$this->urlIncA[$url]["failCount"]++;
			return false;
		}
		$this->urlIncA[$url]["succCount"]++;
		$ret = $this->saveToLocal($url, $pageHtml);
		$this->urlIncA[$url]["save"] = $ret;
		if (empty($ret))
		{
			return false;
		}
		$suffix = $this->getFileSuffix($url);
		if (in_array($suffix, array("jpg", "png", "jpeg", "gif", "ico", "swf")))
		{
			return true;
		}
		$urlA = $this->getPageUrl($pageHtml);
		$this->urlIncA[$url]["urlA"] = $urlA;
		if (empty($urlA))
		{
			return false;
		}
		foreach ($urlA as $urlTmp)
		{
			$this->getPage($urlTmp, "", $url);
		}
	}

	public function getPageUrl($pageHtml)
	{
		$urlA = array();
		$rulesA = $this->rulesA;	
		foreach ($rulesA as $rule)
		{
			$ret = preg_match_all($rule, $pageHtml, $matchA);
			if (!empty($ret))
			{
				foreach ($matchA[1] as $url)
				{
					$urlA[$url]++;
				}
			}
		}
		if (!empty($urlA))
		{
			$urlA = array_keys($urlA);
		}

		return $urlA;
	}

	public function saveToLocal($url, $pageHtml)
	{
		$len = strlen($this->urlPrefix);
		$path = substr($url, $len);
		$linkPos = strpos($path, "?");
		if ($linkPos !== false)
		{
			$path = substr($path, 0, -1 * $linkPos);
		}
		$path = trim($path);
		if (empty($path))
		{
			return false;
		}
		$path = $this->baseDir . $path;
		$ret = $this->mkAllDir(dirname($path));
		$this->urlIncA[$url]["path"] = $path;
		if (empty($ret))
		{
			echo "[{$path} : directory created fail]";
			exit();
		}

		return file_put_contents($path, $pageHtml);
	}

	private function getFileSuffix($url)
	{
		$urlA = parse_url($url);
		if (empty($urlA["path"]))
		{
			return false;
		}
		$fileName = basename($urlA["path"]);
		$suffix = end(explode(".", $fileName));

		return $suffix;
	}

	public function showResult()
	{
		echo "[" . date("Y-m-d H:i:s") . "]<br>";
		$cos = microtime(true) - $this->startTime;
		echo "[cos: {$cos}s]<br>";
		echo "<pre>";
		var_dump($this->urlIncA);
	}

	public function showHistoryResult()
	{
		$this->urlIncA = include "result.php";
		$this->showResult();
	}

	public function logResult()
	{
		$cos = microtime(true) - $this->startTime;
		$content = "<?php\n//[" . date("Y-m-d H:i:s") . "]\n//[cos: {$cos}s]\n";
		$content .= "return " . var_export($this->urlIncA, true) . ";";
			
		return file_put_contents("result.php", $content);
	}
 	
	public function urlParamsDeal($url, $params = "", $parentUrl = "")
	{
		$url = trim($url);
		$linkPos = strpos($url, "#");
		if ($linkPos !== false)
		{
			$url = substr($url, 0, -1 * $linkPos);
		}
		$findFlag = "../";
		$findLen = strlen($findFlag);
		$findPos = strpos($url, $findFlag);
		if ($findPos === 0 &&!empty($parentUrl) && !empty($this->urlIncA[$parentUrl]["save"]))
		{
			$parentUrl = dirname($parentUrl);
			while ($findPos === 0) 
			{
				$parentUrl = dirname($parentUrl);
				$url = substr($url, $findLen);		
				$findPos = strpos($url, $findFlag);
			}
			$url = $parentUrl . "/" . $url;
		}
		if (strpos($url, "http://") !== 0)
		{
			$url = $this->urlPrefix . ltrim($url, "/");
		}
		if (strpos($url, $this->urlPrefix) === false)
		{
			return false;
		}
		$len = strlen($url);
		if (substr($url, -1) == "/")
		{
			$url = rtrim($url, "/") . "/" . $this->defaultIndex;
		}
		if (!empty($params))
		{
			$link = "?";
			if (strpos($url, $link) !== false)
			{
				$link = "&";
			}
			if (is_string($params))
			{
				$url .= $link . $params;
			}
			else if (is_array($params))
			{
				foreach ($params as $name => $value)
				{
					$url .= $link . $name . "=" . $value;
					$link = "&";
				}
			}
		}

		return $url;
	}

	/**
	 * curl获取内容
	 * @param type $url
	 * @param type $options
	 * @return type
	 */
	function curl_get_contents($url, $options = array())
	{
		if (isset($this->urlIncA[$url]["count"]))
		{
			$this->urlIncA[$url]["count"]++;
			return NULL;
		}
		$this->urlIncA[$url]["count"] = 1;
		$default = array(
			CURLOPT_URL => $url,
			CURLOPT_FRESH_CONNECT => 1, //强刷	
			CURLOPT_HEADER => 0, //返回是否包括头部信息
			CURLOPT_RETURNTRANSFER => 1, //把输出转化为字符串，而不是直接输出到屏幕
			CURLOPT_USERAGENT => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.86 Safari/537.36", //"Mozilla/5.0 (Windows NT 6.1; rv:17.0) Gecko/17.0 Firefox/17.0",
			CURLOPT_CONNECTTIMEOUT => 30,
			CURLOPT_TIMEOUT => 60,
		);
		foreach ($options as $key => $value)
		{
		$default[$key] = $value;
		}
		$ch = curl_init();
		curl_setopt_array($ch, $default);
		$result = curl_exec($ch);
		$error = curl_errno($ch);
		$info = curl_getinfo($ch);
		$this->urlIncA[$url]["info"]["error_code"] = $error;
		$this->urlIncA[$url]["info"]["total_time"] = $info["total_time"];
		$this->urlIncA[$url]["info"]["http_code"] = $info["http_code"];
		$this->urlIncA[$url]["info"]["size_download"] = $info["size_download"];
		curl_close($ch);
		return $result;
	}

	/**
	 * http get请求
	 * @param type $url
	 * @param type $params
	 * @param type $options
	 * @return type
	 */
	function http_get($url, $params = array(), $options = array())
	{
	    $paramsFMT = array();
	    foreach ($params as $key => $val)
	    {
	        $paramsFMT[] = $key . "=" . urlencode($val);
	    }
	    return $this->curl_get_contents($url . ($paramsFMT ? ( "?" . join("&", $paramsFMT)) : ""), $options);
	}

	/**
	 * http post请求
	 * @param type $url
	 * @param type $params
	 * @param type $options
	 * @return type
	 */
	function http_post($url, $params = array(), $options = array())
	{
	    $paramsFMT = array();
	    foreach ($params as $key => $val)
	    {
	        $paramsFMT[] = $key . "=" . urlencode($val);
	    }
	    $options[CURLOPT_POST] = 1;
	    $options[CURLOPT_POSTFIELDS] = join("&", $paramsFMT);
	    return $this->curl_get_contents($url, $options);
	}	
	
	/**
	 * 递归创建所有目录
	 * @author lynn
	 * @param  string $path 完整路径名,不包括文件名
	 * @return boolean       目录是否存在或是否创建成功
	 */
	function mkAllDir($path)
	{
	    $ret = true;
	    if (!is_dir($path))
	    {
	        $pathTmp = dirname($path);
	        $ret = $this->mkAllDir($pathTmp);
	        if ($ret)
	        {
	            $ret = mkdir($path);
	        }
	    }

	    return $ret;
	}
}
