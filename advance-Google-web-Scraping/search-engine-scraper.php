#!/usr/bin/php
<?php
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

    ini_set("memory_limit","2048M"); // For scraping 100 results pages 32MB memory expected, for scraping the default 10 results pages 4MB are expected. 64MB is selected just in case.
    ini_set("xdebug.max_nesting_level","2000"); // precaution, might not be required. our parser will require a deep nesting level but I did not check how deep a 100 result page actually is.
    error_reporting(E_ALL & ~E_NOTICE);
    // ************************* Configuration variables *************************
    
    // General configuration
    $test_website_url = isset($_GET['domain'])?$_GET['domain']:NULL; // The URL, or a sub-string of it, of the indexed website.
    $test_keywords = isset($_GET['query'])?$_GET['query']:NULL; // comma separated keywords to test the rank for
	$get_proxys = isset($_GET['proxy'])?$_GET['proxy']:NULL;
    $test_max_pages = 3; // The number of result pages to test until giving up per keyword.
    $test_100_resultpage = 0; // Warning: Google ranking results may  become inaccurate
	
    /* Local result configuration. Enter 'help' to receive a list of possible choices. use global and en for the default worldwide results in english 
     * You need to define a country as well as the language. Visit the Google domain of the specific country to see the available languages.
     * Only a correct combination of country and language will return the correct search engine result pages. */
    $test_country = isset($_GET['google_dom'])?$_GET['google_dom']:NULL; // Country code. "global" is default. Use "help" to receive a list of available codes. [com,us,uk,fr,de,...]
    $test_language = "en"; // Language code. "EN" is default Use "help" to receive a list. Visit the local Google domain to find available langauges of that domain. [en,fr,de,...]
    $filter = 1; // 0 for no filter (recommended for maximizing content), 1 for normal filter (recommended for accuracy)
    $force_cache = 0; // set this to 1 if you wish to force the loading of cache files, even if the files are older than 24 hours. Set to -1 if you wish to force a new scrape.
    $load_all_ranks = 1; /* set this to 0 if you wish to stop scraping once the $test_website_url has been found in the search engine results,
                         * if set to 1 all $test_max_pages will be downloaded. This might be useful for more detailed ranking analysis.*/

    $show_html = 0; // 1 means: output formated with HTML tags. 0 means output for console (recommended script usage)
    $show_all_ranks = 1; // set to 1 to display a complete list of all ranks per keyword, set to 0 to only display the ranks for the specified website
    // ***************************************************************************
    $working_dir = "./local_cache"; // local directory. This script needs permissions to write into it


    require "functions-ses.php";


$page = 0;
$PROXY = array(); // after the rotate api call this variable contains these elements: [address](proxy host),[port](proxy port),[external_ip](the external IP),[ready](0/1)
$PLAN = array();
$results = array();


if ($show_html) $NL = "<br>\n"; else $NL = "\n";
if ($show_html) $HR = "<hr>\n"; else $HR = "_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_\n";
if ($show_html) $B = "<b>"; else $B = "!";
if ($show_html) $B_ = "</b>"; else $B_ = "!";
if ($show_html) $style = "style='font-weight:bold;'"; else $style = "style='font-weight:bold;'";
if ($show_html) $class = ""; else $class = "odd";

echo "<style>
table.dataTable {
    clear: both;
    margin-bottom: 6px !important;
    margin-top: 6px !important;
    max-width: none !important;
}
.table {
    margin-bottom: 20px;
    /*max-width: 100%;
    width: 100%;*/
}
table {
    background-color: transparent;
}
table {
    border-collapse: collapse;
    border-spacing: 0;
}
.table > thead > tr > th, .table > tbody > tr > th, .table > tfoot > tr > th, .table > thead > tr > td, .table > tbody > tr > td, .table > tfoot > tr > td {
    border-top: 1px solid #ddd;
    line-height: 1.42857;
    padding: 8px;
}
table.dataTable td, table.dataTable th {
    box-sizing: content-box;
}
th {
    text-align: left;
}
.odd{
	background-color:#999999;
}
</style>";
/*
 * Start of main()
 */

if ($show_html)
{
    echo "<html><body>";
}else{
    echo "<html><head><script type='text/javascript' src='js/jquery-1.7.1.min.js'></script></head><body>";
}

$keywords = explode(",", $test_keywords);

ip_service('rotate',$get_proxys);

if (!count($keywords)) die ("Error: no keywords defined.$NL");
if (!rmkdir($working_dir)) die("Failed to create/open $working_dir$NL");

$country_data = array('cc'=>$test_country,'lc'=>$test_language);

if (!$country_data) die("Invalid country/language code specified.$NL");

echo "$NL$B Search Engine Scraper for $test_website_url initated $B_ $NL$NL"."<br>";

/*
 * This loop iterates through all keyword combinations
 */
$ch = NULL;
$rotate_ip = 0; // variable that triggers an IP rotation (normally only during keyword changes)
$max_errors_total = 3; // abort script if there are 3 keywords that can not be scraped (something is going wrong and needs to be checked)

$rank_data = array();
$siterank_data = array();

$break=0; // variable used to cancel loop without losing ranking data


foreach ($keywords as $keyword)
	{
		$rank = 0;
		$max_errors_page = 5; // abort script if there are 5 errors in a row, that should not happen

		if ($test_max_pages <= 0) break;
		$search_string = urlencode($keyword);
		$rotate_ip = 1; // IP rotation for each new keyword

		/*
		* This loop iterates through all result pages for the given keyword
		*/
		for ($page = 0; $page < $test_max_pages; $page++)
		{
			$serp_data = load_cache($search_string, $page, $country_data, $force_cache); // load results from local cache if available for today
			$maxpages = 0;

			if (!$serp_data)
			{
				$ip_ready = check_ip_usage(); // test if ip has not been used within the critical time
				while (!$ip_ready || $rotate_ip)
				{	
					$ok = rotate_proxy(); // start/rotate to the IP that has not been started for the longest time, also tests if proxy connection is working
					
					if ($ok != 1)
					{
						die ("Fatal error: proxy rotation failed:$NL $ok$NL");
					}
					$ip_ready = check_ip_usage(); // test if ip has not been used within the critical time
					if (!$ip_ready)
					{
						die("ERROR: No fresh IPs left, try again later. $NL");
					} else
					{
						$rotate_ip = 0; // ip rotated
						break; // continue
					}
				}

				//delay_time(); // stop scraping based on the license size to spread scrapes best possible and avoid detection
				global $scrape_result; // contains metainformation from the scrape_serp_google() function
				$raw_data = scrape_google($search_string, $page, $country_data); // scrape html from search engine
				
				if ($scrape_result != "SCRAPE_SUCCESS")
				{
					if ($max_errors_page--)
					{
						echo "There was an error scraping (Code: $scrape_result), trying again .. $NL"."<br>";
						$page--;
						continue;
					} else
					{
						$page--;
						if ($max_errors_total--)
						{
							echo "Too many errors scraping keyword $search_string (at page $page). Skipping remaining pages of keyword $search_string .. $NL"."<br>";
							break;
						} else
						{
							die ("ERROR: Max keyword errors reached, something is going wrong. $NL");
						}
						break;
					}
				}
				mark_ip_usage(); // store IP usage, this is very important to avoid detection and gray/blacklistings
				global $process_result; // contains metainformation from the process_raw() function
				$serp_data = process_raw_v2($raw_data, $page); // process the html and put results into $serp_data

				if (($process_result == "PROCESS_SUCCESS_MORE") || ($process_result == "PROCESS_SUCCESS_LAST"))
				{
					$result_count = count($serp_data);
					$serp_data['page'] = $page;
					if ($process_result != "PROCESS_SUCCESS_LAST")
					{
						$serp_data['lastpage'] = 1;
					} else
					{
						$serp_data['lastpage'] = 0;
					}
					$serp_data['keyword'] = $keyword;
					$serp_data['cc'] = $country_data['cc'];
					$serp_data['lc'] = $country_data['lc'];
					$serp_data['result_count'] = $result_count;
					store_cache($serp_data, $search_string, $page, $country_data); // store results into local cache
				}

				if ($process_result != "PROCESS_SUCCESS_MORE")
				{
					$break=1;
					//break;
				} // last page
				if (!$load_all_ranks)
				{
					for ($n = 0; $n < $result_count; $n++)
						if (strstr($results[$n]['url'], $test_website_url))
						{
							verbose("Located $test_website_url within search results.$NL");
							$break=1;
							//break;
						}
				}

			} // scrape clause

			$result_count = $serp_data['result_count'];
			
			for ($ref = 0; $ref < $result_count; $ref++)
			{
				$rank++;
				$rank_data[$keyword][$rank]['title'] = $serp_data[$ref]['title'];
				$rank_data[$keyword][$rank]['url']  = $serp_data[$ref]['url'];
				$rank_data[$keyword][$rank]['host'] = $serp_data[$ref]['host'];
				$rank_data[$keyword][$rank]['desc'] = $serp_data[$ref]['desc'];
				$rank_data[$keyword][$rank]['type'] = $serp_data[$ref]['type'];
				//$rank_data[$keyword][$rank]['desc']=$serp_data['desc'']; // not really required
				if (strstr($rank_data[$keyword][$rank]['url'], $test_website_url))
				{
					$info = array();
					$info['rank'] = $rank;
					$info['url'] = $rank_data[$keyword][$rank]['url'];
					$siterank_data[$keyword][] = $info;
				}
			}
			if ($break == 1) break;

		} // page loop
	} // keyword loop
if ($show_all_ranks)
{
    foreach ($rank_data as $keyword => $ranks)
    {
        echo "<br>"."$NL$NL$B" . "Ranking information for keyword \"$keyword\" $B_$NL"."<br>";
		echo "<table class='table dataTable table-striped no-footer'>
				<thead>
					<tr>
						<th>Rank</th>
						<th>Type</th>
						<th>Title</th>
						<th>Description</th>
						<th>Website</th>
						<th>Domain</th>
					</tr>
				</thead><tbody>";
        //echo "$B" . "Rank [Type] - Website -  Title$B_$NL"."<br>";
        $pos = 0;
        foreach ($ranks as $rank)
        {
            $pos++;
            if (strstr($rank['url'], $test_website_url))
            {
				echo "<tr class=$class $style>
							<td>$B$pos</td>
							<td>[$rank[type]]</td>
							<td>$rank[title]$B_$NL</td>
							<td>$rank[desc]</td>
							<td><a href='".$rank['url']."' id='".'link_'.$pos."' target='"."frame_".$pos."'>$rank[url]</a></td>
							<td>$rank[host]</td>
							<td><iframe id='"."frame_".$pos."' name='"."frame_".$pos."' src=".$rank[url]." onLoad='iframeDidLoad();' style='width:500px;height:250px;'></iframe></td>
						</tr>";
                //echo "$B$pos [$rank[type]] - $rank[url] - $rank[title] $B_$NL"."<br>";
//                    echo $rank['desc']."\n";
				//header('Location:'.$rank['url'], true, 301);
				echo '<script>
							$(document).ready(function(){
                                                            $("#link_'.$pos.'").find("a").trigger("click");
                                                            //$("#link_'.$pos.'").click();
                                                        });
							function iframeDidLoad(){
								document.getElementById("'.'frame_'.$pos.'").src = sites[Math.floor(Math.random() * sites.length)];
							}
						</script>';
            } else
            {
				echo "<tr>
							<td>$pos</td>
							<td>[$rank[type]]</td>
							<td>$rank[title]</td>
							<td>$rank[desc]</td>
							<td>$rank[url]</td>
							<td>$rank[host]</td>
						</tr>";
                //echo "$pos [$rank[type]] - $rank[url] - $rank[title] $NL"."<br>";
//                    echo $rank['desc']."\n";
            }
        }
		echo "</tbody></table>";
		
    }
}


foreach ($keywords as $keyword)
{
    if (!isset($siterank_data[$keyword]))
    {
        echo "$NL$B" . "The specified site was not found in the search results for keyword \"$keyword\". $B_$NL"."<br>";
    } else
    {
        $siteranks = $siterank_data[$keyword];
        echo "$NL$NL$B" . "Ranking information for keyword \"$keyword\" and website \"$test_website_url\" [$test_country / $test_language] $B_$NL"."<br>";
        foreach ($siteranks as $siterank)
            echo "Rank $siterank[rank] for URL $siterank[url]$NL"."<br>";
    }
}
//var_dump($siterank_data);


if ($show_html)
{
    echo "</body></html>";
}else{
    echo "</body></html>";
}

?>