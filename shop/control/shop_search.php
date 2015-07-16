<?php
/**
 * 店铺搜索
 *
 * 
 *
 *
 * by  校帮 运营版
 */
defined('InSchoolAssistant') or exit('Access Invalid!');

class shop_searchControl extends BaseHomeControl {
	/**
	 * 店铺列表
	 */
	public function indexOp(){
		/**
		 * 读取语言包
		 */
		Language::read('home_store_class_index');
		$lang	= Language::getLangContent();

		//店铺类目快速搜索
		$class_list = ($h = rkcache('store_class')) ? $h :rkcache('store_class',true,'file');
		if (!key_exists($_GET['cate_id'],$class_list)) $_GET['cate_id'] = 0;
		Tpl::output('class_list',$class_list);

		//店铺搜索
		$model = Model();
		$condition = array();
		$keyword = trim($_GET['keyword']);
		if(C('fullindexer.open') && !empty($keyword)){
			//全文搜索
			$condition = $this->full_search($keyword);
		}else{
			if ($keyword != ''){
				$condition['store_name|store_zy'] = array('like','%'.$keyword.'%');
			}
			if ($_GET['user_name'] != ''){
				$condition['member_name'] = trim($_GET['user_name']);
			}
		}
		if (!empty($_GET['area_id'])){
			$condition['area_id'] = intval($_GET['area_id']);
		}
		if ($_GET['cate_id'] > 0){
			$child = array_merge((array)$class_list[$_GET['cate_id']]['child'],array($_GET['cate_id']));
			$condition['sc_id'] = array('in',$child);
		}

		$condition['store_state'] = 1;

		if (!in_array($_GET['order'],array('desc','asc'))){
			unset($_GET['order']);
		}
		if (!in_array($_GET['key'],array('store_sales','store_credit'))){
			unset($_GET['key']);
		}

		$order = 'store_sort asc';

        if (isset($condition['store.store_id'])){
            $condition['store_id'] = $condition['store.store_id'];unset($condition['store.store_id']);
        }

        $model_store = Model('store');
        $store_list = $model_store->where($condition)->order($order)->page(10)->select();
        //获取店铺商品数，推荐商品列表等信息
        $store_list = $model_store->getStoreSearchList($store_list);
        //print_r($store_list);exit();
        //信用度排序
        if($_GET['key'] == 'store_credit') {
            if($_GET['order'] == 'desc') {
                $store_list = sortClass::sortArrayDesc($store_list, 'store_credit_average');
            }else {
                $store_list = sortClass::sortArrayAsc($store_list, 'store_credit_average');
            }
        }else if($_GET['key'] == 'store_sales') {//销量排行
            if($_GET['order'] == 'desc') {
                $store_list = sortClass::sortArrayDesc($store_list, 'num_sales_jq');
            }else {
                $store_list = sortClass::sortArrayAsc($store_list, 'num_sales_jq');
            }
        }
		Tpl::output('store_list',$store_list);
		
		Tpl::output('show_page',$model->showpage(2));
		//当前位置
		if (intval($_GET['cate_id']) > 0){
			$nav_link[1]['link'] = 'index.php?act=shop_search';
			$nav_link[1]['title'] = $lang['site_search_store'];
			$nav =$class_list[$_GET['cate_id']];
			//如果有父级
			if ($nav['sc_parent_id'] > 0){
				$tmp = $class_list[$nav['sc_parent_id']];
				//存入父级
				$nav_link[] = array(
					'title'=>$tmp['sc_name'],
					'link'=>"index.php?act=shop_search&cate_id=".$nav['sc_parent_id']
				);
			}
			//存入当前级
			$nav_link[] = array(
				'title'=>$nav['sc_name']
			);
		}else{
			$nav_link[1]['link'] = 'index.php';
			$nav_link[1]['title'] = $lang['homepage'];
			$nav_link[2]['title'] = $lang['site_search_store'];
		}

		$purl = $this->getParam();
		Tpl::output('nav_link_list',$nav_link);
		Tpl::output('purl', urlShop($purl['act'], $purl['op'], $purl['param']));

		//SEO
		Model('seo')->type('index')->show();
		Tpl::output('html_title',(empty($_GET['keyword']) ? '' : $_GET['keyword'].' - ').C('site_name').$lang['nc_common_search']);
		Tpl::showpage('shop_search');
	}

	/**
	 * 全文搜索
	 *
	 */
	private function full_search($search_txt){
		$conf = C('fullindexer');
		import('libraries.sphinx');
		$cl = new SphinxClient();
		$cl->SetServer($conf['host'], $conf['port']);
		$cl->SetConnectTimeout(1);
		$cl->SetArrayResult(true);
		$cl->SetRankingMode($conf['rankingmode']?$conf['rankingmode']:0);
		$cl->setLimits(0,$conf['querylimit']);

		$matchmode = $conf['matchmode'];
		$cl->setMatchMode($matchmode);
		//可以使用全文搜索进行状态筛选及排序，但需要经常重新生成索引，否则结果不太准，所以暂不使用。使用数据库，速度会慢些
//		$cl->SetFilter('store_state',array(1),false);
//		if ($_GET['key'] == 'store_credit'){
//			$order = $_GET['order'] == 'desc' ? SPH_SORT_ATTR_DESC : SPH_SORT_ATTR_ASC;
//			$cl->SetSortMode($order,'store_sort');
//		}
		$res = $cl->Query($search_txt, $conf['index_shop']);
		if ($res){
			if (is_array($res['matches'])){
				foreach ($res['matches'] as $value) {
					$matchs_id[] = $value['id'];
				}
			}
		}
		if ($search_txt != ''){
			$condition['store.store_id'] = array('in',$matchs_id);
		}
		return $condition;
	}

	/**
	 * 所有一级地区的子集
	 * @param unknown_type $id
	 */
	private function getAreaNextId($id) {
		$area_array	= array (
			1 => '36,37,38,41,42,43,44,45,46,47,48,49,50,51,52,53,54,566',
			2 => '40,55,56,57,58,59,60,61,64,65,66,67,68,69,70,71,72',
			3 => '73,1126,1127,1128,1129,1130,1131,1132,1133,1134,1135,1136,1137,1138,1139,1140,1141,1142,1143,1144,1145,1146,1147,1148,74,1149,1150,1151,1152,1153,1154,1155,1156,1157,1158,1159,1160,1161,1162,75,1163,1164,1165,1166,1167,1168,1169,76,1170,1171,1172,1173,1174,1175,1176,1177,1178,1179,1180,1181,1182,1183,1184,1185,1186,1187,1188,77,1189,1190,1191,1192,1193,1194,1195,1196,1197,1198,1199,1200,1201,1202,1203,1204,1205,1206,1207,78,1208,1209,1210,1211,1212,1213,1214,1215,1216,1217,1218,1219,1220,1221,1222,1223,1224,1225,1226,1227,1228,1229,1230,1231,1232,79,1233,1234,1235,1236,1237,1238,1239,1240,1241,1242,1243,1244,1245,1246,1247,1248,1249,80,1250,1251,1252,1253,1254,1255,1256,1257,1258,1259,1260,81,1261,1262,1263,1264,1265,1266,1267,1268,1269,1270,1271,82,1272,1273,1274,1275,1276,1277,1278,1279,1280,1281,83,1282,1283,1284,1285,1286,1287,1288,1289,1290,1291,1292,1293,1294,1295,1296,1297',
			4 => '84,1298,1299,1300,1301,1302,1303,1304,1305,1306,1307,85,1308,1309,1310,1311,1312,1313,1314,1315,1316,1317,1318,86,1319,1320,1321,1322,1323,87,1324,1325,1326,1327,1328,1329,1330,1331,1332,1333,1334,1335,1336,88,1337,1338,1339,1340,1341,1342,89,1343,1344,1345,1346,1347,1348,90,1349,1350,1351,1352,1353,1354,1355,1356,1357,1358,1359,91,1360,1361,1362,1363,1364,1365,1366,1367,1368,1369,1370,1371,1372,92,1373,1374,1375,1376,1377,1378,1379,1380,1381,1382,1383,1384,1385,1386,93,1387,1388,1389,1390,1391,1392,1393,1394,1395,1396,1397,1398,1399,1400,1401,1402,1403,94,1404,1405,1406,1407,1408,1409,1410,1411,1412,1413,1414,1415,1416',
			5 => '95,1417,1418,1419,1420,1421,1422,1423,1424,1425,96,1426,1427,1428,1429,1430,1431,1432,1433,1434,97,1435,1436,1437,98,1438,1439,1440,1441,1442,1443,1444,1445,1446,1447,1448,1449,99,1450,1451,1452,1453,1454,1455,1456,1457,100,1458,1459,1460,1461,1462,1463,1464,1465,1466,101,1467,1468,1469,1470,1471,1472,1473,1474,1475,1476,1477,1478,1479,102,1480,1481,1482,1483,1484,1485,1486,103,1487,1488,1489,1490,1491,1492,1493,1494,1495,1496,1497,104,1498,1499,1500,1501,1502,1503,105,1504,1505,1506,1507,1508,1509,1510,1511,1512,1513,1514,1515,106,1516,1517,1518',
			6 => '107,1519,1520,1521,1522,1523,1524,1525,1526,1527,1528,1529,1530,1531,108,1532,1533,1534,1535,1536,1537,1538,1539,1540,1541,109,1542,1543,1544,1545,1546,1547,1548,110,1549,1550,1551,1552,1553,1554,1555,111,1556,1557,1558,1559,1560,1561,112,1562,1563,1564,1565,1566,1567,113,1568,1569,1570,1571,1572,1573,1574,114,1575,1576,1577,1578,1579,1580,115,1581,1582,1583,1584,1585,1586,1587,116,1588,1589,1590,1591,1592,1593,1594,117,1595,1596,1597,1598,118,1599,1600,1601,1602,1603,1604,1605,119,1606,1607,1608,1609,1610,1611,1612,120,1613,1614,1615,1616,1617,1618',
			7 => '121,1619,1620,1621,1622,1623,1624,1625,1626,1627,1628,122,1629,1630,1631,1632,1633,1634,1635,1636,1637,123,1638,1639,1640,1641,1642,1643,124,1644,1645,1646,1647,125,1648,1649,1650,1651,1652,1653,1654,126,1655,1656,1657,1658,1659,1660,127,1661,1662,1663,1664,1665,128,1666,1667,1668,1669,1670,129,1671,1672,1673,1674,1675,1676,1677,1678',
			8 => '130,1679,1680,1681,1682,1683,1684,1685,1686,1687,1688,1689,1690,1691,1692,1693,1694,1695,1696,131,1697,1698,1699,1700,1701,1702,1703,1704,1705,1706,1707,1708,1709,1710,1711,1712,132,1713,1714,1715,1716,1717,1718,1719,1720,1721,133,1722,1723,1724,1725,1726,1727,1728,1729,134,1730,1731,1732,1733,1734,1735,1736,1737,135,1738,1739,1740,1741,1742,1743,1744,1745,1746,136,1747,1748,1749,1750,1751,1752,1753,1754,1755,1756,1757,1758,1759,1760,1761,1762,1763,137,1764,1765,1766,1767,1768,1769,1770,1771,1772,1773,138,1774,1775,1776,1777,139,1778,1779,1780,1781,1782,1783,1784,1785,1786,1787,140,1788,1789,1790,1791,1792,1793,1794,141,1795,1796,1797,1798,1799,1800,1801,1802,1803,1804,142,1805,1806,1807,1808,1809,1810,1811',
			9 => '39,143,144,145,146,147,148,149,150,151,152,153,154,155,156,157,158,159,160,161',
			10 => '162,2027,2028,2029,2030,2031,2032,2033,2034,2035,2036,2037,2038,2039,163,2040,2041,2042,2043,2044,2045,2046,2047,164,2048,2049,2050,2051,2052,2053,2054,2055,2056,2057,2058,165,2059,2060,2061,2062,2063,2064,2065,166,2066,2067,2068,2069,2070,2071,2072,2073,2074,2075,2076,2077,167,2078,2079,2080,2081,2082,2083,2084,2085,168,2086,2087,2088,2089,2090,2091,2092,169,2093,2094,2095,2096,2097,2098,2099,2100,170,2101,2102,2103,2104,2105,2106,2107,2108,2109,171,2110,2111,2112,2113,2114,2115,2116,172,2117,2118,2119,2120,2121,2122,173,2123,2124,2125,2126,2127,2128,174,2129,2130,2131,2132,2133',
			11 => '175,2134,2135,2136,2137,2138,2139,2140,2141,2142,2143,2144,2145,2146,176,2147,2148,2149,2150,2151,2152,2153,2154,2155,2156,2157,177,2158,2159,2160,2161,2162,2163,2164,2165,2166,2167,2168,178,2169,2170,2171,2172,2173,2174,2175,179,2176,2177,2178,2179,2180,180,2181,2182,2183,2184,2185,2186,181,2187,2188,2189,2190,182,2191,2192,2193,2194,2195,2196,183,2197,2198,2199,2200,2201,2202,2203,2204,2205,184,2206,2207,2208,2209,2210,2211,2212,2213,2214,185,2215,2216,2217,2218,2219,2220,2221,2222,2223',
			12 => '186,2224,2225,2226,2227,2228,2229,2230,187,2231,2232,2233,2234,2235,2236,2237,188,2238,2239,2240,2241,2242,2243,2244,189,2245,2246,2247,2248,2249,2250,190,2251,2252,2253,2254,191,2255,2256,2257,2258,192,2259,2260,2261,2262,193,2263,2264,2265,2266,2267,2268,2269,2270,2271,2272,2273,194,2274,2275,2276,2277,2278,2279,2280,195,2281,2282,2283,2284,2285,2286,2287,2288,196,2289,2290,2291,2292,2293,2294,2295,2296,197,2297,2298,2299,2300,2301,198,2302,2303,2304,2305,2306,199,2307,2308,2309,2310,2311,2312,2313,200,2314,2315,2316,2317,201,2318,2319,2320,2321,202,2322,2323,2324,2325,2326,2327,2328',
			13 => '203,2329,2330,2331,2332,2333,2334,2335,2336,2337,2338,2339,2340,2341,204,2342,2343,2344,2345,2346,2347,205,2348,2349,2350,2351,2352,206,2353,2354,2355,2356,2357,2358,2359,2360,2361,2362,2363,2364,207,2365,2366,2367,2368,2369,2370,2371,2372,2373,2374,2375,2376,208,2377,2378,2379,2380,2381,2382,2383,2384,2385,2386,2387,209,2388,2389,2390,2391,2392,2393,2394,2395,2396,2397,210,2398,2399,2400,2401,2402,2403,2404,211,2405,2406,2407,2408,2409,2410,2411,2412,2413',
			14 => '212,2414,2415,2416,2417,2418,2419,2420,2421,2422,213,2423,2424,2425,2426,214,2427,2428,2429,2430,2431,215,2432,2433,2434,2435,2436,2437,2438,2439,2440,2441,2442,2443,216,2444,2445,217,2446,2447,2448,218,2449,2450,2451,2452,2453,2454,2455,2456,2457,2458,2459,2460,2461,2462,2463,2464,2465,2466,219,2467,2468,2469,2470,2471,2472,2473,2474,2475,2476,2477,2478,2479,220,2480,2481,2482,2483,2484,2485,2486,2487,2488,2489,221,2490,2491,2492,2493,2494,2495,2496,2497,2498,2499,2500,222,2501,2502,2503,2504,2505,2506,2507,2508,2509,2510,2511,2512',
			15 => '223,2513,2514,2515,2516,2517,2518,2519,2520,2521,2522,224,2523,2524,2525,2526,2527,2528,2529,2530,2531,2532,2533,2534,225,2535,2536,2537,2538,2539,2540,2541,2542,226,2543,2544,2545,2546,2547,2548,227,2549,2550,2551,2552,2553,228,2554,2555,2556,2557,2558,2559,2560,2561,2562,2563,2564,2565,229,2566,2567,2568,2569,2570,2571,2572,2573,2574,2575,2576,2577,230,2578,2579,2580,2581,2582,2583,2584,2585,2586,2587,2588,2589,231,2590,2591,2592,2593,2594,2595,232,2596,2597,2598,2599,233,2600,2601,2602,2603,234,2604,2605,235,2606,2607,2608,2609,2610,2611,2612,2613,2614,2615,2616,2617,236,2618,2619,2620,2621,2622,2623,2624,2625,2626,2627,2628,237,2629,2630,2631,2632,2633,2634,2635,2636,238,2637,2638,2639,2640,2641,2642,2643,239,2644,2645,2646,2647,2648,2649,2650,2651,2652',
			16 => '240,2653,2654,2655,2656,2657,2658,2659,2660,2661,2662,2663,2664,241,2665,2666,2667,2668,2669,2670,2671,2672,2673,2674,242,2675,2676,2677,2678,2679,2680,2681,2682,2683,2684,2685,2686,2687,2688,2689,243,2690,2691,2692,2693,2694,2695,2696,2697,2698,2699,244,2700,2701,2702,2703,2704,2705,2706,2707,2708,245,2709,2710,2711,2712,2713,246,2714,2715,2716,2717,2718,2719,2720,2721,2722,2723,2724,2725,247,2726,2727,2728,2729,2730,2731,2732,2733,2734,2735,248,2736,2737,2738,2739,2740,2741,249,2742,2743,2744,2745,2746,2747,250,2748,2749,2750,2751,2752,251,2753,2754,2755,2756,2757,2758,252,2759,2760,2761,2762,2763,2764,2765,2766,2767,2768,2769,2770,2771,253,2772,2773,2774,2775,2776,2777,2778,2779,2780,254,2781,2782,2783,2784,2785,2786,2787,2788,2789,2790,255,2791,2792,2793,2794,2795,2796,2797,2798,2799,2800,256,2801,2802,2803,2804,2805,2806,2807,2808,2809,2810,257,2811',
			17 => '258,2812,2813,2814,2815,2816,2817,2818,2819,2820,2821,2822,2823,2824,259,2825,2826,2827,2828,2829,2830,260,2831,2832,2833,2834,2835,2836,2837,2838,261,2839,2840,2841,2842,2843,2844,2845,2846,2847,2848,2849,2850,2851,262,2852,2853,2854,2855,2856,2857,2858,2859,2860,263,2861,2862,2863,264,2864,2865,2866,2867,2868,265,2869,2870,2871,2872,2873,2874,2875,266,2876,2877,2878,2879,2880,2881,2882,2883,267,2884,2885,2886,2887,2888,2889,2890,2891,2892,2893,268,2894,2895,2896,2897,2898,2899,269,2900,2901,270,2902,2903,2904,2905,2906,2907,2908,2909,271,2910,272,2911,273,2912,274,2913',
			18 => '275,2914,2915,2916,2917,2918,2919,2920,2921,2922,276,2923,2924,2925,2926,2927,2928,2929,2930,2931,277,2932,2933,2934,2935,2936,278,2937,2938,2939,2940,2941,2942,2943,2944,2945,2946,2947,2948,279,2949,2950,2951,2952,2953,2954,2955,2956,2957,2958,2959,2960,280,2961,2962,2963,2964,2965,2966,2967,2968,2969,281,2970,2971,2972,2973,2974,2975,2976,2977,2978,282,2979,2980,2981,2982,283,2983,2984,2985,2986,2987,2988,284,2989,2990,2991,2992,2993,2994,2995,2996,2997,2998,2999,285,3000,3001,3002,3003,3004,3005,3006,3007,3008,3009,3010,286,3011,3012,3013,3014,3015,3016,3017,3018,3019,3020,3021,3022,287,3023,3024,3025,3026,3027,288,3028,3029,3030,3031,3032,3033,3034,3035',
			19 => '289,3036,3037,3038,3039,3040,3041,3042,3043,3044,3045,3046,3047,290,3048,3049,3050,3051,3052,3053,3054,3055,3056,3057,291,3058,3059,3060,3061,3062,3063,292,3064,3065,3066,293,3067,3068,3069,3070,3071,3072,3073,294,3074,3075,3076,3077,3078,295,3079,3080,3081,3082,3083,3084,3085,296,3086,3087,3088,3089,3090,3091,3092,3093,3094,297,3095,3096,3097,3098,3099,3100,298,3101,3102,3103,3104,3105,3106,3107,3108,299,3109,3110,3111,3112,3113,300,3114,3115,3116,3117,3118,3119,3120,3121,301,3122,3123,3124,3125,302,3126,3127,3128,3129,3130,3131,303,3132,3133,3134,3135,304,3136,3137,3138,3139,3140,3141,3142,3143,305,3144,306,3145,307,3146,3147,3148,308,3149,3150,3151,3152,3153,309,3154,3155,3156,3157,3158',
			20 => '310,3159,3160,3161,3162,3163,3164,3165,3166,3167,3168,3169,3170,311,3171,3172,3173,3174,3175,3176,3177,3178,3179,3180,312,3181,3182,3183,3184,3185,3186,3187,3188,3189,3190,3191,3192,3193,3194,3195,3196,3197,313,3198,3199,3200,3201,3202,3203,3204,314,3205,3206,3207,3208,315,3209,3210,3211,3212,316,3213,3214,3215,3216,317,3217,3218,3219,3220,3221,318,3222,3223,3224,3225,3226,3227,319,3228,3229,3230,3231,3232,3233,3234,3235,3236,3237,3238,3239,320,3240,3241,3242,3243,321,3244,3245,3246,3247,3248,3249,3250,3251,3252,3253,3254,322,3255,3256,3257,3258,3259,3260,323,3261,3262,3263,3264,3265,3266,3267',
			21 => '324,3268,3269,3270,3271,325,3272,326,3273,327,3274,328,3275,329,3276,330,3277,331,3278,332,3279,333,3280,334,3281,335,3282,336,3283,337,3284,338,3285,339,3286,340,3287,341,3288,342,343,344',
			22 => '62,345,346,347,348,349,350,351,352,353,354,355,356,357,358,359,360,361,362,363,364,365,366,367,368,369,370,371,372,373,374,375,376,377,378,379,380,381,382,383,384',
			23 => '385,4209,4210,4211,4212,4213,4214,4215,4216,4217,4218,4219,4220,4221,4222,4223,4224,4225,4226,4227,386,4228,4229,4230,4231,4232,4233,387,4234,4235,4236,4237,4238,388,4239,4240,4241,4242,4243,4244,4245,389,4246,4247,4248,4249,4250,4251,390,4252,4253,4254,4255,4256,4257,4258,4259,4260,391,4261,4262,4263,4264,4265,4266,4267,392,4268,4269,4270,4271,4272,393,4273,4274,4275,4276,4277,394,4278,4279,4280,4281,4282,4283,4284,4285,4286,4287,4288,395,4289,4290,4291,4292,4293,4294,4295,4296,4297,4298,396,4299,4300,4301,4302,4303,4304,397,4305,4306,4307,4308,4309,4310,4311,4312,4313,4314,398,4315,4316,4317,4318,4319,399,4320,4321,4322,4323,4324,4325,4326,400,4327,4328,4329,4330,4331,4332,4333,4334,401,4335,4336,4337,4338,402,4339,4340,4341,4342,403,4343,4344,4345,4346,4347,4348,4349,4350,4351,4352,4353,4354,4355,404,4356,4357,4358,4359,4360,4361,4362,4363,4364,4365,4366,4367,4368,4369,4370,4371,4372,405,4373,4374,4375,4376,4377,4378,4379,4380,4381,4382,4383,4384,4385,4386,4387,4388,4389',
			24 => '406,4390,4391,4392,4393,4394,4395,4396,4397,4398,4399,407,4400,4401,4402,4403,408,4404,4405,4406,4407,4408,4409,4410,4411,4412,4413,4414,4415,4416,4417,409,4418,4419,4420,4421,4422,4423,410,4424,4425,4426,4427,4428,4429,4430,4431,4432,4433,411,4434,4435,4436,4437,4438,4439,4440,4441,412,4442,4443,4444,4445,4446,4447,4448,4449,413,4450,4451,4452,4453,4454,4455,4456,4457,4458,4459,4460,4461,4462,4463,4464,4465,414,4466,4467,4468,4469,4470,4471,4472,4473,4474,4475,4476,4477',
			25 => '415,4478,4479,4480,4481,4482,4483,4484,4485,4486,4487,4488,4489,4490,4491,416,4492,4493,4494,4495,4496,4497,4498,4499,4500,417,4501,4502,4503,4504,4505,4506,4507,4508,4509,418,4510,4511,4512,4513,4514,419,4515,4516,4517,4518,4519,4520,4521,4522,4523,4524,4525,420,4526,4527,4528,4529,4530,421,422,4531,4532,4533,4534,4535,4536,4537,4538,423,4539,4540,4541,4542,4543,4544,4545,4546,4547,4548,424,4549,4550,4551,4552,4553,4554,4555,4556,4557,4558,4559,4560,4561,425,4562,4563,4564,4565,4566,4567,4568,4569,426,4570,4571,4572,427,4573,4574,4575,4576,4577,4578,4579,4580,4581,4582,4583,4584,428,4585,4586,4587,4588,4589,429,430,4590,4591,4592',
			26 => '431,4593,4594,4595,4596,4597,4598,4599,4600,432,4601,4602,4603,4604,4605,4606,4607,4608,4609,4610,4611,433,4612,4613,4614,4615,4616,4617,4618,4619,4620,4621,4622,4623,434,4624,4625,4626,4627,4628,4629,4630,4631,4632,4633,4634,4635,4636,4637,4638,4639,4640,4641,435,4642,4643,4644,4645,4646,4647,4648,4649,4650,4651,436,4652,4653,4654,4655,4656,4657,4658,437,4659,4660,4661,4662,4663,4664,4665',
			27 => '438,4666,4667,4668,4669,4670,4671,4672,4673,4674,4675,4676,4677,4678,439,4679,4680,4681,4682,440,4683,4684,4685,4686,4687,4688,4689,4690,4691,4692,4693,4694,441,4695,4696,4697,4698,4699,4700,4701,4702,4703,4704,4705,4706,4707,4708,442,4709,4710,4711,4712,4713,4714,4715,4716,4717,4718,4719,443,4720,4721,4722,4723,4724,4725,4726,4727,4728,4729,4730,4731,4732,444,4733,4734,4735,4736,4737,4738,4739,4740,4741,4742,4743,445,4744,4745,4746,4747,4748,4749,4750,4751,4752,4753,4754,4755,446,4756,4757,4758,4759,4760,4761,4762,4763,4764,4765,447,4766,4767,4768,4769,4770,4771,4772',
			28 => '448,4773,4774,4775,4776,4777,4778,4779,4780,449,4781,450,4782,4783,451,4784,4785,4786,4787,4788,452,4789,4790,4791,4792,4793,4794,4795,453,4796,4797,4798,4799,454,4800,4801,4802,4803,4804,4805,455,4806,4807,4808,4809,4810,4811,4812,456,4813,4814,4815,4816,4817,4818,4819,457,4820,4821,4822,4823,4824,4825,4826,4827,458,4828,4829,4830,4831,4832,4833,4834,459,4835,4836,4837,4838,4839,4840,4841,4842,4843,460,4844,4845,4846,4847,4848,4849,4850,4851,461,4852,4853,4854,4855,4856,4857,4858,4859',
			29 => '462,4860,4861,4862,4863,4864,4865,4866,463,4867,4868,4869,4870,4871,4872,464,4873,4874,4875,4876,465,4877,4878,4879,4880,466,4881,4882,4883,4884,4885,467,4886,4887,4888,4889,4890,4891,468,4892,4893,4894,4895,4896,4897,469,4898,4899,4900,4901,4902,4903,4904,4905',
			30 => '470,4906,4907,4908,4909,4910,4911,471,4912,4913,4914,472,4915,4916,4917,4918,473,4919,4920,4921,4922,4923,474,4924,4925,4926',
			31 => '475,4927,4928,4929,4930,4931,4932,4933,4934,476,4935,4936,4937,4938,477,4939,4940,4941,478,4942,4943,4944,479,4945,4946,4947,4948,4949,4950,4951,4952,480,4953,4954,4955,481,4956,4957,4958,4959,4960,4961,4962,4963,482,4964,4965,4966,4967,4968,4969,4970,4971,4972,483,4973,4974,4975,4976,484,4977,4978,4979,4980,4981,4982,4983,4984,4985,4986,4987,4988,485,4989,4990,4991,4992,4993,4994,4995,4996,486,4997,4998,4999,5000,5001,5002,5003,5004,5005,5006,487,5007,5008,5009,5010,5011,5012,5013,488,5014,5015,5016,5017,5018,5019,5020,489,5021,490,5022,491,5023,492,5024',
			32 => '493,494,495,496,497,498,499,500,501,502,503,504,505,506,507,508,509,510,511,512,513,514,515',
			33 => '516,517,518,519,520,521,522,523,524,525,526,527,528,529,530,531,532,533',
			34 => '534',
			35 => '45055,535,536,537,538,539,540,541,542,543,544,545,546,547,548,549,550,551,552,553,554,555,556,557,558,559,560,561,562,563,564,565',
		);
		return $area_array[$id];
	}
	
	function getParam() {
	    $param = $_GET;
	    $purl = array();
	    $purl['act'] = $param['act'];
	    unset($param['act']);
	    $purl['op'] = $param['op'];
	    unset($param['op']); unset($param['curpage']);
	    $purl['param'] = $param;
	    return $purl;
	}
}
class sortClass{
    //升序
    public static function sortArrayAsc($preData,$sortType='store_sort'){
        $sortData = array();
        foreach ($preData as $key_i => $value_i){
            $price_i = $value_i[$sortType];
            $min_key = '';
            $sort_total = count($sortData);
            foreach ($sortData as $key_j => $value_j){
                if($price_i<$value_j[$sortType]){
                    $min_key = $key_j+1;
                    break;
                }
            }
            if(empty($min_key)){
                array_push($sortData, $value_i);
            }else {
                $sortData1 = array_slice($sortData, 0,$min_key-1);
                array_push($sortData1, $value_i);
                if(($min_key-1)<$sort_total){
                    $sortData2 = array_slice($sortData, $min_key-1);
                    foreach ($sortData2 as $value){
                        array_push($sortData1, $value);
                    }
                }
                $sortData = $sortData1;
            }
        }
        return $sortData;
    }
    //降序
    public static function sortArrayDesc($preData,$sortType='store_sort'){
        $sortData = array();
        foreach ($preData as $key_i => $value_i){
            $price_i = $value_i[$sortType];
            $min_key = '';
            $sort_total = count($sortData);
            foreach ($sortData as $key_j => $value_j){
                if($price_i>$value_j[$sortType]){
                    $min_key = $key_j+1;
                    break;
                }
            }
            if(empty($min_key)){
                array_push($sortData, $value_i);
            }else {
                $sortData1 = array_slice($sortData, 0,$min_key-1);
                array_push($sortData1, $value_i);
                if(($min_key-1)<$sort_total){
                    $sortData2 = array_slice($sortData, $min_key-1);
                    foreach ($sortData2 as $value){
                        array_push($sortData1, $value);
                    }
                }
                $sortData = $sortData1;
            }
        }
        return $sortData;
    }
}
