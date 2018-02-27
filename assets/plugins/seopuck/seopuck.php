<?php
	defined('IN_MANAGER_MODE') or die();
	$error_page = 'exit.php'; //Уникальное название страницы, куда первоначально будем делать редирект при внешнем ресурсе 
	
	$e =&$modx->Event;
	
	if (!function_exists('compress_html')) { 
		function compress_html($compress)
		{			
			$compress = str_replace("\n", '', $compress);
			$compress = str_replace("\s", '', $compress);
			$compress = str_replace("\r", '', $compress);
			$compress = str_replace("\t", '', $compress);
			$compress = preg_replace('/(?:(?<=\>)|(?<=\/\>))\s+(?=\<\/?)/', '', $compress);
			$compress = preg_replace('/[\t\r]\s+/', ' ', $compress);
			$compress = preg_replace('/<!(--)([^\[|\|])^(<!-->.*<!--.*-->)/', '', $compress);
			$compress = preg_replace('/\/\*.*?\*\//', '', $compress);
			return preg_replace("#\\s+#ism"," ",$compress);
		}
	}
	
	if ($e->name=='OnWebPagePrerender')
	{
		
		
		$content = $modx->Event->params['documentOutput'];
		
		require_once MODX_BASE_PATH.'assets/lib/simple_html_dom.php';
		$html = new simple_html_dom();
		$html->load($content, false, null, -1, -1, true, true, DEFAULT_TARGET_CHARSET, false); 
		
		$title = $html->find('title',0);        
		$metaTitle = str_replace("'","'",$title->plaintext); // Чтобы не искать потом - заголовок страницы
		$imagealt = $metaTitle.'. Картинка '; //в конце подставляется порядковый номер начиная с 1
		$atitle = $metaTitle.' Ссылка '; //в конце подставляется порядковый номер начиная с 1
		
		//Проставляем тэг title для ссылок
		$links = $html->find("a"); 
		$ln = 1;        
		foreach ($links as $key => $link)
		{
			
			if (!$link->title)
			{
				if ($link->plaintext) 
				{
					
					$t = str_replace('"',"'",trim(mb_substr($link->plaintext,0,50)));
					if (strlen($t)<10)
					{
						$link->title = $atitle.' '.$ln;
						$ln++;
					}
					else $link->title = $t;
				}
				else 
				{
					$link->title = $atitle.' '.$ln;
					$ln++;
				}
			}
		}
		
		//Убираем циклические ссылки
		$url = substr($_SERVER['REQUEST_URI'], 1);
		
		if ($url)
		{
			$cu =  $html->find("a[href='".$url."']"); 
			foreach($cu as $u) $u->href = null;
		}
		
		//Закрываем внешние ссылки
		$outlink = $html->find("a[href^=http]");
		foreach($outlink as $ou) if (strpos($ou->href, MODX_SITE_URL)===false) $ou->href = MODX_SITE_URL.''.$error_page.'?url='.$ou->href;
		
		
		
		//Проставляем тэг alt для картинок      
		$imgs = $html->find("img"); 
		$ln = 1;        
		foreach ($imgs as $key => $img)
		{
			
			if (!$img->alt) 
			{
				$img->alt = $imagealt.' '.$ln;
				$ln++;
			}
			
		}
		
		//Генерим ключевики по тэгам
		
		$mk = $html->find('meta[name=keywords]',0);
		if (count($mk))         
		{
			$min = isset($min) ? $min : 4; //минимальное количество символов в слове при выборке
			$limit = isset($limit) ? $limit : 20; // сколько слов выводить
			
			$keywords = array();
			$keyws = $html->find("h1,h2,h3,h4,h5,h6,b,i,strong");
			foreach($keyws as $keyw) $keywords[]=$keyw->plaintext;
			
			$str = implode(',',$keywords);          
			$str = strip_tags($str);
			$str2 = preg_replace('/[^a-zA-Zа-яА-Я0-9\s]/ui', ' ',$str ); 
			$str2 = str_replace('  ',' ',$str2);
			$str2 = str_replace(PHP_EOL,' ',$str2);
			$arr = explode(' ',$str2);
			foreach($arr as $key=>$val) if ($val) $arr[$key] = mb_strtolower(trim($val),'utf-8');
			
			//Выбираем наиболее часто встречающиеся
			$array_words = array_count_values($arr);
			arsort($array_words);
			$out = array();
			foreach($array_words as $word => $val)
			{
				if (mb_strlen($word,'utf-8')>=$min) $out[]=$word;
				if (count($out)==$limit) break;
			}
			$mk->content = mb_substr($mk->content.','.implode(',',$out), 0, 200);
		}       
		
		// Хак для того, чтобы не сжимать тэг <pre>     
		$pre = $html->find("pre"); 
		if (count($pre)) 
		{
			$arr_pre = array();
			foreach ($pre as $p) $arr_pre[] = $p->outertext;
		}
		
		
		$content = $html->save();
		$html->clear(); 
		
		$content = compress_html($content); //Вытягиваем в 1-у строку
		
		// Хак для того, чтобы не сжимать тэг <pre>
		if (count($pre)) 
		{       
			preg_match_all('~<pre(.*?)pre>~s', $content, $matches);
			foreach ($matches[0] as $key => $pre) $content = str_replace($pre,$arr_pre[$key],$content);
		}
		
		
		
		
		
		
		$modx->Event->output($content);
	}
	
	if ($e->name=='OnPageNotFound')
	{
		$q = $modx->db->escape($_REQUEST['q']);
		if (isset($_GET['url']) && (!empty($_GET['url'])) && ($q==$error_page))
		{
			$url = $_GET['url'];
			if (!preg_match('#(https?|ftp)://\S+[^\s.,>)\];\'\"!?]#i',$url)) 
			{
				exit ("<p>Неверный формат запроса! Проверьте URL!</p>");
			} 
			header("Location:$url");
			exit();
		}
	}       	
