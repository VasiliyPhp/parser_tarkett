<?php
	
	
	error_reporting(E_ALL);
	ini_set('display_errors',1);
	set_time_limit(0);
	require 'vendor/autoload.php';
	
	const SITE = 'http://www.tarkett.ru';
	$prop2 = require 'prop2.php';
	
	// p($prop2);
	$properties = array_filter(array_map('trim', file('properties.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)));
	$props = ['Категория','Вторая категория','Третья категория','Четвертая категория','Изображение','Описание','Транслит', 'Транслит категории'];
	$properties = array_merge($props, $properties);
	
	
	try{
		
		$tmp = curl(SITE . '/catalog/');
		$res = array_fill_keys($properties,null);
		// p($tmp);
		
		$doc = phpQuery::newDocument($tmp)->find('.picmenuitem');
		// $file = 
		foreach($doc as $main_cats){
			$cat1_link = SITE . pq('.picture a', $main_cats)->attr('href');
			$res['Категория'] = $cat1_name = pq('.title.black', $main_cats)->text();
			// if($res['Категория'] == 'Art Vinyl'){
				// continue;
			// }
			// p([$cat1_link,$cat1_name]);
			parse_second_cat($cat1_link, $cat1_name, $res);
			
			
			
		}
		
		
		
		
		}catch(Exception $e ){
		
		echo '<b color="#faa"> ' . $e->getMessage() . '</b><br/>';
		echo '<b color="#faa"> ' . $e->getLine() . '</b>';
	}
	
	
	// function  parse_second_cat($link, $name1, &$ar){
	function  parse_second_cat($link, $name1, $ar){
		p($link,0);
		$doc1 = phpquery::newDocument(curl($link));
		$docs = pq('.CTCOneBrend');
		foreach($docs as $doc){
			$ar['Вторая категория'] = $name2 = pq('.CTCBrendPic',$doc)->attr('alt');
			if($ar['Категория'] == 'АКСЕССУАРЫ' && $ar['Вторая категория'] != 'Сварочные шнуры'){
				p(__LINE__,0);
				continue;
			}
			if(stripos($ar['Вторая категория'], 'POLYSTYL') !== false){
				p($ar['Вторая категория'] . ' polystil' ,0);p(__LINE__,0);
				continue;
			}
			
			foreach(pq('.prodtypelink',$doc) as $links){
				$ar['Третья категория'] = $name3 = pq($links)->text();
				
				if(stripos($ar['Третья категория'], 'POLYSTYL') !== false){
					p($ar['Третья категория'] . ' polystil' ,0);p(__LINE__,0);
					continue;
				}
				
				$href = SITE . pq($links)->attr('href');
				// переходим на общую карточку товара
				parse_cart($href, $ar);
				$raw_desc = '';
				// p($ar);
				
			}
		}
		$doc1->unloadDocument();
		// echo $doc;
		// p('');
		
	}
	
	// function parse_cart($href, &$ar){
	function parse_cart($href, $ar){
		
		p($href,0);
		$doc = phpQuery::newDocument(curl($href));
		$desc = pq('.discblock p');
		if(!count($desc)){
			$cats = pq('.CGPoneProdTitle');	
			foreach($cats as $cat){
				$ar['Четвертая категория'] = pq('h2 a',$cat)->text();
				
				if(stripos($ar['Четвертая категория'], 'POLYSTYL') !== false){
					p($ar['Четвертая категория'] . ' polystil' ,0);p(__LINE__,0);
					continue;
				}
				
				$link = SITE . pq('h2 a',$cat)->attr('href');
				parse_cart($link, $ar);
			}
			$doc->unloadDocument();
			return ;
		}
		foreach($desc as $d){
			$p = pq('a,img', $d);
			if(count($p)){
				continue;
			}
			$ar['Описание'] .= '<p>'.pq($d)->text().'</p>';
		}
		
		$links = pq('.designblock .descela');
		
		foreach($links as $link){
			$href = SITE . pq($link)->attr('href');
			p($href,0);
			$doc2 = phpQuery::newDocument(curl($href));
			$trs = pq('.PCPC tr');
			$chars = [];
			foreach($trs as $tr){
				$char = trim(pq('td:eq(0)', $tr)->text());
				$val = (trim(pq('td:eq(1)', $tr)->html()));
				
				$chars[] = $char;
				
				if(array_key_exists($char, $ar)){
					$ar[$char] = $val;
				}
				else{
					p($char,0);
				}
			}
			
			// foreach(array_keys($ar) as $_key){
			// if(!in_array($_key, $chars)){
			// $ar[$_key] = '';
			// }
			// }
			$ar['Название'] =  preg_replace('~\s+~',' ',trim(pq('.PCtoprightBlock h1')->text()));
			$ar['Транслит'] = translit($ar['Название']);
			$ar['Транслит категории'] = translit($ar['Категория']);
			$image = save_img(SITE . pq('.PCprodPic a')->attr('href'), $ar['Транслит'], 
			$ar['Категория'], $ar['Вторая категория'], $ar['Третья категория'] );
			$ar['Изображение'] = $image;
			
			save_csv($ar);
			
			// p($ar);
			
			
			
			$doc2->unloadDocument();
			
		}
		$doc->unloadDocument();
		// if()
		// p('');
		
	}
	
	function save_csv($ar){
		global $properties, $prop2;
		$path = 'csv' ;
		file_exists($path) or mkdir($path,null,1);
		$path .= '/';
		$csv = $path . translit($ar['Категория']) . '.csv';
		$exists = file_exists($csv);
		$f = fopen($csv, 'a');
		if(!$exists){
			fputcsv($f, array_keys($prop2), ';','"');
			// fputcsv($f, ($prop2), ';','"');
		}
		$result = [];
		foreach($prop2 as $value){
			// echo $value;
			$result[] = isset($ar[$value])? $ar[$value] : '';
		}
		fputcsv($f, $result, ';','"');
	}
	
	
	function save_img($url, $name, $cat, $subcut, $subsubcut){
		$path = 'images/'.translit($cat ) . '/' . translit($subcut ) . '/' . translit($subsubcut );
		file_exists($path) or mkdir($path,null,1);
		$name = translit($name);
		file_put_contents($path . '/' . $name . '.jpg', file_get_contents($url));
		return $path . '/' . $name . '.jpg';
		
	}
	function curl ($url){
		
		
		$ch = curl_init($url);
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		
		$response = curl_exec($ch);
		
		if($curl = curl_error($ch)){
			throw new Exception($curl);
		}
		return $response;
	}		
	
	function p($M,$die = 1){
		
		printf('<pre>%s</pre>',print_r($M,1));
		$die && die();
		ob_flush();
		flush();
	}								
	
	function translit($str){
		$str = preg_replace('~\s+~',' ', mb_strtolower($str));
		$tr = array(
		"А"=>"a", "Б"=>"b", "В"=>"v", "Г"=>"g", "Д"=>"d",
		"Е"=>"e", "Ё"=>"yo", "Ж"=>"zh", "З"=>"z", "И"=>"i", 
		"Й"=>"j", "К"=>"k", "Л"=>"l", "М"=>"m", "Н"=>"n", 
		"О"=>"o", "П"=>"p", "Р"=>"r", "С"=>"s", "Т"=>"t", 
		"У"=>"u", "Ф"=>"f", "Х"=>"kh", "Ц"=>"ts", "Ч"=>"ch", 
		"Ш"=>"sh", "Щ"=>"sch", "Ъ"=>"", "Ы"=>"y", "Ь"=>"", 
		"Э"=>"e", "Ю"=>"yu", "Я"=>"ya", "а"=>"a", "б"=>"b", 
		"в"=>"v", "г"=>"g", "д"=>"d", "е"=>"e", "ё"=>"yo", 
		"ж"=>"zh", "з"=>"z", "и"=>"i", "й"=>"j", "к"=>"k", 
		"л"=>"l", "м"=>"m", "н"=>"n", "о"=>"o", "п"=>"p", 
		"р"=>"r", "с"=>"s", "т"=>"t", "у"=>"u", "ф"=>"f", 
		"х"=>"kh", "ц"=>"ts", "ч"=>"ch", "ш"=>"sh", "щ"=>"sch", 
		"ъ"=>"", "ы"=>"y", "ь"=>"", "э"=>"e", "ю"=>"yu", 
		"я"=>"ya", " "=>"-", "."=>"", ","=>"", "/"=>"-",  
		":"=>"", ";"=>"","—"=>"", "–"=>"-"
		);
		return strtr($str,$tr);
	}																																				