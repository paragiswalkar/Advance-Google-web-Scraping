<?PHP
    /* License: 
       Open source for private and commercial use but this comment needs to stay untouched on top.
       URL of original source code: http://scraping.compunect.com
       Author of original source code: http://www.compunect.com
       IP rotation API code from here: http://www.us-proxies.com/automate
       Under no circumstances and under no legal theory, whether in tort (including negligence), contract, or otherwise, shall the Licensor be liable to anyone for any direct, indirect, special, incidental, or consequential damages of any character arising as a result of this License or the use of the Original Work including, without limitation, damages for loss of goodwill, work stoppage, computer failure or malfunction, or any and all other commercial damages or losses. This limitation of liability shall not apply to the extent applicable law prohibits such limitation.
       Usage exceptions:
       Public redistributing modifications of this source code project is not allowed without written agreement.
       Using this work for private and commercial projects is allowed, redistributing it is not allowed without our written agreement.
     */
	
    function verbose($text)
    {
        echo $text;
    }

    /*
     * By default (no force) the function will load cached data within 24 hours otherwise reject the cache.
     * Google does not change its ranking too frequently, that's why 24 hours has been chosen.
     *
     * Multithreading: When multithreading you need to work on a proper locking mechanism
     */
    function load_cache($search_string, $page, $country_data, $force_cache)
    {
        global $working_dir;
        global $NL;
        global $test_100_resultpage;

        if ($force_cache < 0) return NULL;
        $lc = $country_data['lc'];
        $cc = $country_data['cc'];
        if ($test_100_resultpage)
        {
            $hash = md5($search_string . "_" . $lc . "_" . $cc . "." . $page . ".100p");
        } else
        {
            $hash = md5($search_string . "_" . $lc . "_" . $cc . "." . $page);
        }
        $file = "$working_dir/$hash.cache";
        $now = time();
        if (file_exists($file))
        {
            $ut = filemtime($file);
            $dif = $now - $ut;
            $hour = (int)($dif / (60 * 60));
            if ($force_cache || ($dif < (60 * 60 * 24)))
            {
                $serdata = file_get_contents($file);
                $serp_data = unserialize($serdata);
                verbose("Cache: loaded file $file for $search_string and page $page. File age: $hour hours$NL");

                return $serp_data;
            }

            return NULL;
        } else
        {
            return NULL;
        }

    }

    /*
     * Multithreading: When multithreading you need to work on a proper locking mechanism
     */
    function store_cache($serp_data, $search_string, $page, $country_data)
    {
        global $working_dir;
        global $NL;
        global $test_100_resultpage;

        $lc = $country_data['lc'];
        $cc = $country_data['cc'];
        if ($test_100_resultpage)
        {
            $hash = md5($search_string . "_" . $lc . "_" . $cc . "." . $page . ".100p");
        } else
        {
            $hash = md5($search_string . "_" . $lc . "_" . $cc . "." . $page);
        }
        $file = "$working_dir/$hash.cache";
        $now = time();
        if (file_exists($file))
        {
            $ut = filemtime($file);
            $dif = $now - $ut;
            if ($dif < (60 * 60 * 24)) echo "Warning: cache storage initated for $search_string page $page which was already cached within the past 24 hours!$NL";
        }
        $serdata = serialize($serp_data);
        file_put_contents($file, $serdata, LOCK_EX);
        verbose("Cache: stored file $file for $search_string and page $page.$NL");
    }

    // check_ip_usage() must be called before first use of mark_ip_usage()
    function check_ip_usage()
    {
        global $PROXY;
        global $working_dir;
        global $NL;
        global $ip_usage_data; // usage data object as array

        if (!isset($PROXY['ready'])) return 0; // proxy not ready/started
        if (!$PROXY['ready']) return 0; // proxy not ready/started

        if (!isset($ip_usage_data))
        {
            if (!file_exists($working_dir . "/ipdata.obj")) // usage data object as file
            {
                echo "Warning!$NL" . "The ipdata.obj file was not found, if this is the first usage of the rank checker everything is alright.$NL" . "Otherwise removal or failure to access the ip usage data will lead to damage of the IP quality.$NL$NL";
                sleep(5);
                $ip_usage_data = array();
            } else
            {
                $ser_data = file_get_contents($working_dir . "/ipdata.obj");
                $ip_usage_data = unserialize($ser_data);
            }
        }

        if (!isset($ip_usage_data[$PROXY['address']]))
        {
            verbose("IP $PROXY[address] is ready for use $NL");

            return 1; // the IP was not used yet
        }
        if (!isset($ip_usage_data[$PROXY['address']]['requests'][20]['ut_google']))
        {
            verbose("IP $PROXY[address] is ready for use $NL");

            return 1; // the IP has not been used 20+ times yet, return true
        }
        $ut_last = (int)$ip_usage_data[$PROXY['address']]['ut_last-usage']; // last time this IP was used
        $req_total = (int)$ip_usage_data[$PROXY['address']]['request-total']; // total number of requests made by this IP
        $req_20 = (int)$ip_usage_data[$PROXY['address']]['requests'][10]['ut_google']; // the 20th request (if IP was used 20+ times) unixtime stamp [changed to 10 due to Google issues]

        $now = time();
        if (($now - $req_20) > (60 * 60))
        {
            verbose("IP $PROXY[address] is ready for use $NL");

            return 1; // more than an hour passed since 20th usage of this IP [changed to 10]
        } else
        {
            $cd_sec = (60 * 60) - ($now - $req_20);
            verbose("IP $PROXY[address] needs $cd_sec seconds cooldown, not ready for use yet $NL");

            return 0; // the IP is overused, it can not be used for scraping without being detected by the search engine yet
        }

    }

    /* Delay (sleep) based on the license size to allow optimal scraping
     *
     * Warning!
     * Do NOT change the delay to be shorter than the specified delay.
     * When scraping Google you should never do more than 20 requests per hour per IP address
     * The recommended value is 10, if you must go higher you can go up to 20 but I'd stay lower
     * This function will create a delay based on your total IP addresses.
     *
     * Together with the IP management functions this will ensure that your IPs stay healthy (no wrong rankings) and undetected (no virus warnings, blacklists, captchas)
     *
     * Multithreading:
     * When multithreading you need to multiply the delay time ($d) by the number of threads
     *
     * Due to Google getting stricter and stricter you might even have to lower the rate.
     */
    function delay_time()
    {
        global $NL;
        global $PLAN;
        $d = (3600 * 1000000 / (((float)$PLAN['total_ips']) * 10));
        verbose("Delay based on plan size.. $NL");
        usleep($d);
    }

    /*
     * Updates and stores the ip usage data object
     * Marks an IP as used and re-sorts the access array 
     */
    function mark_ip_usage()
    {
        global $PROXY;
        global $working_dir;
        global $NL;
        global $ip_usage_data; // usage data object as array

        if (!isset($ip_usage_data)) die("ERROR: Incorrect usage. check_ip_usage() needs to be called once before mark_ip_usage()!$NL");
        $now = time();

        $ip_usage_data[$PROXY['address']]['ut_last-usage'] = $now; // last time this IP was used
        if (!isset($ip_usage_data[$PROXY['address']]['request-total'])) $ip_usage_data[$PROXY['address']]['request-total'] = 0;
        $ip_usage_data[$PROXY['address']]['request-total']++; // total number of requests made by this IP
        // shift fifo queue
        for ($req = 19; $req >= 1; $req--)
        {
            if (isset($ip_usage_data[$PROXY['address']]['requests'][$req]['ut_google']))
            {
                $ip_usage_data[$PROXY['address']]['requests'][$req + 1]['ut_google'] = $ip_usage_data[$PROXY['address']]['requests'][$req]['ut_google'];
            }
        }
        $ip_usage_data[$PROXY['address']]['requests'][1]['ut_google'] = $now;

        $serdata = serialize($ip_usage_data);
        file_put_contents($working_dir . "/ipdata.obj", $serdata, LOCK_EX);

    }


    // access google based on parameters and return raw html or "0" in case of an error
    function scrape_google($search_string, $page, $local_data)
    {
        global $ch;
        global $NL;
        global $PROXY;
        global $PLAN;
        global $scrape_result;
        global $test_100_resultpage;
        global $filter;
        $scrape_result = "";
		$google_ip = "";
		
		if(isset($local_data['cc']) && $local_data['cc']=='global'){
			$google_ip = '216.58.197.228';
		}else if(isset($local_data['cc']) && $local_data['cc']=='uk'){
			$google_ip = '172.217.26.35';
		}else if(isset($local_data['cc']) && $local_data['cc']=='in'){
			$google_ip = '172.217.24.131';
		}else{
			$google_ip = '216.58.197.228';
		}
        
        $hl = $local_data['lc'];

        if ($page == 0)
        {
            if ($test_100_resultpage)
            {
                $url = "http://$google_ip/search?q=$search_string&hl=$hl&ie=utf-8&as_qdr=all&aq=t&rls=org:mozilla:us:official&client=firefox&num=100&filter=$filter";
            } else
            {
                $url = "http://$google_ip/search?q=$search_string&hl=$hl&ie=utf-8&as_qdr=all&aq=t&rls=org:mozilla:us:official&client=firefox&num=10&filter=$filter";
            }
        } else
        {

            if ($test_100_resultpage)
            {
                $num = $page * 100;
                $url = "http://$google_ip/search?q=$search_string&hl=$hl&ie=utf-8&as_qdr=all&aq=t&rls=org:mozilla:us:official&client=firefox&start=$num&num=100&filter=$filter";
            } else
            {
                $num = $page * 10;
                $url = "http://$google_ip/search?q=$search_string&hl=$hl&ie=utf-8&as_qdr=all&aq=t&rls=org:mozilla:us:official&client=firefox&start=$num&num=10&filter=$filter";
            }
        }
        //verbose("Debug, Search URL: $url$NL");

        curl_setopt($ch, CURLOPT_URL, $url);
        $htmdata = curl_exec($ch);
		
        if (!$htmdata)
        {
            $error = curl_error($ch);
            $info = curl_getinfo($ch);
            echo "\tError scraping: $error [ $error ]$NL";
            $scrape_result = "SCRAPE_ERROR";
            sleep(3);

            return "";
        } else
        {
            if (strlen($htmdata) < 20)
            {
                $scrape_result = "SCRAPE_EMPTY_SERP";
                sleep(3);

                return "";
            }
        }


        if (strstr($htmdata, "computer virus or spyware application"))
        {
            echo("Google blocked us, we need more proxies ! Make sure you did not damage the IP management functions. Consider changing keywords and lowering request rates. $NL");
            $scrape_result = "SCRAPE_DETECTED";
            die();
        }
        if (strstr($htmdata, "entire network is affected"))
        {
            echo("Google blocked us, we need more proxies ! Make sure you did not damage the IP management functions. Consider changing keywords and lowering request rates. $NL");
            $scrape_result = "SCRAPE_DETECTED";
            die();
        }
        if (strstr($htmdata, "http://www.download.com/Antivirus"))
        {
            echo("Google blocked us, we need more proxies ! Make sure you did not damage the IP management functions. Consider changing keywords and lowering request rates. $NL");
            $scrape_result = "SCRAPE_DETECTED";
            die();
        }
        if (strstr($htmdata, "/images/yellow_warning.gif"))
        {
            echo("Google blocked us, we need more proxies ! Make sure you did not damage the IP management functions. Consider changing keywords and lowering request rates. $NL");
            $scrape_result = "SCRAPE_DETECTED";
            die();
        }
        if (strstr($htmdata, "This page appears when Google automatically detects requests coming from your computer network"))
        {
            echo("Google blocked us, we need more proxies ! Make sure you did not damage the IP management functions. Consider changing keywords and lowering request rates. $NL");
            $scrape_result = "SCRAPE_DETECTED";
            die();
        }
        $scrape_result = "SCRAPE_SUCCESS";

        return $htmdata;
    }

    require_once "simple_html_dom.php";
    function process_raw_v2($data, $page)
    {
        global $process_result; // contains metainformation from the process_raw() function
        global $test_100_resultpage;
        global $NL;
        global $B;
        global $B_;
        $results=array();

        $html = new simple_html_dom();
        $html->load($data);
        /** @var $interest simple_html_dom_node */
        $interest = $html->find('div#ires ol div.g');
        echo "found interesting elements: ".count($interest)."\n";
        $interest_num=0;
        foreach ($interest as $li)
        {
            $result = array('title'=>'undefined','host'=>'undefined','url'=>'undefined','desc'=>'undefined','type'=>'organic');
            $interest_num ++;
            $h3 = $li->find('h3.r',0);
            if (!$h3)
            {
                continue;
            }
            $a = $h3->find('a',0);
            if (!$a) continue;
            $result['title'] = html_entity_decode($a->plaintext);
            $lnk = urldecode($a->href);
            if ($lnk)
            {
                preg_match('/(ht[^&]*)/', $lnk, $m);
                if ($m && $m[1])
                {
                    $result['url']=$m[1];
                    $tmp=parse_url($m[1]);
                    $result['host']=$tmp['host'];
                } else
                {
                    if (strstr($result['title'],'News')) $result['type']='news';
                    if (strstr($result['title'],'Images')) $result['type']='images';
                }
            }
            if ($result['type']=='organic')
            {
                $sp = $li->find('span.st',0);
                if ($sp)
                {
                    $result['desc']=html_entity_decode($sp->plaintext);
                    $sp->clear();
                }
            }
            $h3->clear();
            $a->clear();
            $li->clear();
            $results[]=$result;
        }
        $html->clear;





        // Analyze if more results are available (next page)
        $next = 0;
        if (strstr($data, "Next</a>"))
        {
            $next = 1;
        } else
        {
            if ($test_100_resultpage)
            {
                $needstart = ($page + 1) * 100;
            } else
            {
                $needstart = ($page + 1) * 10;
            }
            $findstr = "start=$needstart";
            if (strstr($data, $findstr)) $next = 1;
        }
        $page++;
        if ($next)
        {
            $process_result = "PROCESS_SUCCESS_MORE"; // more data available
        } else
        {
            $process_result = "PROCESS_SUCCESS_LAST";
        } // last page reached

        return $results;
    }

    function rotate_proxy()
    {
        global $PROXY;
        global $ch;
        global $NL;
        $max_errors = 3;
        $success = 0;
        while ($max_errors--)
        {
            $res = ip_service("rotate"); // will fill $PROXY
			
            $ip = "";
            if ($res <= 0)
            {
                verbose("API error: Proxy API connection failed (Error $res). trying again soon..$NL$NL");
                sleep(21); // retry after a while
            } else
            {
                verbose("API success: Received proxy IP $PROXY[address] on port $PROXY[port]$NL");
                $success = 1;
                break;
            }
        }
		
		if ($success)
        {
            $ch = new_curl_session($ch);

            return 1;
        } else
        {
            return "API rotation failed. Check license, firewall and API credentials.$NL";
        }
    }
	
	function ip_service($cmd, $proxys=NULL,$x = ""){
		global $PROXY;
        global $PLAN;
        global $NL;
		
		$proxy_arr = array();
		
		if ($cmd == "plan")
            {
				$data = explode(',',$proxys);
				
				foreach($data as $row){
						$ar[] = explode(":", $row);
				}
					
				foreach($ar as $num){
						$proxy_arr[] = $num[0];
				}
					
				$PLAN['total_ips'] = count($proxy_arr);
					
				if ($PLAN['total_ips'] > 0) $PLAN['active'] = 1; else $PLAN['active'] = 0;
					
                return 1;

            } // cmd==plan
		if ($cmd == "rotate")
            {
					$PROXY['ready'] = 0;
                
					$data = explode(',',$proxys);
					
					//if (array_count_values($data) > 10) return -1; // connection not possible
                    
					foreach($data as $row){
						$ar[] = explode(":", $row);
					}
					
					foreach($ar as $num){
						$proxy_arr[] = $num[0];
					}
					
					if ($proxy_id == 0) {
						$proxy_id = array_rand($proxy_arr);
					}
					
					$PROXY['address'] = $ar[$proxy_id][0];
                    $PROXY['port'] = $ar[$proxy_id][1];
                    $PROXY['ready'] = 1;
                    usleep(5000000); // additional time to avoid connecting during proxy bootup phase, removing this can cause random connection failures but will increase overall performance for large IP licenses
                    return 1;
            } // cmd==rotate	
	}
	
	/*
     * This is the API function to retrieve US IP addresses
     * On success this function will define the global $PROXY variable, adding the elements ready,address,port,address and return 1
     * On failure the return is 0 or smaller and the PROXY variable ready element is set to "0"
     * To obtain a plan please check out us-proxies.com, this can often be handled within a day
     */

    /* function ip_service($cmd, $x = "")
    {
        global $pwd;
        global $uid;
        global $PROXY;
        global $PLAN;
        global $NL;
		
		$proxy_arr = array();
		
		$fp = fopen("proxy_ips.txt","r");
		
		if (!$fp)
        {
            echo "Unable to open $NL";

            return -1; // connection not possible
        }else{
			
			if ($cmd == "plan")
            {
				stream_set_timeout($fp, 8);
                $res = "";
                $n = 0;
                while (!feof($fp))
                {
                    if ($n++ > 4) break;
                    $res .= fread($fp, 8192);
                }
                $info = stream_get_meta_data($fp);
				
                fclose($fp);

                if ($info['timed_out'])
                {
                    echo 'API: Connection timed out! $NL';

                    return -2; // api timeout
                } else
                {
					
                    if (strlen($res) > 1000) return -3; // invalid api response (check the API website for possible problems)
                    $data = extractBody($res);
					
                    foreach($data as $row){
						$ar[] = explode(":", $row);
					}
					
					foreach($ar as $num){
						$proxy_arr[] = $num[0];
					}
					
					$PLAN['total_ips'] = count($proxy_arr);
					
					if ($PLAN['total_ips'] > 0) $PLAN['active'] = 1; else $PLAN['active'] = 0;
					
                    return 1;
                }	

            } // cmd==plan
			
			if ($cmd == "rotate")
            {
                $PROXY['ready'] = 0;
                
				stream_set_timeout($fp, 8);
                $res = "";
                $n = 0;
                while (!feof($fp))
                {
                    if ($n++ > 4) break;
                    $res .= fread($fp, 8192);
                }
                $info = stream_get_meta_data($fp);
				
                fclose($fp);

                if ($info['timed_out'])
                {
                    echo 'API: Connection timed out! $NL';

                    return -2; // api timeout
                } else
                {	
					
                    if (strlen($res) > 1000) return -3; // invalid api response (check the API website for possible problems)
                    
					$data = extractBody($res);
					
					//if (array_count_values($data) > 10) return -1; // connection not possible
                    
					foreach($data as $row){
						$ar[] = explode(":", $row);
					}
					
					foreach($ar as $num){
						$proxy_arr[] = $num[0];
					}
					
					if ($proxy_id == 0) {
						$proxy_id = array_rand($proxy_arr);
					}
					
					$PROXY['address'] = $ar[$proxy_id][0];
                                        $PROXY['port'] = $ar[$proxy_id][1];
                                        $PROXY['ready'] = 1;
                                        usleep(5000000); // additional time to avoid connecting during proxy bootup phase, removing this can cause random connection failures but will increase overall performance for large IP licenses
                                        return 1;
                }
            } // cmd==rotate
			
		}
    } */
	
	/*
     * For country&language specific searches
     * The identifier codes require an active plan at us-proxies.com
     * If you plan to omit the IP service just replace that part too or do not use language specifications at all
     */
    function get_google_cc($cc, $lc)
    {
        global $pwd;
        global $uid;
        global $PROXY;
        global $PLAN;
        global $NL;
        $fp = fopen("google_ip.txt","r");
        if (!$fp)
        {
            echo "Unable to connect to google_cc API $NL";

            return NULL; // connection not possible
        } else
        {
//            echo("GET /g_api.php?api=1&uid=$uid&pwd=$pwd&cmd=google_cc&cc=$cc&lc=$lc HTTP/1.0\r\nHost: us-proxies.com\r\nAccept: text/html, text/plain, text/*, */*;q=0.01\r\nAccept-Encoding: plain\r\nAccept-Language: en\r\n\r\n");
            
			stream_set_timeout($fp, 8);
            $res = "";
            $n = 0;
            while (!feof($fp))
            {
                if ($n++ > 4) break;
                $res .= fread($fp, 8192);
            }
            $info = stream_get_meta_data($fp);
            fclose($fp);

            if ($info['timed_out'])
            {
                echo 'API: Connection timed out! $NL';

                return NULL; // api timeout
            } else
            {
                $data = extractBody($res);
                $obj = unserialize($data);
				
                if (isset($obj['error'])) echo $obj['error'] . "$NL";
                if (isset($obj['info'])) echo $obj['info'] . "$NL";

                return $obj['data'];

                if (strlen($data) < 4) return NULL; // invalid api response
            }
        }
    }
	
	// return 1 if license is ready, otherwise 0
    function get_license()
    {
        global $uid;
        global $pwd;
        global $PLAN;
        global $NL;

        $res = ip_service("plan");
        $ip = "";
        if ($res <= 0)
        {
            verbose("API error: Proxy API connection failed (Error $res). trying again later..$NL$NL");

            return 0;
        } else
        {
            ($PLAN['active'] == 1) ? $ready = "active" : $ready = "not active";
            verbose("API success: Account is $ready.$NL");
            if ($PLAN['active'] == 1) return 1;

            return 0;
        }

        return $PLAN;
    }


    function extractBody($response_str)
    {
        //$parts = preg_split('/(?:\r?\n){2}/', $response_str, 2);
		
		$parts = explode(PHP_EOL, $response_str);
		
        if (!empty($parts)) return $parts;

        return '';
    }

    function getip()
    {
        global $PROXY;
        if (!$PROXY['ready']) return -1; // proxy not ready

        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, 'http://ipcheck.ipnetic.com/remote_ip.php'); // returns the real IP
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
        $curl_proxy = $PROXY['address'].":".$PROXY['port'];
        curl_setopt($curl_handle, CURLOPT_PROXY, $curl_proxy);
        $tested_ip = curl_exec($curl_handle);

        if (preg_match("^([1-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}^", $tested_ip))
        {
            curl_close($curl_handle);

            return $tested_ip;
        } else
        {
            $info = curl_getinfo($curl_handle);
            curl_close($curl_handle);

            return 0; // possible error would be a wrong authentication IP or a firewall
        }
    }


    function new_curl_session($ch = NULL)
    {
        global $PROXY;
        if ((!isset($PROXY['ready'])) || (!$PROXY['ready'])) return $ch; // proxy not ready

        if (isset($ch) && ($ch != NULL))
        {
            curl_close($ch);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $curl_proxy = "$PROXY[address]:$PROXY[port]";
        curl_setopt($ch, CURLOPT_PROXY, $curl_proxy);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.0; en; rv:1.9.0.4) Gecko/2009011913 Firefox/3.0.6");



        return $ch;
    }


    function rmkdir($path, $mode = 0755)
    {
        if (file_exists($path)) return 1;

        return @mkdir($path, $mode);
    }

?>